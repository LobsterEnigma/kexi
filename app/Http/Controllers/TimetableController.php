<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimetableRequest;
use App\Models\Timetable;
use App\Services\AcademicPlanner;
use App\Services\MonthCalendar;
use App\Services\PersonalPlanner;
use App\Services\ScheduleAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $timetable = $request->user()->timetables()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();

        if ($timetable) {
            return redirect()->route('timetables.show', $timetable);
        }

        return view('timetables.empty');
    }

    public function show(
        Request $request,
        Timetable $timetable,
        ScheduleAnalyzer $analyzer,
        MonthCalendar $monthCalendar,
        AcademicPlanner $academicPlanner,
        PersonalPlanner $personalPlanner,
    ): View {
        $this->authorize('view', $timetable);
        $display = $request->session()->get('calendar_display.'.$request->user()->id, ['courses' => true, 'academic' => true, 'personal' => true]);
        if ($request->has('display_settings')) {
            $request->validate(['show_courses' => ['required', 'boolean'], 'show_academic' => ['required', 'boolean'], 'show_personal' => ['required', 'boolean']]);
            foreach (['courses', 'academic', 'personal'] as $kind) {
                $display[$kind] = $request->boolean('show_'.$kind);
            }
            $request->session()->put('calendar_display.'.$request->user()->id, $display);
        }
        $requestedWeek = $request->query('week');
        $week = min(max(
            $requestedWeek === null ? $timetable->currentWeek() : $request->integer('week'),
            1,
        ), $timetable->week_count);
        $viewMode = $request->query('view') === 'month' && $timetable->term_start_date
            ? 'month'
            : 'week';
        $academicTasks = $academicPlanner->tasks($timetable);
        $academicDates = $academicTasks->flatMap(fn ($task) => [
            $task->opens_at, $task->due_at, $task->starts_at, $task->ends_at,
            ...$task->entries->flatMap(fn ($entry) => [$entry->starts_at, $entry->ends_at, $entry->due_at])->all(),
        ])->filter()->all();
        foreach ($request->user()->personalEvents()->with('exceptions')->get() as $personalEvent) {
            $academicDates[] = $personalEvent->starts_at;
            $academicDates[] = $personalEvent->ends_at;
            if ($personalEvent->repeat_until) {
                $academicDates[] = CarbonImmutable::parse($personalEvent->repeat_until->toDateString(), $personalEvent->timezone)->addDays(7);
            }
            foreach ($personalEvent->exceptions as $exception) {
                if (! $exception->canceled && isset($exception->overrides['starts_at'])) {
                    $academicDates[] = CarbonImmutable::parse($exception->overrides['starts_at'], 'UTC');
                }
            }
        }
        $monthCalendarData = $viewMode === 'month'
            ? $monthCalendar->build($timetable, $request->query('month'), $week, $academicDates)
            : null;
        $timetables = $request->user()->timetables()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
        $analysis = $analyzer->forWeek($timetable, $week);
        $academicDays = [];
        $academicLayout = null;
        $personalDays = [];
        if ($timetable->term_start_date) {
            $academicStart = $monthCalendarData
                ? $monthCalendarData['cells']->first()['date']
                : CarbonImmutable::instance($timetable->weekStartDate($week));
            $academicEnd = $monthCalendarData ? $monthCalendarData['cells']->last()['date'] : $academicStart->addDays(6);
            $personalDays = $personalPlanner->calendar($timetable, $academicStart, $academicEnd);
            $academicDays = $display['academic'] ? $academicPlanner->calendar($timetable, $academicStart, $academicEnd, $academicTasks) : [];
            if ($display['personal']) {
                $academicDays = $personalPlanner->merge($academicDays, $personalDays);
            }
            if (! $monthCalendarData) {
                $academicLayout = $academicPlanner->layoutWeek([...$analysis, 'items' => $display['courses'] ? $analysis['items'] : []], $academicDays, $academicStart);
            }
        }
        $shares = $timetable->shares()->latest()->get();
        $activeShare = $shares
            ->whereNull('revoked_at')
            ->first();

        return view('timetables.show', compact(
            'timetable',
            'timetables',
            'week',
            'viewMode',
            'monthCalendarData',
            'analysis',
            'activeShare',
            'shares',
            'academicDays',
            'academicLayout',
            'personalDays',
            'display',
        ));
    }

    public function store(StoreTimetableRequest $request): RedirectResponse
    {
        $data = $request->normalized();
        $data['timezone'] ??= config('kexi.display_timezone');

        $timetable = DB::transaction(function () use ($request, $data): Timetable {
            $hasTimetables = $request->user()->timetables()->exists();
            $makeDefault = ! $hasTimetables || ($data['is_default'] ?? false);

            if ($makeDefault) {
                $request->user()->timetables()->update(['is_default' => false]);
            }

            return $request->user()->timetables()->create([
                ...$data,
                'is_default' => $makeDefault,
            ]);
        });

        return redirect()->route('timetables.show', $timetable)
            ->with('status', '课表已创建。');
    }

    public function update(StoreTimetableRequest $request, Timetable $timetable): RedirectResponse
    {
        $this->authorize('update', $timetable);
        $data = $request->normalized();

        DB::transaction(function () use ($request, $timetable, $data): void {
            if ($data['is_default'] ?? false) {
                $request->user()->timetables()->whereKeyNot($timetable->id)->update(['is_default' => false]);
            }
            $timetable->update($data);
        });

        return back()->with('status', '课表设置已保存。');
    }

    public function destroy(Request $request, Timetable $timetable): RedirectResponse
    {
        $this->authorize('delete', $timetable);

        DB::transaction(function () use ($request, $timetable): void {
            $wasDefault = $timetable->is_default;
            $timetable->delete();
            if ($wasDefault) {
                $request->user()->timetables()->latest('updated_at')->first()?->update(['is_default' => true]);
            }
        });

        return redirect()->route('timetables.index')->with('status', '课表已删除。');
    }
}
