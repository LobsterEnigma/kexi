<?php

namespace App\Services;

use App\Models\AcademicTask;
use App\Models\Timetable;

class AcademicTaskExport
{
    public function build(Timetable $timetable, array $options): array
    {
        $query = $timetable->academicTasks()->with(['course', 'entries']);
        if ($options['scope'] === 'selected') {
            $query->where(function ($query) use ($options): void {
                $query->whereIn('course_id', $options['courses'] ?? []);
                if (! empty($options['independent'])) {
                    $query->orWhereNull('course_id');
                }
            });
        }
        if ($options['status'] === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($options['status'] === 'completed') {
            $query->whereNotNull('completed_at');
        }
        $tasks = $query->orderByRaw('COALESCE(due_at, starts_at, opens_at) IS NULL')
            ->orderByRaw('COALESCE(due_at, starts_at, opens_at)')->orderBy('id')->get();
        $date = fn ($value) => $value?->timezone($timetable->timezone)->format('Y-m-d H:i') ?? '';
        $groups = $tasks->groupBy(fn ($task) => $task->course_id ?? 'independent')->map(function ($tasks) use ($options, $date) {
            $course = $tasks->first()->course;
            return [
                'course' => $course ? trim(($course->code ? $course->code.' · ' : '').$course->name) : '独立任务（未关联课程）',
                'tasks' => $tasks->map(function ($task) use ($options, $date) {
                    return [
                        'title' => $task->title, 'type' => AcademicTask::TYPES[$task->type],
                        'status' => $task->completed_at ? '已完成' : '待完成',
                        'opens' => $date($task->opens_at), 'due' => $date($task->due_at),
                        'start' => $date($task->starts_at), 'end' => $date($task->ends_at),
                        'location' => $task->location ?? '',
                        'notes' => ! empty($options['notes']) ? ($task->notes ?? '') : '',
                        'url' => ! empty($options['notes']) ? ($task->url ?? '') : '',
                        'entries' => empty($options['details']) ? [] : $task->entries->map(fn ($entry) => [
                            'title' => $entry->title, 'type' => $entry->kind === 'study' ? '学习计划' : 'Project 阶段',
                            'status' => $entry->completed_at ? '已完成' : '待完成',
                            'opens' => '', 'due' => $date($entry->due_at),
                            'start' => $date($entry->starts_at), 'end' => $date($entry->ends_at),
                            'location' => $entry->location ?? '',
                            'notes' => ! empty($options['notes']) ? ($entry->notes ?? '') : '', 'url' => '',
                        ])->all(),
                    ];
                })->all(),
            ];
        })->sortBy('course', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'groups' => $groups, 'count' => $tasks->count(),
            'completed' => $tasks->whereNotNull('completed_at')->count(),
            'timezone' => $timetable->timezone, 'created' => now()->timezone($timetable->timezone)->format('Y-m-d H:i'),
            'status' => ['all' => '全部任务', 'pending' => '待完成任务', 'completed' => '已完成任务'][$options['status']],
            'scope' => $options['scope'] === 'all' ? '当前课表全部课程与独立任务' : '所选课程与任务',
        ];
    }

    public function csvRows(array $report): iterable
    {
        yield ['课程', '任务名称', '记录类型', '阶段 / 学习计划名称', '任务类型', '完成状态', '开放作答 / 提交', '提交 / 阶段截止', '考试 / 学习开始', '考试 / 学习结束', '地点', '备注', '资料链接', '时区'];
        foreach ($report['groups'] as $group) {
            foreach ($group['tasks'] as $task) {
                yield [$group['course'], $task['title'], '任务', '', $task['type'], $task['status'], $task['opens'], $task['due'], $task['start'], $task['end'], $task['location'], $task['notes'], $task['url'], $report['timezone']];
                foreach ($task['entries'] as $entry) {
                    yield [$group['course'], $task['title'], $entry['type'], $entry['title'], $task['type'], $entry['status'], '', $entry['due'], $entry['start'], $entry['end'], $entry['location'], $entry['notes'], '', $report['timezone']];
                }
            }
        }
    }

    public function csvCell(mixed $value): string
    {
        $value = (string) $value;
        // Spreadsheet applications must treat user-entered titles and notes as text.
        return preg_match('/^(?:[\t\r\n]|[\s\x{FEFF}]*[=+@-])/u', $value) ? "'".$value : $value;
    }
}
