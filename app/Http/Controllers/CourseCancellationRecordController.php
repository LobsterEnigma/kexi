<?php

namespace App\Http\Controllers;

use App\Models\CourseCancellationRecord;
use App\Models\CourseMeetingCancellation;
use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseCancellationRecordController extends Controller
{
    public function index(Request $request, Timetable $timetable): View
    {
        $this->authorize('view', $timetable);
        $reasons = ['legacy' => '历史记录（未填写原因）', ...CourseMeetingCancellation::REASONS];
        $filters = $request->validate([
            'course' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', Rule::in(array_keys($reasons))],
            'action' => ['nullable', Rule::in(['canceled', 'restored', 'removed'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $records = CourseCancellationRecord::query()->where('timetable_id', $timetable->id)
            ->when($filters['course'] ?? null, fn ($q, $value) => $q->where('course_name', $value))
            ->when($filters['reason'] ?? null, fn ($q, $value) => $q->where('reason', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('occurrence_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('occurrence_date', '<=', $value))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $courseNames = CourseCancellationRecord::query()->where('timetable_id', $timetable->id)
            ->distinct()->orderBy('course_name')->pluck('course_name');
        $timetables = $request->user()->timetables()->get();

        return view('timetables.cancellation-records', compact('timetable', 'timetables', 'records', 'reasons', 'courseNames', 'filters'));
    }
}
