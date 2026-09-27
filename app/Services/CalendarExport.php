<?php

namespace App\Services;

use App\Models\Timetable;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Sabre\VObject\Component\VCalendar;

class CalendarExport
{
    public const CONTENTS = ['courses' => '课程', 'tasks' => '任务截止与考试', 'study' => '学习计划与项目阶段', 'personal' => '个人安排'];

    public function build(Timetable $timetable, array $contents, string $from, string $to): string
    {
        $start = CarbonImmutable::parse($from, $timetable->timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timetable->timezone)->endOfDay();
        $calendar = new VCalendar;
        $calendar->PRODID = '-//Kexi//Calendar//ZH';
        $calendar->add('X-WR-CALNAME', $timetable->name);
        $calendar->add('X-WR-TIMEZONE', $timetable->timezone);
        $calendar->add('REFRESH-INTERVAL', 'PT1H', ['VALUE' => 'DURATION']);
        $count = 0;
        $add = function (string $identity, string $title, $a, $b, ?string $location, bool $allDay = false, bool $canceled = false, bool $complete = false) use ($calendar, $start, $end, &$count) {
            if (! $a) {
                return;
            }
            $a = CarbonImmutable::instance($a);
            $b = $b ? CarbonImmutable::instance($b) : $a;
            if ($a->gt($end) || ($b->gt($a) ? $b->lte($start) : $a->lt($start))) {
                return;
            }
            if (++$count > 10000) {
                throw ValidationException::withMessages(['calendar' => '此范围内安排过多，请缩小日期范围。']);
            }
            $event = $calendar->add('VEVENT', [
                'UID' => hash_hmac('sha256', $identity, (string) config('app.key')).'@kexi',
                'DTSTAMP' => CarbonImmutable::now('UTC')->toDateTimeImmutable(),
                'SUMMARY' => ($complete ? '已完成 · ' : '').$title,
                'STATUS' => $canceled ? 'CANCELLED' : 'CONFIRMED',
                'TRANSP' => $canceled || $complete || $b->eq($a) ? 'TRANSPARENT' : 'OPAQUE',
            ]);
            if ($allDay) {
                $event->add('DTSTART', $a->format('Ymd'), ['VALUE' => 'DATE']);
                $event->add('DTEND', $b->format('Ymd'), ['VALUE' => 'DATE']);
            } else {
                $event->add('DTSTART', $a->utc()->toDateTimeImmutable());
                if ($b->gt($a)) {
                    $event->add('DTEND', $b->utc()->toDateTimeImmutable());
                }
            }
            if ($location) {
                $event->add('LOCATION', $location);
            }
            // Notes, URLs and cancellation reasons are deliberately excluded from bearer feeds.
        };
        if (in_array('courses', $contents, true) && $timetable->term_start_date) {
            $timetable->loadMissing('courses.meetings.cancellations');
            foreach ($timetable->courses->where('is_archived', false) as $course) {
                foreach ($course->meetings as $meeting) {
                    for ($week = 1; $week <= $timetable->week_count; $week++) {
                        if (! $meeting->occursInWeek($week)) {
                            continue;
                        }
                        $date = $timetable->occurrenceDate($week, $meeting->weekday)->toDateString();
                        $a = CarbonImmutable::parse($date.' '.$meeting->starts_at, $timetable->timezone);
                        $b = CarbonImmutable::parse($date.' '.$meeting->ends_at, $timetable->timezone);
                        $add('course:'.$meeting->id.':'.$date, $course->name.($meeting->label ? ' · '.$meeting->label : ''), $a, $b, $meeting->location, false, $meeting->isCanceledInWeek($week));
                    }
                }
            }
        }
        if (array_intersect(['tasks', 'study'], $contents)) {
            foreach ($timetable->academicTasks()->with('entries')->get() as $task) {
                if (in_array('tasks', $contents, true)) {
                    foreach (['opens_at' => '开放 · ', 'due_at' => '截止 · '] as $field => $prefix) {
                        $add('task:'.$task->id.':'.$field, $prefix.$task->title, $task->$field, null, null, false, false, (bool) $task->completed_at);
                    }
                    $add('task:'.$task->id.':event', $task->title, $task->starts_at, $task->ends_at, $task->location, false, false, (bool) $task->completed_at);
                }
                if (in_array('study', $contents, true)) {
                    foreach ($task->entries as $entry) {
                        $add('entry:'.$entry->id, ($entry->kind === 'study' ? '学习 · ' : '阶段截止 · ').$entry->title.' · '.$task->title, $entry->kind === 'study' ? $entry->starts_at : $entry->due_at, $entry->kind === 'study' ? $entry->ends_at : null, $entry->location, false, false, (bool) ($task->completed_at || $entry->completed_at));
                    }
                }
            }
        }
        if (in_array('personal', $contents, true)) {
            foreach (app(PersonalPlanner::class)->occurrences($timetable->user, $start, $end, true) as $item) {
                $add('personal:'.$item['event_id'].':'.$item['occurrence_date'], $item['title'], $item['starts_at']->timezone($item['timezone']), $item['ends_at']->timezone($item['timezone']), $item['location'], $item['all_day'], $item['canceled']);
            }
        }

        return $calendar->serialize();
    }
}
