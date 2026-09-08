@php
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value));
    $detailFilterCount = $activeFilters->except('action')->count();
    $hasFilters = $activeFilters->isNotEmpty();
    $actions = ['' => '全部记录', 'canceled' => '取消 / 请假', 'restored' => '恢复上课', 'removed' => '移除时间段'];
    $actionIcons = ['canceled' => 'calendar-x-2', 'restored' => 'rotate-ccw', 'removed' => 'trash-2'];
    $weekdayNames = ['', '周一', '周二', '周三', '周四', '周五', '周六', '周日'];
@endphp

<x-app-layout>
    <x-slot name="title">请假与停课记录</x-slot>
    <div class="wb-records-page">
        <header class="wb-records-topbar">
            <div class="wb-records-topbar__inner">
                <a class="wb-records-brand" href="{{ route('timetables.show', $timetable) }}" aria-label="返回我的课表"><x-brand-mark /><span>{{ config('app.name', '课隙') }}</span></a>
                <span class="wb-records-breadcrumb"><span>我的课表</span><i data-lucide="chevron-right"></i>请假记录</span>
                <a class="wb-btn" href="{{ route('timetables.show', $timetable) }}"><i data-lucide="chevron-left"></i>返回课表</a>
            </div>
        </header>

        <main class="wb-records-main">
            <div class="wb-records-header">
                <div><h1>请假与停课记录</h1><p>{{ $timetable->name }}{{ $timetable->term_name ? ' · '.$timetable->term_name : '' }}</p></div>
                <label class="wb-records-term wb-field-group">
                    <span class="wb-label">课表 / 学期</span>
                    <select class="wb-select" onchange="window.location.assign(this.value)">
                        @foreach ($timetables as $option)
                            <option value="{{ route('timetables.cancellation-records', $option) }}" @selected($option->id === $timetable->id)>{{ $option->name }} {{ $option->term_name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @if ($errors->any())<div class="wb-alert" role="alert">{{ $errors->first() }}</div>@endif

            <section class="wb-records-panel" aria-label="请假与停课历史">
                <div class="wb-records-toolbar">
                    <nav class="wb-records-tabs" aria-label="记录类型">
                        @foreach ($actions as $key => $label)
                            <a href="{{ route('timetables.cancellation-records', ['timetable' => $timetable, ...collect($filters)->except('action')->all(), ...($key ? ['action' => $key] : [])]) }}" class="wb-records-tab" @if (($filters['action'] ?? '') === $key) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                    <span class="wb-records-count"><strong>{{ $records->total() }}</strong> 条记录</span>
                </div>

                <details class="wb-records-filter-panel" @if ($detailFilterCount || $errors->any()) open @endif>
                    <summary>
                        <span><i data-lucide="sliders-horizontal"></i>筛选条件 @if ($detailFilterCount)<b>{{ $detailFilterCount }}</b>@endif</span>
                        <span class="wb-records-filter-toggle"><span class="when-closed">展开</span><span class="when-open">收起</span><i data-lucide="chevron-down"></i></span>
                    </summary>
                    <form class="wb-records-filters" method="GET">
                        @if (filled($filters['action'] ?? null))<input type="hidden" name="action" value="{{ $filters['action'] }}">@endif
                        <label class="wb-field-group"><span class="wb-label">课程</span><select class="wb-select" name="course"><option value="">全部课程</option>@foreach ($courseNames as $name)<option @selected(($filters['course'] ?? '') === $name)>{{ $name }}</option>@endforeach</select></label>
                        <label class="wb-field-group"><span class="wb-label">原因</span><select class="wb-select" name="reason"><option value="">全部原因</option>@foreach ($reasons as $key => $label)<option value="{{ $key }}" @selected(($filters['reason'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label class="wb-field-group"><span class="wb-label">课程开始日期</span><input class="wb-field" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
                        <label class="wb-field-group"><span class="wb-label">课程结束日期</span><input class="wb-field" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
                        <div class="wb-records-filter-actions"><button class="wb-btn wb-btn--primary" type="submit">应用筛选</button><a class="wb-btn" href="{{ route('timetables.cancellation-records', $timetable) }}">重置</a></div>
                    </form>
                </details>

                <div class="wb-records-list">
                    @forelse ($records as $record)
                        <article class="wb-record" data-record-action="{{ $record->action }}">
                            <div class="wb-record-date" aria-label="课程日期"><span>{{ $record->occurrence_date?->format('Y / m') ?? '教学周' }}</span><strong>{{ $record->occurrence_date?->format('d') ?? $record->week_number }}</strong><small>{{ $weekdayNames[$record->weekday] }}</small></div>
                            <div class="wb-record-content">
                                <div class="wb-record-heading"><h2>{{ $record->course_name }}</h2><span class="wb-record-badge"><i data-lucide="{{ $actionIcons[$record->action] ?? 'calendar-days' }}"></i>{{ $actions[$record->action] ?? $record->action }}</span></div>
                                <p class="wb-record-meta">
                                    @if ($record->course_code)<span>{{ $record->course_code }}</span>@endif
                                    @if ($record->label)<span>{{ $record->label }}</span>@endif
                                    <span>第 {{ $record->week_number }} 周</span><span><i data-lucide="clock-3"></i>{{ substr($record->starts_at, 0, 5) }}–{{ substr($record->ends_at, 0, 5) }}</span>
                                </p>
                                <div class="wb-record-detail"><span class="wb-record-reason">{{ $reasons[$record->reason] ?? '其他' }}</span>@if ($record->note)<p class="wb-record-note">{{ $record->note }}</p>@endif</div>
                                <time class="wb-record-timestamp" datetime="{{ $record->created_at->toIso8601String() }}">操作时间 {{ $record->created_at->timezone($timetable->timezone)->format('Y/m/d H:i') }}</time>
                            </div>
                        </article>
                    @empty
                        <div class="wb-records-empty">
                            <div class="wb-records-empty__symbol" aria-hidden="true"><i data-lucide="calendar-days"></i><span><i data-lucide="{{ $hasFilters ? 'sliders-horizontal' : 'check' }}"></i></span></div>
                            <h2>{{ $hasFilters ? '没有找到符合条件的记录' : '本学期还没有请假记录' }}</h2>
                            <p>{{ $hasFilters ? '试试其他课程、原因或日期范围。' : '取消课程或恢复上课后，你的每一次安排都会留在这里。' }}</p>
                            @if ($hasFilters)<a class="wb-btn wb-btn--primary" href="{{ route('timetables.cancellation-records', $timetable) }}"><i data-lucide="rotate-ccw"></i>清除筛选</a>
                            @else<a class="wb-btn wb-btn--primary" href="{{ route('timetables.show', $timetable) }}"><i data-lucide="calendar-days"></i>查看我的课表</a>@endif
                        </div>
                    @endforelse
                </div>
                @if ($records->hasPages())<div class="wb-records-pagination">{{ $records->links() }}</div>@endif
            </section>
            <p class="wb-records-privacy"><i data-lucide="eye-off"></i>仅自己可见，请假原因和备注不会出现在分享课表中。</p>
        </main>
    </div>
</x-app-layout>
