<?php

namespace App\Services;

use App\Enums\ScheduleSeverity;
use App\Models\CourseMeeting;
use App\Models\Timetable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ScheduleAnalyzer
{
    /** @return array<string, mixed> */
    public function forWeek(Timetable $timetable, int $week): array
    {
        if ($week < 1 || $week > $timetable->week_count) {
            throw new InvalidArgumentException('Week is outside this timetable term.');
        }

        $timetable->loadMissing('courses.meetings.cancellations');

        $occurrences = $timetable->courses
            ->reject(fn ($course) => $course->is_archived)
            ->flatMap->meetings
            ->filter(fn (CourseMeeting $meeting) => $meeting->occursInWeek($week))
            ->values();
        $canceledMeetings = $occurrences
            ->filter(fn (CourseMeeting $meeting) => $meeting->isCanceledInWeek($week))
            ->values();
        $meetings = $occurrences
            ->reject(fn (CourseMeeting $meeting) => $meeting->isCanceledInWeek($week))
            ->values();

        $analysis = $this->analyzeMeetings(
            $meetings,
            $week,
            (int) $timetable->near_threshold_minutes,
        );

        return $this->withCanceledMeetings($analysis, $canceledMeetings);
    }

    /** @return array<string, mixed> */
    public function forTerm(Timetable $timetable): array
    {
        $grouped = [];

        for ($week = 1; $week <= $timetable->week_count; $week++) {
            foreach ($this->forWeek($timetable, $week)['issues'] as $issue) {
                $key = implode(':', [
                    $issue['type'],
                    ...$issue['meeting_ids'],
                    $issue['minutes'],
                ]);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = $issue;
                    $grouped[$key]['affected_weeks'] = [];
                }

                $grouped[$key]['affected_weeks'][] = $week;
            }
        }

        return [
            'policy' => [
                'version' => 'timetable_v1',
                'near_threshold_minutes' => (int) $timetable->near_threshold_minutes,
            ],
            'issues' => array_values($grouped),
        ];
    }

    /**
     * @param  Collection<int, CourseMeeting>  $meetings
     * @return array<string, mixed>
     */
    public function analyzeMeetings(Collection $meetings, int $week, int $nearThreshold): array
    {
        $defaultDayStart = (int) config('kexi.schedule.day_start');
        $defaultDayEnd = (int) config('kexi.schedule.day_end');
        $dayStart = $meetings->isEmpty()
            ? $defaultDayStart
            : min($defaultDayStart, (int) floor($meetings->min(fn (CourseMeeting $meeting) => $meeting->startMinute()) / 60) * 60);
        $dayEnd = $meetings->isEmpty()
            ? $defaultDayEnd
            : max($defaultDayEnd, (int) ceil($meetings->max(fn (CourseMeeting $meeting) => $meeting->endMinute()) / 60) * 60);
        $pixelsPerMinute = (float) config('kexi.schedule.pixels_per_minute');
        $items = [];

        foreach ($meetings as $index => $meeting) {
            $key = (string) ($meeting->getKey() ?? "new-{$index}");
            $start = $meeting->startMinute();
            $end = $meeting->endMinute();
            $items[$key] = [
                'key' => $key,
                'meeting' => $meeting,
                'start_minute' => $start,
                'end_minute' => $end,
                'top' => max(0, ($start - $dayStart) * $pixelsPerMinute),
                'height' => max(18, ($end - $start) * $pixelsPerMinute),
                'status' => ScheduleSeverity::SlackDeep->value,
                'nearest_gap' => null,
                'issue_ids' => [],
                'lane' => 0,
                'lane_count' => 1,
            ];
        }

        $issues = [];
        $allGaps = [];

        foreach (collect($items)->groupBy(fn (array $item) => $item['meeting']->weekday) as $weekday => $dayItems) {
            $keys = $dayItems->sortBy([
                ['start_minute', 'asc'],
                ['end_minute', 'asc'],
                ['key', 'asc'],
            ])->pluck('key')->values()->all();

            for ($left = 0; $left < count($keys); $left++) {
                for ($right = $left + 1; $right < count($keys); $right++) {
                    $leftKey = $keys[$left];
                    $rightKey = $keys[$right];
                    $overlap = min($items[$leftKey]['end_minute'], $items[$rightKey]['end_minute'])
                        - max($items[$leftKey]['start_minute'], $items[$rightKey]['start_minute']);

                    if ($overlap > 0) {
                        $issueId = "overlap-{$week}-{$weekday}-{$leftKey}-{$rightKey}";
                        $issues[] = $this->issue(
                            $issueId,
                            'overlap',
                            $items[$leftKey],
                            $items[$rightKey],
                            $overlap,
                            $week,
                        );
                        $this->applySeverity($items[$leftKey], ScheduleSeverity::Conflict, $issueId);
                        $this->applySeverity($items[$rightKey], ScheduleSeverity::Conflict, $issueId);

                        continue;
                    }

                    $gap = max($items[$leftKey]['start_minute'], $items[$rightKey]['start_minute'])
                        - min($items[$leftKey]['end_minute'], $items[$rightKey]['end_minute']);
                    $allGaps[] = $gap;
                    $this->rememberGap($items[$leftKey], $gap);
                    $this->rememberGap($items[$rightKey], $gap);

                    if ($gap <= $nearThreshold) {
                        $issueId = "near-{$week}-{$weekday}-{$leftKey}-{$rightKey}";
                        $issues[] = $this->issue(
                            $issueId,
                            'near',
                            $items[$leftKey],
                            $items[$rightKey],
                            $gap,
                            $week,
                        );
                        $this->applySeverity($items[$leftKey], ScheduleSeverity::Near, $issueId);
                        $this->applySeverity($items[$rightKey], ScheduleSeverity::Near, $issueId);
                    }
                }
            }

            $this->assignLanes($items, $keys);
        }

        foreach ($items as &$item) {
            if (in_array($item['status'], [ScheduleSeverity::Conflict->value, ScheduleSeverity::Near->value], true)) {
                continue;
            }

            $item['status'] = $this->slackSeverity($item['nearest_gap'], $nearThreshold)->value;
        }
        unset($item);

        $orderedItems = collect($items)->sortBy([
            [fn (array $item) => $item['meeting']->weekday, 'asc'],
            ['start_minute', 'asc'],
            ['key', 'asc'],
        ])->values()->all();

        return [
            'policy' => [
                'version' => 'timetable_v1',
                'near_threshold_minutes' => $nearThreshold,
            ],
            'week' => $week,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'items' => $orderedItems,
            'issues' => $issues,
            'summary' => [
                'conflicts' => count(array_filter($issues, fn (array $issue) => $issue['type'] === 'overlap')),
                'near' => count(array_filter($issues, fn (array $issue) => $issue['type'] === 'near')),
                'closest_gap' => $allGaps === [] ? null : min($allGaps),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  Collection<int, CourseMeeting>  $meetings
     * @return array<string, mixed>
     */
    private function withCanceledMeetings(array $analysis, Collection $meetings): array
    {
        $analysis['summary']['canceled'] = $meetings->count();

        if ($meetings->isEmpty()) {
            return $analysis;
        }

        $pixelsPerMinute = (float) config('kexi.schedule.pixels_per_minute');
        $originalDayStart = (int) $analysis['day_start'];
        $dayStart = min(
            $originalDayStart,
            (int) floor($meetings->min(fn (CourseMeeting $meeting) => $meeting->startMinute()) / 60) * 60,
        );
        $dayEnd = max(
            (int) $analysis['day_end'],
            (int) ceil($meetings->max(fn (CourseMeeting $meeting) => $meeting->endMinute()) / 60) * 60,
        );
        $topOffset = ($originalDayStart - $dayStart) * $pixelsPerMinute;

        if ($topOffset > 0) {
            foreach ($analysis['items'] as &$item) {
                $item['top'] += $topOffset;
            }
            unset($item);
        }

        $canceledItems = $meetings->map(function (CourseMeeting $meeting) use ($dayStart, $pixelsPerMinute): array {
            $start = $meeting->startMinute();
            $end = $meeting->endMinute();

            return [
                'key' => (string) $meeting->getKey(),
                'meeting' => $meeting,
                'start_minute' => $start,
                'end_minute' => $end,
                'top' => max(0, ($start - $dayStart) * $pixelsPerMinute),
                'height' => max(18, ($end - $start) * $pixelsPerMinute),
                'status' => 'canceled',
                'nearest_gap' => null,
                'issue_ids' => [],
                'lane' => 0,
                'lane_count' => 1,
                'is_canceled' => true,
            ];
        });

        $allItems = collect($analysis['items'])
            ->concat($canceledItems)
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item])
            ->all();

        foreach (collect($allItems)->groupBy(fn (array $item) => $item['meeting']->weekday) as $dayItems) {
            $keys = $dayItems->sortBy([
                ['start_minute', 'asc'],
                ['end_minute', 'asc'],
                ['key', 'asc'],
            ])->pluck('key')->values()->all();
            $this->assignLanes($allItems, $keys);
        }

        $analysis['day_start'] = $dayStart;
        $analysis['day_end'] = $dayEnd;
        $analysis['items'] = collect($allItems)
            ->sortBy([
                [fn (array $item) => $item['meeting']->weekday, 'asc'],
                ['start_minute', 'asc'],
                ['key', 'asc'],
            ])
            ->values()
            ->all();

        return $analysis;
    }

    /** @param array<string, mixed> $item */
    private function applySeverity(array &$item, ScheduleSeverity $severity, string $issueId): void
    {
        $current = ScheduleSeverity::from($item['status']);
        if ($severity->priority() > $current->priority()) {
            $item['status'] = $severity->value;
        }
        $item['issue_ids'][] = $issueId;
    }

    /** @param array<string, mixed> $item */
    private function rememberGap(array &$item, int $gap): void
    {
        $item['nearest_gap'] = $item['nearest_gap'] === null
            ? $gap
            : min($item['nearest_gap'], $gap);
    }

    private function slackSeverity(?int $gap, int $threshold): ScheduleSeverity
    {
        if ($gap === null || $gap > $threshold * 4) {
            return ScheduleSeverity::SlackDeep;
        }

        if ($gap <= $threshold * 2) {
            return ScheduleSeverity::SlackLight;
        }

        return ScheduleSeverity::SlackMedium;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<string>  $keys
     */
    private function assignLanes(array &$items, array $keys): void
    {
        $cluster = [];
        $clusterEnd = null;

        foreach ($keys as $key) {
            if ($cluster !== [] && $items[$key]['start_minute'] >= $clusterEnd) {
                $this->assignClusterLanes($items, $cluster);
                $cluster = [];
                $clusterEnd = null;
            }

            $cluster[] = $key;
            $clusterEnd = max($clusterEnd ?? 0, $items[$key]['end_minute']);
        }

        if ($cluster !== []) {
            $this->assignClusterLanes($items, $cluster);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<string>  $cluster
     */
    private function assignClusterLanes(array &$items, array $cluster): void
    {
        $laneEnds = [];

        foreach ($cluster as $key) {
            $lane = null;
            foreach ($laneEnds as $candidate => $end) {
                if ($end <= $items[$key]['start_minute']) {
                    $lane = $candidate;
                    break;
                }
            }

            $lane ??= count($laneEnds);
            $laneEnds[$lane] = $items[$key]['end_minute'];
            $items[$key]['lane'] = $lane;
        }

        $laneCount = max(1, count($laneEnds));
        foreach ($cluster as $key) {
            $items[$key]['lane_count'] = $laneCount;
        }
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function issue(string $id, string $type, array $left, array $right, int $minutes, int $week): array
    {
        $leftMeeting = $left['meeting'];
        $rightMeeting = $right['meeting'];
        $leftName = $leftMeeting->course->name;
        $rightName = $rightMeeting->course->name;

        return [
            'id' => $id,
            'type' => $type,
            'meeting_ids' => [(int) $leftMeeting->getKey(), (int) $rightMeeting->getKey()],
            'course_names' => [$leftName, $rightName],
            'meeting_details' => [
                $this->meetingDetail($leftMeeting),
                $this->meetingDetail($rightMeeting),
            ],
            'weekday' => (int) $leftMeeting->weekday,
            'minutes' => $minutes,
            'affected_weeks' => [$week],
            'message' => $type === 'overlap'
                ? "{$leftName} 与 {$rightName} 重叠 {$minutes} 分钟"
                : "{$leftName} → {$rightName} 间隔 {$minutes} 分钟",
        ];
    }

    /** @return array<string, mixed> */
    private function meetingDetail(CourseMeeting $meeting): array
    {
        return [
            'course_name' => (string) $meeting->course->name,
            'course_code' => (string) ($meeting->course->code ?? ''),
            'label' => (string) ($meeting->label ?? ''),
            'teacher' => (string) ($meeting->teacher ?? ''),
            'starts_at' => substr((string) $meeting->starts_at, 0, 5),
            'ends_at' => substr((string) $meeting->ends_at, 0, 5),
            'location' => (string) ($meeting->location ?? ''),
        ];
    }
}
