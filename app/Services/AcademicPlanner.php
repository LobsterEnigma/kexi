<?php

namespace App\Services;

use App\Models\AcademicTask;
use App\Models\Timetable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AcademicPlanner
{
    public function tasks(Timetable $timetable): Collection
    {
        return $timetable->academicTasks()->with(['course', 'entries'])->get();
    }

    public function calendar(Timetable $timetable, CarbonImmutable $from, CarbonImmutable $to, ?Collection $tasks = null): array
    {
        $days = [];
        $timezone = $timetable->timezone;
        foreach ($tasks ?? $this->tasks($timetable) as $task) {
            $base = ['task_id' => $task->id, 'title' => $task->title, 'course' => $task->course?->code ?: $task->course?->name,
                'color' => $task->color(), 'completed' => (bool) $task->completed_at,
                'url' => route('academic-tasks.edit', [$timetable, $task]), 'type' => AcademicTask::TYPES[$task->type]];
            $due = $task->due_at?->timezone($timezone);
            $open = $task->opens_at?->timezone($timezone);
            for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
                $key = $date->toDateString();
                if ($due && $due->isSameDay($date)) {
                    $days[$key]['banners'][] = [...$base, 'kind' => 'due', 'label' => '截止 '.$due->format('H:i')];
                } elseif ($open && $due && $open->lte($date->endOfDay()) && $due->gte($date)) {
                    $days[$key]['banners'][] = [...$base, 'kind' => 'window', 'label' => ($open->isSameDay($date) ? $open->format('H:i').' 开放' : '可作答 / 提交').' · 至 '.$due->format('n/j H:i')];
                } elseif ($open && ! $due && $open->isSameDay($date)) {
                    $days[$key]['banners'][] = [...$base, 'kind' => 'open', 'label' => $open->format('H:i').' 开放 · 截止待定'];
                }
            }
            $this->addTimed($days, $base, $task->starts_at, $task->ends_at, $task->location, 'event', $from, $to, $timezone);
            foreach ($task->entries as $entry) {
                $child = [...$base, 'title' => $entry->title, 'parent_title' => $task->title, 'completed' => $base['completed'] || (bool) $entry->completed_at, 'url' => $base['url'].'#entry-'.$entry->id];
                if ($entry->kind === 'study') {
                    $this->addTimed($days, $child, $entry->starts_at, $entry->ends_at, $entry->location, 'study', $from, $to, $timezone);
                } elseif ($entry->due_at) {
                    $date = $entry->due_at->timezone($timezone);
                    if ($date->betweenIncluded($from->startOfDay(), $to->endOfDay())) {
                        $days[$date->toDateString()]['banners'][] = [...$child, 'kind' => 'milestone', 'label' => '阶段截止 '.$date->format('H:i')];
                    }
                }
            }
        }
        foreach ($days as &$day) {
            $day['banners'] = collect($day['banners'] ?? [])->sortBy([
                [fn ($event) => $event['completed'], 'asc'],
                [fn ($event) => $event['kind'] === 'window' ? 1 : 0, 'asc'],
                ['label', 'asc'],
            ])->values()->all();
            $day['timed'] = collect($day['timed'] ?? [])->sortBy('start_minute')->values()->all();
        }
        unset($day);

        return $days;
    }

    private function addTimed(array &$days, array $base, ?CarbonImmutable $start, ?CarbonImmutable $end, ?string $location, string $kind, CarbonImmutable $from, CarbonImmutable $to, string $timezone): void
    {
        if (! $start || ! $end) {
            return;
        }
        $start = $start->timezone($timezone);
        $end = $end->timezone($timezone);
        if ($start->gt($to->endOfDay()) || $end->lte($from->startOfDay())) {
            return;
        }
        for ($date = $start->startOfDay()->max($from->startOfDay()); $date->lte($to) && $date->lt($end); $date = $date->addDay()) {
            $segmentStart = $start->max($date);
            $segmentEnd = $end->min($date->addDay());
            $startMinute = $segmentStart->hour * 60 + $segmentStart->minute;
            $endMinute = $segmentEnd->isSameDay($date) ? $segmentEnd->hour * 60 + $segmentEnd->minute : 1440;
            $days[$date->toDateString()]['timed'][] = [...$base, 'kind' => $kind, 'label' => $kind === 'study' ? '学习计划' : '考试 / 活动',
                'start_minute' => $startMinute, 'end_minute' => $endMinute, 'time' => $segmentStart->format('H:i').'–'.($endMinute === 1440 ? '24:00' : $segmentEnd->format('H:i')),
                'full_time' => $start->format('n/j H:i').'–'.$end->format('n/j H:i'), 'location' => $location, 'conflict' => false];
        }
    }

    /** Lay out academic events and courses together, keeping the original course diagnostics unchanged. */
    public function layoutWeek(array $analysis, array $days, CarbonImmutable $start): array
    {
        $academic = [];
        foreach (range(1, 7) as $weekday) {
            foreach ($days[$start->addDays($weekday - 1)->toDateString()]['timed'] ?? [] as $event) {
                $academic[] = [...$event, 'weekday' => $weekday];
            }
        }
        $dayStart = min([$analysis['day_start'], ...array_map(fn ($e) => (int) floor($e['start_minute'] / 60) * 60, $academic)]);
        $dayEnd = max([$analysis['day_end'], ...array_map(fn ($e) => (int) ceil($e['end_minute'] / 60) * 60, $academic)]);
        $courses = $analysis['items'];
        foreach ($courses as &$item) {
            $item['top'] = $item['start_minute'] - $dayStart;
        }
        unset($item);
        foreach (range(1, 7) as $weekday) {
            $intervals = [];
            foreach ($courses as $id => $item) {
                if ((int) $item['meeting']->weekday === $weekday) {
                    $intervals[] = ['group' => 'course', 'id' => $id, 'start' => $item['start_minute'], 'end' => $item['end_minute'], 'inactive' => $item['status'] === 'canceled'];
                }
            }
            foreach ($academic as $id => $item) {
                if ($item['weekday'] === $weekday) {
                    $intervals[] = ['group' => 'academic', 'id' => $id, 'start' => $item['start_minute'], 'end' => $item['end_minute'], 'inactive' => $item['completed']];
                }
            }
            usort($intervals, fn ($a, $b) => [$a['start'], $a['end'], $a['group'], $a['id']] <=> [$b['start'], $b['end'], $b['group'], $b['id']]);
            foreach ($intervals as $a) {
                if ($a['group'] === 'academic' && ! $a['inactive']) {
                    foreach ($intervals as $b) {
                        if (! $b['inactive'] && ($a['group'] !== $b['group'] || $a['id'] !== $b['id']) && min($a['end'], $b['end']) > max($a['start'], $b['start'])) {
                            $academic[$a['id']]['conflict'] = true;
                        }
                    }
                }
            }
            $cluster = [];
            $clusterEnd = 0;
            $flush = function () use (&$cluster, &$courses, &$academic): void {
                $laneEnds = [];
                foreach ($cluster as &$item) {
                    $lane = null;
                    foreach ($laneEnds as $candidate => $end) {
                        if ($end <= $item['start']) {
                            $lane = $candidate;
                            break;
                        }
                    }
                    $lane ??= count($laneEnds);
                    $laneEnds[$lane] = $item['end'];
                    $item['lane'] = $lane;
                }
                unset($item);
                foreach ($cluster as $item) {
                    if ($item['group'] === 'course') {
                        $courses[$item['id']]['lane'] = $item['lane'];
                        $courses[$item['id']]['lane_count'] = count($laneEnds);
                    } else {
                        $academic[$item['id']]['lane'] = $item['lane'];
                        $academic[$item['id']]['lane_count'] = count($laneEnds);
                    }
                }
                $cluster = [];
            };
            foreach ($intervals as $item) {
                if ($cluster && $item['start'] >= $clusterEnd) {
                    $flush();
                } $cluster[] = $item;
                $clusterEnd = max($clusterEnd, $item['end']);
            }
            $flush();
        }

        return ['items' => $courses, 'academic' => $academic, 'day_start' => $dayStart, 'day_end' => $dayEnd];
    }

    public function reminders(Timetable $timetable): Collection
    {
        $now = CarbonImmutable::now('UTC');
        $result = collect();
        foreach ($this->tasks($timetable)->whereNull('completed_at') as $task) {
            $targets = [['model' => $task, 'scope' => 'task', 'time' => $task->due_at, 'label' => '任务截止', 'suffix' => 'due'], ['model' => $task, 'scope' => 'task', 'time' => $task->starts_at, 'label' => '考试 / 活动开始', 'suffix' => 'start']];
            foreach ($task->entries->whereNull('completed_at') as $entry) {
                $targets[] = ['model' => $entry, 'scope' => 'entry', 'time' => $entry->kind === 'study' ? $entry->starts_at : $entry->due_at, 'label' => $entry->kind === 'study' ? '学习计划开始' : '项目阶段截止', 'suffix' => $entry->kind];
            }
            foreach ($targets as $target) {
                $model = $target['model'];
                $time = $target['time'];
                if (! $time || $model->reminder_minutes === null) {
                    continue;
                }
                $trigger = $time->subMinutes($model->reminder_minutes);
                if ($now->lt($trigger) || ($model->reminder_ack_at && $model->reminder_ack_at->gte($trigger))) {
                    continue;
                }
                $key = $target['scope'].'-'.$model->id.'-'.$target['suffix'].'-'.$time->timestamp;
                $result->push(['key' => $key, 'title' => $model->title, 'task_title' => $task->title, 'label' => $target['label'], 'time' => $time->timezone($timetable->timezone)->format('n/j H:i'),
                    'overdue' => $time->lt($now), 'sort' => $time->timestamp, 'url' => route('academic-tasks.edit', [$timetable, $task]).($target['scope'] === 'entry' ? '#entry-'.$model->id : ''), 'model' => $model]);
            }
        }

        return $result->sortBy('sort')->values();
    }
}
