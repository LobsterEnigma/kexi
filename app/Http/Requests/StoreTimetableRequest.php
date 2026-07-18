<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'term_name' => ['nullable', 'string', 'max:100'],
            'term_start_date' => ['nullable', 'date'],
            'term_end_date' => ['nullable', 'date', 'after_or_equal:term_start_date', 'required_without:week_count'],
            'week_count' => ['nullable', 'required_without:term_end_date', 'integer', 'between:1,30'],
            'timezone' => ['nullable', 'timezone:all'],
            'near_threshold_minutes' => ['required', 'integer', Rule::in(config('kexi.schedule.near_thresholds'))],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $start = $this->dateValue('term_start_date');
            $end = $this->dateValue('term_end_date');
            $weeks = $this->integer('week_count');

            if ($end && ! $start) {
                $validator->errors()->add('term_start_date', '填写学期截止日期前，请先填写开学日期。');

                return;
            }

            if (! $start || ! $end) {
                return;
            }

            $calculatedWeeks = $this->weeksBetween($start, $end);
            if ($calculatedWeeks > 30) {
                $validator->errors()->add('term_end_date', '学期范围最长为 30 周。');
            }

            if ($weeks > 0 && $weeks !== $calculatedWeeks) {
                $validator->errors()->add('week_count', '总周数与开学、截止日期不一致。');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function normalized(): array
    {
        $data = $this->validated();
        $start = $this->dateValue('term_start_date');
        $end = $this->dateValue('term_end_date');
        $weeks = isset($data['week_count']) ? (int) $data['week_count'] : null;

        if (! $start) {
            $data['term_end_date'] = null;
            $data['week_count'] = $weeks ?? 18;

            return $data;
        }

        if ($end) {
            $data['term_end_date'] = $end->toDateString();
            $data['week_count'] = $this->weeksBetween($start, $end);

            return $data;
        }

        $resolvedWeeks = $weeks ?? 18;
        $data['week_count'] = $resolvedWeeks;
        $data['term_end_date'] = $start->addDays(($resolvedWeeks * 7) - 1)->toDateString();

        return $data;
    }

    private function dateValue(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        return filled($value) ? CarbonImmutable::parse((string) $value)->startOfDay() : null;
    }

    private function weeksBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) ceil(($start->diffInDays($end) + 1) / 7);
    }
}
