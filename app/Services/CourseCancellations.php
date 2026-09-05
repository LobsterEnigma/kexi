<?php

namespace App\Services;

use App\Models\CourseCancellationRecord;
use App\Models\CourseMeeting;
use App\Models\CourseMeetingCancellation;

class CourseCancellations
{
    public function cancel(CourseMeeting $meeting, array $weeks, array $reason): void
    {
        foreach ($weeks as $week) {
            $cancellation = $meeting->cancellations()->firstOrCreate(['week_number' => $week], $reason);
            if ($cancellation->wasRecentlyCreated) {
                $this->record($meeting, $cancellation, 'canceled');
            }
        }
    }

    public function restore(CourseMeeting $meeting, array $weeks, string $action = 'restored'): void
    {
        foreach ($meeting->cancellations()->whereIn('week_number', $weeks)->get() as $cancellation) {
            $this->record($meeting, $cancellation, $action);
            $cancellation->delete();
        }
    }

    private function record(CourseMeeting $meeting, CourseMeetingCancellation $cancellation, string $action): void
    {
        $course = $meeting->course;
        $timetable = $course->timetable;
        CourseCancellationRecord::create([
            'timetable_id' => $timetable->id,
            'course_id' => $course->id,
            'course_meeting_id' => $meeting->id,
            'course_name' => $course->name,
            'course_code' => $course->code,
            'label' => $meeting->label,
            'week_number' => $cancellation->week_number,
            'weekday' => $meeting->weekday,
            'occurrence_date' => $timetable->term_start_date?->copy()->addWeeks($cancellation->week_number - 1)->addDays($meeting->weekday - 1),
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
            'action' => $action,
            'reason' => $cancellation->reason,
            'note' => $cancellation->note,
        ]);
    }
}
