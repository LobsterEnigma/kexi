<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMeeting;
use App\Models\Timetable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseMeetingCancellationController extends Controller
{
    public function store(
        Request $request,
        Timetable $timetable,
        Course $course,
        CourseMeeting $meeting,
    ): RedirectResponse {
        $this->authorizeMeeting($timetable, $course, $meeting);
        $weeks = $this->validatedWeeks($request, $timetable, $meeting, true);

        DB::transaction(function () use ($meeting, $weeks): void {
            foreach ($weeks as $week) {
                $meeting->cancellations()->firstOrCreate(['week_number' => $week]);
            }
        });

        return back()->with('status', count($weeks) === 1 ? '本次课程已取消。' : '所选课程已批量取消。');
    }

    public function update(
        Request $request,
        Timetable $timetable,
        Course $course,
        CourseMeeting $meeting,
    ): RedirectResponse {
        $this->authorizeMeeting($timetable, $course, $meeting);
        $weeks = $this->validatedWeeks($request, $timetable, $meeting, false);

        DB::transaction(function () use ($meeting, $weeks): void {
            $meeting->cancellations()->delete();
            $meeting->cancellations()->createMany(
                array_map(fn (int $week): array => ['week_number' => $week], $weeks),
            );
        });

        return back()->with('status', $weeks === [] ? '已恢复该时间段的全部课程。' : '停课安排已更新。');
    }

    public function destroy(
        Request $request,
        Timetable $timetable,
        Course $course,
        CourseMeeting $meeting,
    ): RedirectResponse {
        $this->authorizeMeeting($timetable, $course, $meeting);
        $weeks = $this->validatedWeeks($request, $timetable, $meeting, true);
        $meeting->cancellations()->whereIn('week_number', $weeks)->delete();

        return back()->with('status', count($weeks) === 1 ? '本次课程已恢复。' : '所选课程已恢复。');
    }

    private function authorizeMeeting(Timetable $timetable, Course $course, CourseMeeting $meeting): void
    {
        abort_unless($course->timetable_id === $timetable->id, 404);
        abort_unless($meeting->course_id === $course->id, 404);
        $this->authorize('update', $course);
    }

    /** @return list<int> */
    private function validatedWeeks(
        Request $request,
        Timetable $timetable,
        CourseMeeting $meeting,
        bool $requireSelection,
    ): array {
        if (! $requireSelection && ! $request->has('weeks')) {
            $request->merge(['weeks' => []]);
        }

        $data = $request->validate([
            'weeks' => [$requireSelection ? 'required' : 'present', 'array', $requireSelection ? 'min:1' : 'min:0', 'max:53'],
            'weeks.*' => ['integer', 'distinct', 'between:1,'.$timetable->week_count],
        ]);
        $weeks = collect($data['weeks'])
            ->map(fn (mixed $week): int => (int) $week)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (collect($weeks)->contains(fn (int $week): bool => ! $meeting->occursInWeek($week))) {
            throw ValidationException::withMessages([
                'weeks' => '只能选择该时间段原本会发生的教学周。',
            ]);
        }

        return $weeks;
    }
}
