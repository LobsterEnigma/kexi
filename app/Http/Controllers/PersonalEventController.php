<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonalEventRequest;
use App\Models\PersonalEvent;
use App\Models\Timetable;
use App\Services\PersonalPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonalEventController extends Controller
{
    private function context(Request $request): ?Timetable
    {
        if ($request->filled('timetable')) {
            return $request->user()->timetables()->findOrFail($request->integer('timetable'));
        }

        return $request->user()->timetables()->orderByDesc('is_default')->first();
    }

    private function owned(Request $request, PersonalEvent $event): void
    {
        abort_unless($event->user_id === $request->user()->id, 404);
    }

    public function index(Request $request, PersonalPlanner $planner)
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'q' => ['nullable', 'string', 'max:160'], 'canceled' => ['nullable', 'boolean']]);
        $timetable = $this->context($request);
        $timezone = $timetable?->timezone ?? config('kexi.display_timezone');
        $month = CarbonImmutable::parse(($filters['month'] ?? now($timezone)->format('Y-m')).'-01', $timezone);
        $rows = $planner->occurrences($request->user(), $month, $month->endOfMonth(), $request->boolean('canceled'));
        if ($request->filled('q')) {
            $rows = $rows->filter(fn ($row) => mb_stripos($row['title'], $filters['q']) !== false)->values();
        }
        $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil($rows->count() / 20)));
        $events = new LengthAwarePaginator($rows->forPage($page, 20), $rows->count(), 20, $page, ['path' => route('personal-events.index'), 'query' => $request->except('page')]);

        return view('personal.index', compact('timetable', 'timezone', 'month', 'events', 'filters'));
    }

    public function create(Request $request)
    {
        $timetable = $this->context($request);
        $event = new PersonalEvent(['timezone' => $timetable?->timezone ?? config('kexi.display_timezone'), 'color' => '#168575', 'category' => '生活', 'repeat' => 'none', 'weekdays' => []]);

        return view('personal.edit', ['timetable' => $timetable, 'event' => $event, 'occurrence' => null, 'row' => null, 'conflicts' => []]);
    }

    public function store(PersonalEventRequest $request)
    {
        $timetable = $this->context($request);
        $event = $request->user()->personalEvents()->create($request->normalized());

        return redirect()->route('personal-events.edit', ['event' => $event, 'timetable' => $timetable?->id])->with('status', '个人安排已添加。');
    }

    public function edit(Request $request, PersonalEvent $event, PersonalPlanner $planner)
    {
        $this->owned($request, $event);
        $request->validate(['occurrence' => ['nullable', 'date_format:Y-m-d']]);
        $timetable = $this->context($request);
        $occurrence = $request->query('occurrence');
        if ($occurrence) {
            abort_unless($planner->isOccurrence($event, $occurrence), 404);
        }
        $row = $planner->occurrence($event, $occurrence ?: $event->starts_at->timezone($event->timezone)->toDateString(), (bool) $occurrence);
        $conflicts = $row['canceled'] ? [] : $planner->conflicts($request->user(), $row, $timetable);

        return view('personal.edit', compact('timetable', 'event', 'occurrence', 'row', 'conflicts'));
    }

    public function update(PersonalEventRequest $request, PersonalEvent $event, PersonalPlanner $planner)
    {
        $this->owned($request, $event);
        $timetable = $this->context($request);
        $data = $request->normalized();
        $scope = $request->input('scope', 'all');
        $date = $request->input('occurrence');
        if ($scope !== 'all') {
            abort_unless($date && $planner->isOccurrence($event, $date), 422);
        }
        $target = DB::transaction(function () use ($event, $scope, $date, $data, $request) {
            if ($scope === 'one') {
                $event->exceptions()->updateOrCreate(['occurrence_date' => $date], ['canceled' => false, 'overrides' => collect($data)->except(['repeat', 'weekdays', 'repeat_until'])->all()]);

                return $event;
            }
            if ($scope === 'future' && $date > $event->starts_at->timezone($event->timezone)->toDateString()) {
                $event->update(['repeat_until' => CarbonImmutable::parse($date)->subDay()->toDateString()]);
                $event->exceptions()->where('occurrence_date', '>=', $date)->delete();

                return $request->user()->personalEvents()->create($data);
            }
            $event->update($data);
            $event->exceptions()->delete();

            return $event;
        });

        return redirect()->route('personal-events.edit', ['event' => $target, 'timetable' => $timetable?->id, 'occurrence' => $scope === 'one' ? $date : null])->with('status', '安排已保存。时间重叠不会阻止保存，请留意下方提示。');
    }

    public function cancel(Request $request, PersonalEvent $event, PersonalPlanner $planner)
    {
        $this->owned($request, $event);
        $data = $request->validate(['occurrence' => ['required', 'date_format:Y-m-d'], 'action' => ['required', Rule::in(['cancel', 'restore', 'future'])]]);
        abort_unless($planner->isOccurrence($event, $data['occurrence']), 422);
        if ($data['action'] === 'future') {
            $start = $event->starts_at->timezone($event->timezone)->toDateString();
            if ($data['occurrence'] === $start || $event->repeat === 'none') {
                $event->delete();
            } else {
                $event->update(['repeat_until' => CarbonImmutable::parse($data['occurrence'])->subDay()->toDateString()]);
            }
        } else {
            $event->exceptions()->updateOrCreate(['occurrence_date' => $data['occurrence']], ['canceled' => $data['action'] === 'cancel']);
        }

        return redirect()->route('personal-events.index', ['timetable' => $request->input('timetable'), 'month' => substr($data['occurrence'], 0, 7), 'canceled' => 1])->with('status', $data['action'] === 'restore' ? '这次安排已恢复。' : '安排已取消。');
    }

    public function destroy(Request $request, PersonalEvent $event)
    {
        $this->owned($request, $event);
        $event->delete();

        return redirect()->route('personal-events.index', ['timetable' => $request->input('timetable')])->with('status', '整个安排已删除。');
    }
}
