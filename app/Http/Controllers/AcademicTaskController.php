<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicTaskRequest;
use App\Models\AcademicTask;
use App\Models\AcademicTaskEntry;
use App\Models\Timetable;
use App\Services\AcademicPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicTaskController extends Controller
{
    public function index(Request $request, Timetable $timetable, AcademicPlanner $planner)
    {
        $this->authorize('view', $timetable);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'completed', 'overdue', 'week', 'all'])],
            'type' => ['nullable', Rule::in(array_keys(AcademicTask::TYPES))],
            'course' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:160'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['time_asc', 'time_desc', 'newest'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
        $status = $filters['status'] ?? 'pending';
        $now = CarbonImmutable::now('UTC');
        $base = $timetable->academicTasks();
        $stats = ['pending' => (clone $base)->whereNull('completed_at')->count(), 'completed' => (clone $base)->whereNotNull('completed_at')->count(), 'overdue' => (clone $base)->whereNull('completed_at')->where('due_at', '<', $now)->count()];
        $query = (clone $base)->with(['course', 'entries']);
        if ($status === 'completed') {
            $query->whereNotNull('completed_at');
        } elseif ($status !== 'all') {
            $query->whereNull('completed_at');
        }
        if ($status === 'overdue') {
            $query->where('due_at', '<', $now);
        }
        if ($status === 'week') {
            $query->whereBetween('due_at', [$now, $now->timezone($timetable->timezone)->addDays(7)->endOfDay()->utc()]);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['course'] ?? null)) {
            $query->where('course_id', $filters['course']);
        }
        if (filled($filters['q'] ?? null)) {
            $query->where('title', 'like', '%'.$filters['q'].'%');
        }
        if (filled($filters['date'] ?? null)) {
            $date = CarbonImmutable::parse($filters['date'], $timetable->timezone);
            $day = $planner->calendar($timetable, $date, $date)[$date->toDateString()] ?? [];
            $query->whereIn('id', collect([...($day['banners'] ?? []), ...($day['timed'] ?? [])])->pluck('task_id')->unique());
        }
        $sort = $filters['sort'] ?? 'time_asc';
        $perPage = (int) ($filters['per_page'] ?? 10);
        if ($sort === 'newest') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderByRaw('COALESCE(due_at, starts_at, opens_at) IS NULL')
                ->orderByRaw('COALESCE(due_at, starts_at, opens_at) '.($sort === 'time_desc' ? 'DESC' : 'ASC'))
                ->orderBy('id');
        }
        $tasks = $query->paginate($perPage)->withQueryString();
        if ($tasks->currentPage() > $tasks->lastPage()) {
            return redirect()->to($tasks->url($tasks->lastPage()));
        }
        $courses = $timetable->courses()->get();

        $activeFilters = collect();
        if (filled($filters['q'] ?? null)) {
            $activeFilters->push('名称：'.$filters['q']);
        }
        if (filled($filters['course'] ?? null)) {
            $course = $courses->firstWhere('id', $filters['course']);
            $activeFilters->push('课程：'.($course ? ($course->code ?: $course->name) : '未找到'));
        }
        if (filled($filters['type'] ?? null)) {
            $activeFilters->push('类型：'.AcademicTask::TYPES[$filters['type']]);
        }
        if (filled($filters['date'] ?? null)) {
            $activeFilters->push('日期：'.$filters['date']);
        }

        return view('academic.index', compact('timetable', 'tasks', 'filters', 'status', 'stats', 'courses', 'sort', 'perPage', 'activeFilters'));
    }

    public function create(Timetable $timetable)
    {
        $this->authorize('update', $timetable);

        return view('academic.edit', ['timetable' => $timetable, 'task' => new AcademicTask(['type' => 'assignment', 'reminder_minutes' => 1440]), 'courses' => $timetable->courses()->get()]);
    }

    public function store(AcademicTaskRequest $request, Timetable $timetable)
    {
        $task = $timetable->academicTasks()->create($request->normalized());

        return redirect()->route('academic-tasks.edit', [$timetable, $task])->with('status', '任务已创建，可以继续添加学习计划和项目阶段。');
    }

    public function edit(Timetable $timetable, AcademicTask $task)
    {
        $this->check($timetable, $task);
        $task->load(['course', 'entries']);

        return view('academic.edit', ['timetable' => $timetable, 'task' => $task, 'courses' => $timetable->courses()->get()]);
    }

    public function update(AcademicTaskRequest $request, Timetable $timetable, AcademicTask $task)
    {
        $task->fill($request->normalized());
        if ($task->isDirty(['due_at', 'starts_at', 'reminder_minutes'])) {
            $task->reminder_ack_at = null;
        }
        $task->save();

        return redirect()->route('academic-tasks.edit', [$timetable, $task])->with('status', '任务已保存。');
    }

    public function complete(Request $request, Timetable $timetable, AcademicTask $task)
    {
        $this->check($timetable, $task);
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $task->update(['completed_at' => $data['completed'] ? ($task->completed_at ?? now('UTC')) : null, 'reminder_ack_at' => null]);

        return back()->with('status', $data['completed'] ? '任务已完成，相关提醒已停止。' : '任务已重新打开。');
    }

    public function destroy(Timetable $timetable, AcademicTask $task)
    {
        $this->check($timetable, $task);
        $task->delete();

        return redirect()->route('academic-tasks.index', $timetable)->with('status', '任务及其阶段、学习计划已删除。');
    }

    public function storeEntry(AcademicTaskRequest $request, Timetable $timetable, AcademicTask $task)
    {
        $entry = $task->entries()->create($request->normalized());

        return redirect()->to(route('academic-tasks.edit', [$timetable, $task]).'#entry-'.$entry->id)->with('status', '安排已添加。');
    }

    public function updateEntry(AcademicTaskRequest $request, Timetable $timetable, AcademicTask $task, AcademicTaskEntry $entry)
    {
        $this->check($timetable, $task, $entry);
        $entry->fill($request->normalized());
        if ($entry->isDirty(['due_at', 'starts_at', 'reminder_minutes'])) {
            $entry->reminder_ack_at = null;
        }
        $entry->save();

        return redirect()->to(route('academic-tasks.edit', [$timetable, $task]).'#entry-'.$entry->id)->with('status', '安排已保存。');
    }

    public function completeEntry(Request $request, Timetable $timetable, AcademicTask $task, AcademicTaskEntry $entry)
    {
        $this->check($timetable, $task, $entry);
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $entry->update(['completed_at' => $data['completed'] ? ($entry->completed_at ?? now('UTC')) : null, 'reminder_ack_at' => null]);

        return back()->with('status', '完成状态已更新。');
    }

    public function destroyEntry(Timetable $timetable, AcademicTask $task, AcademicTaskEntry $entry)
    {
        $this->check($timetable, $task, $entry);
        $entry->delete();

        return back()->with('status', '安排已删除。');
    }

    public function reminders(Timetable $timetable, AcademicPlanner $planner)
    {
        $this->authorize('view', $timetable);

        return response()->json(['items' => $planner->reminders($timetable)->map(fn ($item) => collect($item)->except('model')->all())])->header('Cache-Control', 'no-store');
    }

    public function acknowledge(Request $request, Timetable $timetable, AcademicPlanner $planner)
    {
        $this->authorize('update', $timetable);
        $data = $request->validate(['key' => ['required', 'string', 'max:100']]);
        $reminder = $planner->reminders($timetable)->firstWhere('key', $data['key']);
        if ($reminder) {
            $reminder['model']->update(['reminder_ack_at' => now('UTC')]);
        }

        return response()->json(['ok' => true]);
    }

    private function check(Timetable $timetable, AcademicTask $task, ?AcademicTaskEntry $entry = null): void
    {
        $this->authorize('update', $timetable);
        abort_unless((int) $task->timetable_id === (int) $timetable->id, 404);
        if ($entry) {
            abort_unless((int) $entry->academic_task_id === (int) $task->id, 404);
        }
    }
}
