<?php

namespace App\Services;

use App\Models\CourseMeeting;
use App\Models\Timetable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MonthCalendar
{
    /**
     * @return array{
     *     month: CarbonImmutable,
     *     label: string,
     *     first_month: string,
     *     last_month: string,
     *     previous_month: ?string,
     *     next_month: ?string,
     *     cells: Collection<int, array<string, mixed>>
     * }
     */
    public function build(Timetable $timetable, ?string $requestedMonth, int $fallbackWeek): array
    {
        $timezone = $timetable->timezone ?: config('kexi.display_timezone');
        $termStart = CarbonImmutable::parse(
            $timetable->term_start_date->toDateString(),
            $timezone,
        )->startOfDay();
        $termEnd = CarbonImmutable::parse(
            $timetable->resolvedTermEndDate()?->toDateString() ?? $termStart->addDays(($timetable->week_count * 7) - 1)->toDateString(),
            $timezone,
        )->endOfDay();
        $firstMonth = $termStart->startOfMonth();
        $lastMonth = $termEnd->startOfMonth();
        $fallbackMonth = $termStart
            ->addWeeks(max(0, min($timetable->week_count, $fallbackWeek) - 1))
            ->startOfMonth();
        $month = $this->parseMonth($requestedMonth, $timezone) ?? $fallbackMonth;

        if ($month->lessThan($firstMonth)) {
            $month = $firstMonth;
        } elseif ($month->greaterThan($lastMonth)) {
            $month = $lastMonth;
        }

        $timetable->loadMissing('courses.meetings.cancellations');
        $meetings = $timetable->courses
            ->reject(fn ($course) => $course->is_archived)
            ->flatMap->meetings
            ->values();
        $gridStart = $month->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $cells = collect();

        for ($date = $gridStart; $date->lessThanOrEqualTo($gridEnd); $date = $date->addDay()) {
            $inTerm = $date->betweenIncluded($termStart, $termEnd);
            $week = $inTerm
                ? intdiv((int) $termStart->diffInDays($date), 7) + 1
                : null;
            $events = $inTerm
                ? $this->eventsForDate($meetings, $date, $week)
                : collect();

            $cells->push([
                'date' => $date,
                'date_key' => $date->toDateString(),
                'in_month' => $date->isSameMonth($month),
                'in_term' => $inTerm,
                'is_today' => $date->isToday(),
                'week' => $week,
                'events' => $events->take(4)->values(),
                'overflow_count' => max(0, $events->count() - 4),
            ]);
        }

        return [
            'month' => $month,
            'label' => $month->isoFormat('YYYY年M月'),
            'first_month' => $firstMonth->format('Y-m'),
            'last_month' => $lastMonth->format('Y-m'),
            'previous_month' => $month->greaterThan($firstMonth) ? $month->subMonth()->format('Y-m') : null,
            'next_month' => $month->lessThan($lastMonth) ? $month->addMonth()->format('Y-m') : null,
            'cells' => $cells,
        ];
    }

    private function parseMonth(?string $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return null;
        }

        [$year, $month] = array_map('intval', explode('-', $value));

        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->startOfMonth();
    }

    /**
     * @param  Collection<int, CourseMeeting>  $meetings
     * @return Collection<int, CourseMeeting>
     */
    private function eventsForDate(Collection $meetings, CarbonImmutable $date, int $week): Collection
    {
        return $meetings
            ->filter(fn (CourseMeeting $meeting) => (int) $meeting->weekday === $date->isoWeekday()
                && $meeting->occursInWeek($week))
            ->sortBy([
                ['starts_at', 'asc'],
                ['ends_at', 'asc'],
                [fn (CourseMeeting $meeting) => $meeting->course->sort_order, 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
