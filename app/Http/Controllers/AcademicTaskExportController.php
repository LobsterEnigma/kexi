<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Services\AcademicTaskExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicTaskExportController extends Controller
{
    public function options(Timetable $timetable)
    {
        $this->authorize('view', $timetable);

        return response()->view('academic.export-options', [
            'timetable' => $timetable,
            'courses' => $timetable->courses()->withCount('academicTasks')->orderBy('code')->orderBy('name')->get(),
            'independentCount' => $timetable->academicTasks()->whereNull('course_id')->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, Timetable $timetable, AcademicTaskExport $export)
    {
        $this->authorize('view', $timetable);
        $options = $request->validate([
            'scope' => ['required', Rule::in(['all', 'selected'])],
            'courses' => ['nullable', 'array', 'max:500'],
            'courses.*' => ['integer', 'distinct', Rule::exists('courses', 'id')->where('timetable_id', $timetable->id)],
            'independent' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['all', 'pending', 'completed'])],
            'details' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'boolean'],
            'format' => ['required', Rule::in(['preview', 'csv'])],
        ]);
        if ($options['scope'] === 'selected' && empty($options['courses']) && empty($options['independent'])) {
            throw ValidationException::withMessages(['courses' => '请至少选择一门课程，或勾选独立任务。']);
        }
        $report = $export->build($timetable, $options);
        if ($options['format'] === 'csv') {
            return response()->streamDownload(function () use ($report, $export): void {
                $stream = fopen('php://output', 'w');
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ($export->csvRows($report) as $row) {
                    fputcsv($stream, array_map($export->csvCell(...), $row), ',', '"', '');
                }
                fclose($stream);
            }, 'kexi-tasks-'.$timetable->id.'-'.now()->format('Ymd').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->view('academic.export-preview', compact('timetable', 'report', 'options'))
            ->header('Cache-Control', 'private, no-store');
    }
}
