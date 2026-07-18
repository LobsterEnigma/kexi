<?php

namespace App\Http\Requests;

use App\Enums\WeekMode;
use App\Models\Timetable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $meetings = collect($this->input('meetings', []))->map(function ($meeting): array {
            $weeks = $meeting['specific_weeks'] ?? null;
            if (is_string($weeks)) {
                $weeks = preg_split('/[\s,，、]+/u', trim($weeks), -1, PREG_SPLIT_NO_EMPTY);
            }

            if (is_array($weeks)) {
                $weeks = collect($weeks)->map(fn ($week) => (int) $week)->unique()->sort()->values()->all();
            }

            $meeting['specific_weeks'] = $weeks;

            return $meeting;
        })->all();

        $this->merge(['meetings' => $meetings]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meetings' => ['required', 'array', 'min:1', 'max:20'],
            'meetings.*.label' => ['nullable', 'string', 'max:40'],
            'meetings.*.teacher' => ['nullable', 'string', 'max:80'],
            'meetings.*.weekday' => ['required', 'integer', 'between:1,7'],
            'meetings.*.starts_at' => ['required', 'date_format:H:i'],
            'meetings.*.ends_at' => ['required', 'date_format:H:i'],
            'meetings.*.location' => ['nullable', 'string', 'max:120'],
            'meetings.*.week_mode' => ['required', Rule::enum(WeekMode::class)],
            'meetings.*.start_week' => ['nullable', 'integer', 'between:1,30'],
            'meetings.*.end_week' => ['nullable', 'integer', 'between:1,30'],
            'meetings.*.specific_weeks' => ['nullable', 'array', 'max:30'],
            'meetings.*.specific_weeks.*' => ['integer', 'between:1,30'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $timetable = $this->route('timetable');
            if (! $timetable instanceof Timetable) {
                return;
            }

            $fingerprints = [];
            foreach ($this->input('meetings', []) as $index => $meeting) {
                $path = "meetings.{$index}";
                if (($meeting['ends_at'] ?? '') <= ($meeting['starts_at'] ?? '')) {
                    $validator->errors()->add("{$path}.ends_at", '结束时间必须晚于开始时间。');
                }

                $mode = $meeting['week_mode'] ?? null;
                if ($mode === WeekMode::Specific->value) {
                    $weeks = $meeting['specific_weeks'] ?? [];
                    if ($weeks === []) {
                        $validator->errors()->add("{$path}.specific_weeks", '指定周次不能为空。');
                    }
                    foreach ($weeks as $week) {
                        if ($week > $timetable->week_count) {
                            $validator->errors()->add("{$path}.specific_weeks", "周次不能超过第 {$timetable->week_count} 周。");
                            break;
                        }
                    }
                } else {
                    $start = (int) ($meeting['start_week'] ?? 0);
                    $end = (int) ($meeting['end_week'] ?? 0);
                    if ($start < 1 || $end < $start || $end > $timetable->week_count) {
                        $validator->errors()->add("{$path}.end_week", "周次范围必须在 1 至 {$timetable->week_count} 周内。");
                    } elseif ($mode === WeekMode::Odd->value && $this->parityRangeIsEmpty($start, $end, 1)) {
                        $validator->errors()->add("{$path}.week_mode", '该范围内没有单周。');
                    } elseif ($mode === WeekMode::Even->value && $this->parityRangeIsEmpty($start, $end, 0)) {
                        $validator->errors()->add("{$path}.week_mode", '该范围内没有双周。');
                    }
                }

                $fingerprint = implode('|', [
                    $meeting['weekday'] ?? '',
                    $meeting['starts_at'] ?? '',
                    $meeting['ends_at'] ?? '',
                    $mode,
                    $meeting['start_week'] ?? '',
                    $meeting['end_week'] ?? '',
                    implode(',', $meeting['specific_weeks'] ?? []),
                ]);
                if (isset($fingerprints[$fingerprint])) {
                    $validator->errors()->add($path, '同一课程不能添加完全重复的时间段。');
                }
                $fingerprints[$fingerprint] = true;
            }
        }];
    }

    /** @return array<int, array<string, mixed>> */
    public function normalizedMeetings(): array
    {
        return collect($this->validated('meetings'))->map(function (array $meeting, int $index): array {
            $specific = $meeting['week_mode'] === WeekMode::Specific->value;
            $meeting['start_week'] = $specific ? null : (int) $meeting['start_week'];
            $meeting['end_week'] = $specific ? null : (int) $meeting['end_week'];
            $meeting['specific_weeks'] = $specific ? $meeting['specific_weeks'] : null;
            $meeting['sort_order'] = $index;

            return $meeting;
        })->all();
    }

    private function parityRangeIsEmpty(int $start, int $end, int $parity): bool
    {
        for ($week = $start; $week <= $end; $week++) {
            if ($week % 2 === $parity) {
                return false;
            }
        }

        return true;
    }
}
