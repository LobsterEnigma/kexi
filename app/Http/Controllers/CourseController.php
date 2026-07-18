<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Models\Course;
use App\Models\Timetable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function store(StoreCourseRequest $request, Timetable $timetable): RedirectResponse
    {
        $this->authorize('update', $timetable);
        $courseData = $request->safe()->only(['name', 'code', 'notes']);

        DB::transaction(function () use ($request, $timetable, $courseData): void {
            $course = $timetable->courses()->create([
                ...$courseData,
                'sort_order' => (int) $timetable->courses()->max('sort_order') + 1,
            ]);
            $course->meetings()->createMany($request->normalizedMeetings());
        });

        $viewParameters = $request->input('view_mode') === 'month'
            && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('view_month')) === 1
                ? ['view' => 'month', 'month' => $request->input('view_month')]
                : ['view' => 'week', 'week' => $request->integer('view_week', 1)];

        return redirect()->route('timetables.show', [
            'timetable' => $timetable,
            ...$viewParameters,
        ])->with('status', '课程已添加。');
    }

    public function update(StoreCourseRequest $request, Timetable $timetable, Course $course): RedirectResponse
    {
        abort_unless($course->timetable_id === $timetable->id, 404);
        $this->authorize('update', $course);
        $courseData = $request->safe()->only(['name', 'code', 'notes']);

        DB::transaction(function () use ($request, $course, $courseData): void {
            $course->update($courseData);
            $course->meetings()->delete();
            $course->meetings()->createMany($request->normalizedMeetings());
        });

        return back()->with('status', '课程已更新。');
    }

    public function destroy(Request $request, Timetable $timetable, Course $course): RedirectResponse
    {
        abort_unless($course->timetable_id === $timetable->id, 404);
        $this->authorize('delete', $course);
        $course->delete();

        return back()->with('status', '课程已删除。');
    }

    public function archive(Timetable $timetable, Course $course): RedirectResponse
    {
        abort_unless($course->timetable_id === $timetable->id, 404);
        $this->authorize('update', $course);
        $course->update(['is_archived' => true]);

        return back()->with('status', '课程已暂时隐藏，可随时恢复。');
    }

    public function restore(Timetable $timetable, Course $course): RedirectResponse
    {
        abort_unless($course->timetable_id === $timetable->id, 404);
        $this->authorize('update', $course);
        $course->update(['is_archived' => false]);

        return back()->with('status', '课程已恢复显示。');
    }
}
