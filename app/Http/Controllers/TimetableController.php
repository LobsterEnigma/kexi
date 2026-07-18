<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimetableRequest;
use App\Models\Timetable;
use App\Services\MonthCalendar;
use App\Services\ScheduleAnalyzer;
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
    ): View {
        $this->authorize('view', $timetable);
        $requestedWeek = $request->query('week');
        $week = min(max(
            $requestedWeek === null ? $timetable->currentWeek() : $request->integer('week'),
            1,
        ), $timetable->week_count);
        $viewMode = $request->query('view') === 'month' && $timetable->term_start_date
            ? 'month'
            : 'week';
        $monthCalendarData = $viewMode === 'month'
            ? $monthCalendar->build($timetable, $request->query('month'), $week)
            : null;
        $timetables = $request->user()->timetables()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
        $analysis = $analyzer->forWeek($timetable, $week);
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
