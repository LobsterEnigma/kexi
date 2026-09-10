<?php

namespace App\Services;

use App\Models\PersonalEvent;
use App\Models\Timetable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PersonalPlanner
{
    public function isOccurrence(PersonalEvent $event, string $date): bool
    {
        $start = $event->starts_at->timezone($event->timezone)->toDateString();
        if ($date < $start) {
            return false;
        }
        if ($event->repeat === 'none') {
            return $date === $start;
        }

        return $date <= $event->repeat_until->toDateString() && in_array(CarbonImmutable::parse($date)->isoWeekday(), $event->weekdays, true);
    }

    public function occurrence(PersonalEvent $event, string $date, bool $applyException = true): array
    {
        $start = $event->starts_at->timezone($event->timezone);
        $end = $event->ends_at->timezone($event->timezone);
        $offset = (int) $start->startOfDay()->diffInDays($end->startOfDay());
        $local = CarbonImmutable::parse($date, $event->timezone);
        $occurrenceStart = $local->setTime($start->hour, $start->minute);
        $occurrenceEnd = $local->addDays($offset)->setTime($end->hour, $end->minute);
        if ($occurrenceEnd->lte($occurrenceStart)) {
            $occurrenceEnd = $occurrenceStart->addMinutes(max(1, (int) $start->diffInMinutes($end)));
        }
        $exception = $applyException ? $event->exceptions->firstWhere('occurrence_date', $date) : null;
        $data = array_merge($event->only(['title', 'category', 'color', 'timezone', 'all_day', 'location', 'notes']), [
            'starts_at' => $occurrenceStart->utc(),
            'ends_at' => $occurrenceEnd->utc(),
        ], $exception?->overrides ?? []);
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'], 'UTC');
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at'], 'UTC');

        return [...$data, 'event_id' => $event->id, 'occurrence_date' => $date, 'canceled' => (bool) $exception?->canceled, 'repeat' => $event->repeat];
    }

    public function occurrences(User $user, CarbonImmutable $from, CarbonImmutable $to, bool $includeCanceled = false): Collection
    {
        $result = collect();
        foreach ($user->personalEvents()->with('exceptions')->get() as $event) {
            $dates = [];
            $start = $from->timezone($event->timezone)->subDays(7)->startOfDay()->max($event->starts_at->timezone($event->timezone)->startOfDay());
            $end = $to->timezone($event->timezone)->endOfDay();
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                if ($this->isOccurrence($event, $date->toDateString())) {
                    $dates[] = $date->toDateString();
                }
            }
            // A rescheduled occurrence may have moved into this range from a different week.
            $dates = array_unique([...$dates, ...$event->exceptions->pluck('occurrence_date')->all()]);
            foreach ($dates as $date) {
                if (! $this->isOccurrence($event, $date)) {
                    continue;
                }
                $item = $this->occurrence($event, $date);
                if ((! $item['canceled'] || $includeCanceled) && $item['starts_at']->lte($to->endOfDay()) && $item['ends_at']->gt($from->startOfDay())) {
                    $result->push($item);
                }
            }
        }

        return $result->sortBy([['starts_at', 'asc'], ['event_id', 'asc'], ['occurrence_date', 'asc']])->values();
    }

    public function calendar(Timetable $timetable, CarbonImmutable $from, CarbonImmutable $to, bool $public = false): array
    {
        $days = [];
        foreach ($this->occurrences($timetable->user, $from, $to) as $item) {
            $start = $item['starts_at']->timezone($timetable->timezone);
            $end = $item['ends_at']->timezone($timetable->timezone);
            $base = ['personal' => true, 'title' => $item['title'], 'type' => $item['category'], 'color' => $item['color'], 'completed' => false,
                'course' => null, 'kind' => 'personal', 'location' => $item['location'], 'conflict' => false,
                'url' => $public ? null : route('personal-events.edit', ['event' => $item['event_id'], 'occurrence' => $item['occurrence_date'], 'timetable' => $timetable->id]),
                'label' => $item['all_day'] ? '全天 · '.$item['category'] : '个人 · '.$item['category'], 'full_time' => $start->format('n/j H:i').'–'.$end->format('n/j H:i')];
            for ($date = $start->startOfDay()->max($from->startOfDay()); $date->lte($to) && $date->lt($end); $date = $date->addDay()) {
                $a = $start->max($date);
                $b = $end->min($date->addDay());
                $endMinute = $b->isSameDay($date) ? $b->hour * 60 + $b->minute : 1440;
                $row = [...$base, 'start_minute' => $a->hour * 60 + $a->minute, 'end_minute' => $endMinute, 'time' => $item['all_day'] ? '全天' : $a->format('H:i').'–'.($endMinute === 1440 ? '24:00' : $b->format('H:i'))];
                $days[$date->toDateString()][$item['all_day'] ? 'banners' : 'timed'][] = $row;
            }
        }

        return $days;
    }

    public function merge(array $academic, array $personal): array
    {
        foreach ($personal as $date => $day) {
            foreach (['banners', 'timed'] as $kind) {
                $academic[$date][$kind] = [...($academic[$date][$kind] ?? []), ...($day[$kind] ?? [])];
            }
        }

        return $academic;
    }

    public function conflicts(User $user, array $row, ?Timetable $timetable): array
    {
        $from = $row['starts_at'];
        $to = $row['ends_at'];
        $names = [];
        foreach ($this->occurrences($user, $from, $to) as $other) {
            if ($other['event_id'] === $row['event_id'] && $other['occurrence_date'] === $row['occurrence_date']) {
                continue;
            }
            if ($other['starts_at']->lt($to) && $other['ends_at']->gt($from)) {
                $names[] = $other['title'];
            }
        }
        if ($timetable?->term_start_date) {
            $localFrom = $from->timezone($timetable->timezone)->startOfDay();
            $localTo = $to->timezone($timetable->timezone);
            $academic = app(AcademicPlanner::class)->calendar($timetable, $localFrom, $localTo);
            for ($day = $localFrom; $day->lt($localTo); $day = $day->addDay()) {
                $week = (int) floor(CarbonImmutable::instance($timetable->weekStartDate())->diffInDays($day) / 7) + 1;
                $ranges = $academic[$day->toDateString()]['timed'] ?? [];
                if ($week >= 1 && $week <= $timetable->week_count) {
                    foreach (app(ScheduleAnalyzer::class)->forWeek($timetable, $week)['items'] as $item) {
                        if ((int) $item['meeting']->weekday === $day->isoWeekday() && $item['status'] !== 'canceled') {
                            $ranges[] = ['start_minute' => $item['start_minute'], 'end_minute' => $item['end_minute'], 'title' => $item['meeting']->course->name];
                        }
                    }
                }
                foreach ($ranges as $range) {
                    if (empty($range['completed']) && $day->addMinutes($range['start_minute'])->lt($to) && $day->addMinutes($range['end_minute'])->gt($from)) {
                        $names[] = $range['title'];
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }
}
