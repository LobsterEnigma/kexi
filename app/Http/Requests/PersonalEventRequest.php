<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('all_day')) {
            foreach (['start', 'end'] as $boundary) {
                if ($this->exists($boundary.'_local')) {
                    $value = $this->input($boundary.'_local');
                    $valid = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value);
                    $this->merge([
                        $boundary.'_date' => $valid ? substr($value, 0, 10) : null,
                        $boundary.'_time' => $valid ? substr($value, 11, 5) : null,
                    ]);
                }
            }
        }
        if ($this->input('scope') === 'one' || $this->input('repeat') === 'none') {
            $this->merge(['repeat' => 'none', 'weekdays' => null, 'repeat_until' => null]);
        }
        if ($this->boolean('all_day')) {
            $this->merge(['start_time' => null, 'end_time' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'], 'category' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'timezone' => ['required', 'timezone:all'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_unless:all_day,1', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_unless:all_day,1', 'date_format:H:i'],
            'all_day' => ['required', 'boolean'], 'location' => ['nullable', 'string', 'max:200'], 'notes' => ['nullable', 'string', 'max:4000'],
            'repeat' => ['required', Rule::in(['none', 'weekly'])],
            'weekdays' => ['nullable', 'required_if:repeat,weekly', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'repeat_until' => ['nullable', 'required_if:repeat,weekly', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'scope' => ['nullable', Rule::in(['all', 'one', 'future'])], 'occurrence' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return ['start_date' => '开始日期', 'end_date' => '结束日期', 'start_time' => '开始时间', 'end_time' => '结束时间', 'timezone' => '活动时区'];
    }

    public function messages(): array
    {
        return ['end_date.after_or_equal' => '结束日期不能早于开始日期，请检查完整的起止日期与时间。'];
    }

    public function normalized(): array
    {
        $data = $this->validated();
        if (($data['scope'] ?? null) === 'future' && $data['start_date'] < ($data['occurrence'] ?? '')) {
            throw ValidationException::withMessages(['start_date' => '后续安排的开始日期不能早于所选的这一次。']);
        }
        $allDay = $this->boolean('all_day');
        $startValue = $data['start_date'].' '.($allDay ? '00:00' : $data['start_time']);
        $endValue = $data['end_date'].' '.($allDay ? '00:00' : $data['end_time']);
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $startValue, $data['timezone']);
        $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $endValue, $data['timezone']);
        if ($start->format('Y-m-d H:i') !== $startValue || $end->format('Y-m-d H:i') !== $endValue) {
            throw ValidationException::withMessages(['start_time' => '这个时间在所选时区不存在，请检查夏令时切换日期。']);
        }
        if ($allDay) {
            $end = $end->addDay();
        }
        if ($end->lte($start) || $end->gt($start->addDays(7))) {
            throw ValidationException::withMessages(['end_time' => '结束时间必须晚于开始时间，每次活动最长 7 天。']);
        }
        $days = array_map('intval', $data['weekdays'] ?? []);
        if ($data['repeat'] === 'weekly') {
            if (! in_array($start->isoWeekday(), $days, true)) {
                throw ValidationException::withMessages(['weekdays' => '重复星期需要包含首次活动的星期。']);
            }
            if (CarbonImmutable::parse($data['repeat_until'])->gt(CarbonImmutable::parse($data['start_date'])->addYears(2))) {
                throw ValidationException::withMessages(['repeat_until' => '重复结束日期最多设置到首次活动的两年后。']);
            }
        }

        return array_merge(collect($data)->only(['title', 'category', 'color', 'timezone', 'location', 'notes', 'repeat'])->all(), [
            'starts_at' => $start->utc()->format('Y-m-d H:i:s'), 'ends_at' => $end->utc()->format('Y-m-d H:i:s'), 'all_day' => $allDay,
            'weekdays' => $data['repeat'] === 'weekly' ? $days : null, 'repeat_until' => $data['repeat'] === 'weekly' ? $data['repeat_until'] : null,
        ]);
    }
}
