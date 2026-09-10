<x-academic-shell :timetable="$timetable" title="学业任务">
    <x-slot name="actions"><div class="academic-heading-actions"><a class="wb-btn" href="{{ route('academic-tasks.export',$timetable) }}"><i data-lucide="download"></i>导出任务</a><a class="wb-btn wb-btn--primary" href="{{ route('academic-tasks.create',$timetable) }}"><i data-lucide="plus"></i>新建任务</a></div></x-slot>
    @if(!$timetable->term_start_date)<p class="academic-hint">课表尚未设置开学日期。你可以先管理任务，补全课表设置后即可在周/月日历中对照查看。</p>@endif
    <div class="academic-stats academic-stats--compact">
        @foreach(['pending'=>'待完成','overdue'=>'已逾期','completed'=>'已完成'] as $key=>$label)
            <a href="{{ route('academic-tasks.index',['timetable'=>$timetable,...collect($filters)->except('status')->all(),'status'=>$key]) }}" class="academic-stat {{ $key==='overdue' && $stats[$key] ? 'academic-stat--urgent' : '' }}"><span>{{ $label }}</span><strong>{{ $stats[$key] }}</strong></a>
        @endforeach
    </div>
    <section class="wb-records-panel academic-task-index">
        <nav class="academic-tabs" aria-label="任务状态">
            @foreach(['pending'=>'待完成','week'=>'未来 7 天截止','overdue'=>'已逾期','completed'=>'已完成','all'=>'全部'] as $key=>$label)<a href="{{ route('academic-tasks.index',['timetable'=>$timetable,...collect($filters)->except('status')->all(),'status'=>$key]) }}" @if($status===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
        </nav>
        <div class="academic-list-toolbar">
            <p class="academic-list-count">共 <strong>{{ $tasks->total() }}</strong> 项@if($tasks->total())<span> · 当前 {{ $tasks->firstItem() ?? 0 }}–{{ $tasks->lastItem() ?? 0 }} 项</span>@endif</p>
        </div>
        <details class="academic-filter-disclosure">
            <summary><span><i data-lucide="sliders-horizontal"></i>筛选条件 @if($activeFilters->isNotEmpty())<b>{{ $activeFilters->count() }}</b>@endif</span><span class="academic-filter-toggle"><span class="academic-filter-show">展开</span><span class="academic-filter-hide">收起</span><i data-lucide="chevron-down"></i></span></summary>
            <form class="academic-filter" method="GET">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <label class="wb-field-group"><span class="wb-label">搜索名称</span><input class="wb-field" name="q" value="{{ $filters['q']??'' }}" placeholder="作业、测验、项目…" maxlength="160"></label>
            <label class="wb-field-group"><span class="wb-label">课程</span><select class="wb-select" name="course"><option value="">全部课程</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(($filters['course']??'')==$course->id)>{{ $course->code ? $course->code.' · ' : '' }}{{ $course->name }}</option>@endforeach</select></label>
            <label class="wb-field-group"><span class="wb-label">类型</span><select class="wb-select" name="type"><option value="">全部类型</option>@foreach(\App\Models\AcademicTask::TYPES as $key=>$label)<option value="{{ $key }}" @selected(($filters['type']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
            <label class="wb-field-group"><span class="wb-label">安排日期</span><input class="wb-field" type="date" name="date" value="{{ $filters['date']??'' }}"></label>
            <div class="academic-filter__actions"><button class="wb-btn" type="submit">应用筛选</button><a class="wb-btn" href="{{ route('academic-tasks.index',['timetable'=>$timetable,'status'=>$status,'sort'=>$sort,'per_page'=>$perPage]) }}">重置</a></div>
            </form>
        </details>
        @if($activeFilters->isNotEmpty())<div class="academic-active-filters" aria-label="已应用的筛选条件">@foreach($activeFilters as $label)<span>{{ $label }}</span>@endforeach<a href="{{ route('academic-tasks.index',['timetable'=>$timetable,'status'=>$status,'sort'=>$sort,'per_page'=>$perPage]) }}">清除筛选</a></div>@endif
        <div class="academic-list">
            @forelse($tasks as $task)
                @php
                    $milestones = $task->entries->where('kind', 'milestone');
                    $scheduledStart = $task->starts_at?->timezone($timetable->timezone);
                    $scheduledEnd = $task->ends_at?->timezone($timetable->timezone);
                    $crossYear = $scheduledStart && $scheduledEnd && $scheduledStart->year !== $scheduledEnd->year;
                @endphp
                <article class="academic-task {{ $task->completed_at ? 'is-complete' : '' }}" style="--task-color: {{ $task->color() }}" data-task-id="{{ $task->id }}">
                    <form action="{{ route('academic-tasks.complete',[$timetable,$task]) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="completed" value="{{ $task->completed_at ? 0 : 1 }}"><button class="academic-check" type="submit" aria-label="{{ $task->completed_at ? '重新打开' : '完成' }} {{ $task->title }}" aria-pressed="{{ $task->completed_at?'true':'false' }}">@if($task->completed_at)<i data-lucide="check"></i>@endif</button></form>
                    <a class="academic-task__body" href="{{ route('academic-tasks.edit',[$timetable,$task]) }}">
                        <div class="academic-task__tags"><span>{{ \App\Models\AcademicTask::TYPES[$task->type] }}</span><span>{{ $task->course?->code ?: $task->course?->name ?: '独立任务' }}</span>@if($task->completed_at)<span>已完成</span>@elseif($task->due_at?->isPast())<span class="academic-error">已逾期</span>@endif</div>
                        <h2>{{ $task->title }}</h2>
                        <div class="academic-task__meta">
                            @if($task->due_at)<span><i data-lucide="calendar-days"></i>提交截止 {{ $task->due_at->timezone($timetable->timezone)->format('n/j H:i') }}</span>@endif
                            @if($task->opens_at)<span>开放作答 / 提交 {{ $task->opens_at->timezone($timetable->timezone)->format('n/j H:i') }}</span>@endif
                            @if($scheduledStart)<span><i data-lucide="clock-3"></i>考试 / 活动 {{ $scheduledStart->format($crossYear ? 'Y/n/j H:i' : 'n/j H:i') }}@if($scheduledEnd)–{{ $scheduledEnd->format($scheduledStart->isSameDay($scheduledEnd) ? 'H:i' : ($crossYear ? 'Y/n/j H:i' : 'n/j H:i')) }}@endif</span>@endif
                            @if(!$task->due_at && !$task->opens_at && !$scheduledStart && $task->entries->isEmpty())<span>时间待安排</span>@endif
                            @if($task->entries->where('kind','study')->count())<span>{{ $task->entries->where('kind','study')->count() }} 次学习计划</span>@endif
                        </div>
                        @if($milestones->isNotEmpty())<div class="academic-progress"><progress value="{{ $milestones->whereNotNull('completed_at')->count() }}" max="{{ $milestones->count() }}" aria-label="项目阶段进度"></progress><span>{{ $milestones->whereNotNull('completed_at')->count() }}/{{ $milestones->count() }} 阶段完成</span></div>@endif
                    </a><a class="wb-icon-btn" href="{{ route('academic-tasks.edit',[$timetable,$task]) }}" aria-label="查看 {{ $task->title }}"><i data-lucide="chevron-right"></i></a>
                </article>
            @empty
                <div class="wb-records-empty"><div class="wb-records-empty__symbol"><i data-lucide="file-text"></i></div>@if($activeFilters->isNotEmpty())<h2>没有符合筛选条件的任务</h2><p>试试更换课程、类型或日期，或清除筛选后查看。</p><a class="wb-btn" href="{{ route('academic-tasks.index',['timetable'=>$timetable,'status'=>$status,'sort'=>$sort,'per_page'=>$perPage]) }}">清除筛选</a>@else<h2>当前分类暂无任务</h2><p>可以切换到「全部」查看其他状态的任务，或创建一项新任务。</p><a class="wb-btn wb-btn--primary" href="{{ route('academic-tasks.create',$timetable) }}">创建学业任务</a>@endif</div>
            @endforelse
        </div>
        <footer class="academic-list-footer" aria-label="任务列表显示与翻页">
            <form method="GET" class="academic-list-settings" x-data="{ helpOpen: false }" x-on:change="$el.requestSubmit()" x-on:keydown.escape="if (helpOpen) { $event.preventDefault(); $event.stopPropagation(); helpOpen = false; $refs.sortHelp.focus({ preventScroll: true }); }">
                <input type="hidden" name="status" value="{{ $status }}">
                @foreach(collect($filters)->only(['q','course','type','date'])->filter(fn($value) => filled($value)) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <div class="academic-sort-control">
                    <label><span>排序</span><select name="sort" aria-label="任务排序">@foreach(['time_asc'=>'时间：早 → 晚','time_desc'=>'时间：晚 → 早','newest'=>'最近创建'] as $key=>$label)<option value="{{ $key }}" @selected($sort===$key)>{{ $label }}</option>@endforeach</select></label>
                    <div class="academic-sort-help" x-on:click.outside="helpOpen = false">
                        <button type="button" x-ref="sortHelp" class="academic-sort-help__button" aria-label="查看排序规则" aria-controls="academic-sort-rules" x-bind:aria-expanded="helpOpen" x-on:click="helpOpen = !helpOpen"><i data-lucide="circle-help"></i></button>
                        <div id="academic-sort-rules" class="academic-sort-help__panel" role="note" x-show="helpOpen" x-cloak><strong>排序规则</strong><p>{{ $sort==='newest' ? '按创建时间由新到旧排列。' : '按提交截止时间排列；没有截止时间时，使用考试 / 活动开始时间，再使用开放作答 / 提交时间。都未填写的任务排在最后。' }}</p></div>
                    </div>
                </div>
                <label class="academic-page-size"><span>每页</span><select name="per_page" aria-label="每页任务数">@foreach([10,20,50] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }} 项</option>@endforeach</select></label>
                <noscript><button class="wb-btn" type="submit">应用</button></noscript>
            </form>
            <nav class="wb-records-pagination academic-list-pagination" aria-label="任务分页">
                @if($tasks->onFirstPage())<span class="academic-page-button is-disabled" aria-disabled="true" aria-label="上一页"><i data-lucide="chevron-left"></i></span>@else<a class="academic-page-button" href="{{ $tasks->previousPageUrl() }}" rel="prev" aria-label="上一页"><i data-lucide="chevron-left"></i></a>@endif
                <span class="academic-page-position" aria-current="page">{{ $tasks->currentPage() }} / {{ $tasks->lastPage() }}<span class="sr-only"> 页</span></span>
                @if($tasks->hasMorePages())<a class="academic-page-button" href="{{ $tasks->nextPageUrl() }}" rel="next" aria-label="下一页"><i data-lucide="chevron-right"></i></a>@else<span class="academic-page-button is-disabled" aria-disabled="true" aria-label="下一页"><i data-lucide="chevron-right"></i></span>@endif
            </nav>
        </footer>
    </section>
</x-academic-shell>
