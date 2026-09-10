<?php

namespace App\Http\Requests;

use App\Models\AcademicTask;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->can('update', $this->route('timetable')) || abort(404);
        $task = $this->route('task');
        if ($task && (int) $task->timetable_id !== (int) $this->route('timetable')->id) {
            abort(404);
        }
        $entry = $this->route('entry');
        if ($entry && (int) $entry->academic_task_id !== (int) $task?->id) {
            abort(404);
        }

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:200'],
            'reminder_minutes' => ['nullable', 'integer', Rule::in(array_keys(AcademicTask::REMINDERS))],
        ];
        if ($this->isEntry()) {
            $rules['kind'] = ['required', Rule::in(['milestone', 'study'])];
        } else {
            $rules['type'] = ['required', Rule::in(array_keys(AcademicTask::TYPES))];
            $rules['course_id'] = ['nullable', 'integer', Rule::exists('courses', 'id')->where('timetable_id', $this->route('timetable')->id)];
            $rules['url'] = ['nullable', 'url:http,https', 'max:2000'];
            $rules['opens_at'] = ['nullable', 'date_format:Y-m-d\TH:i'];
        }
        foreach (['starts_at', 'ends_at', 'due_at'] as $key) {
            $rules[$key] = ['nullable', 'date_format:Y-m-d\TH:i'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['title' => '名称', 'type' => '任务类型', 'course_id' => '关联课程', 'opens_at' => '开放时间', 'due_at' => '截止时间', 'starts_at' => '开始时间', 'ends_at' => '结束时间', 'reminder_minutes' => '提醒时间', 'url' => '相关链接'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $dates = [];
            foreach (['opens_at', 'due_at', 'starts_at', 'ends_at'] as $key) {
                if (! $this->filled($key)) {
                    $dates[$key] = null;

                    continue;
                }
                try {
                    $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->input($key), $this->route('timetable')->timezone);
                    if ($date->format('Y-m-d\TH:i') !== $this->input($key) || $date->year < 2000 || $date->year > 2100) {
                        throw new \RuntimeException;
                    }
                    $dates[$key] = $date;
                } catch (\Throwable) {
                    $validator->errors()->add($key, '请输入 2000–2100 年内有效的当地时间（注意夏令时跳时）。');
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($dates['opens_at'] && $dates['due_at'] && $dates['opens_at']->gte($dates['due_at'])) {
                $validator->errors()->add('due_at', '截止时间必须晚于开放时间。');
            }
            if ((bool) $dates['starts_at'] !== (bool) $dates['ends_at']) {
                $validator->errors()->add('ends_at', '请同时填写开始时间和结束时间。');
            }
            if ($dates['starts_at'] && $dates['ends_at'] && ($dates['ends_at']->lte($dates['starts_at']) || $dates['starts_at']->diffInDays($dates['ends_at']) > 7)) {
                $validator->errors()->add('ends_at', '结束时间应晚于开始时间，单次安排最长 7 天。');
            }
            if ($this->isEntry()) {
                if ($this->input('kind') === 'milestone') {
                    if (! $dates['due_at']) {
                        $validator->errors()->add('due_at', '请填写阶段截止时间。');
                    }
                    if ($this->route('task')->type !== 'project') {
                        $validator->errors()->add('kind', '只有 Project 可以添加项目阶段。');
                    }
                } elseif (! $dates['starts_at'] || ! $dates['ends_at']) {
                    $validator->errors()->add('starts_at', '请填写学习计划的开始和结束时间。');
                }
            } elseif ($this->route('task')?->entries()->where('kind', 'milestone')->exists() && $this->input('type') !== 'project') {
                $validator->errors()->add('type', '此 Project 仍有项目阶段，请先移除阶段再更改类型。');
            }
        }];
    }

    public function normalized(): array
    {
        $data = $this->validated();
        foreach (['opens_at', 'due_at', 'starts_at', 'ends_at'] as $key) {
            if ($this->isEntry() && $key === 'opens_at') {
                continue;
            }
            $data[$key] = filled($data[$key] ?? null) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$key], $this->route('timetable')->timezone)->utc() : null;
        }
        if ($this->isEntry()) {
            if ($data['kind'] === 'study') {
                $data['due_at'] = null;
            } else {
                $data['starts_at'] = null;
                $data['ends_at'] = null;
            }
        }
        $data['reminder_minutes'] = isset($data['reminder_minutes']) ? (int) $data['reminder_minutes'] : null;

        return $data;
    }

    private function isEntry(): bool
    {
        return $this->routeIs('academic-entries.*');
    }
}
