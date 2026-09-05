<x-app-layout>
    <x-slot name="title">请假与停课记录</x-slot>
    <div class="wb-records-page">
        <header class="wb-records-header">
            <a class="wb-btn" href="{{ route('timetables.show', $timetable) }}"><i data-lucide="chevron-left"></i>返回课表</a>
            <div><h1>请假与停课记录</h1><p>{{ $timetable->name }}{{ $timetable->term_name ? ' · '.$timetable->term_name : '' }}</p></div>
            <label class="wb-field-group">
                <span class="wb-label">课表 / 学期</span>
                <select class="wb-select" onchange="window.location.assign(this.value)">
                    @foreach ($timetables as $option)
                        <option value="{{ route('timetables.cancellation-records', $option) }}" @selected($option->id === $timetable->id)>{{ $option->name }} {{ $option->term_name }}</option>
                    @endforeach
                </select>
            </label>
        </header>
        <main class="wb-records-main">
            @if ($errors->any())<div class="wb-alert" role="alert">{{ $errors->first() }}</div>@endif
            <form class="wb-records-filters" method="GET">
                <label class="wb-field-group"><span class="wb-label">课程</span><select class="wb-select" name="course"><option value="">全部课程</option>@foreach ($courseNames as $name)<option @selected(($filters['course'] ?? '') === $name)>{{ $name }}</option>@endforeach</select></label>
                <label class="wb-field-group"><span class="wb-label">原因</span><select class="wb-select" name="reason"><option value="">全部原因</option>@foreach ($reasons as $key => $label)<option value="{{ $key }}" @selected(($filters['reason'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="wb-field-group"><span class="wb-label">操作</span><select class="wb-select" name="action"><option value="">全部操作</option>@foreach (['canceled' => '取消 / 请假', 'restored' => '恢复上课', 'removed' => '移除时间段'] as $key => $label)<option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="wb-field-group"><span class="wb-label">课程日期从</span><input class="wb-field" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
                <label class="wb-field-group"><span class="wb-label">至</span><input class="wb-field" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
                <div class="flex gap-2"><button class="wb-btn wb-btn--primary" type="submit"><i data-lucide="sliders-horizontal"></i>筛选</button><a class="wb-icon-btn" href="{{ route('timetables.cancellation-records', $timetable) }}" title="重置筛选" aria-label="重置筛选"><i data-lucide="rotate-ccw"></i></a></div>
            </form>
            <p class="wb-records-count">{{ $records->total() }} 条记录</p>
            <div class="wb-records-list">
                @forelse ($records as $record)
                    <article class="wb-record" data-record-action="{{ $record->action }}">
                        <div class="wb-record-date"><strong>{{ $record->occurrence_date?->format('Y/m/d') ?? '第 '.$record->week_number.' 周' }}</strong><span>{{ ['','周一','周二','周三','周四','周五','周六','周日'][$record->weekday] }} · {{ substr($record->starts_at, 0, 5) }}–{{ substr($record->ends_at, 0, 5) }}</span></div>
                        <div class="min-w-0"><h2>{{ $record->course_name }}</h2><p>{{ implode(' · ', array_filter([$record->course_code, $record->label, '第 '.$record->week_number.' 周'])) }}</p><div class="wb-record-reason">{{ $reasons[$record->reason] ?? '其他' }}</div>@if ($record->note)<p class="wb-record-note">{{ $record->note }}</p>@endif</div>
                        <div class="wb-record-status"><span>{{ ['canceled' => '已取消 / 请假', 'restored' => '已恢复上课', 'removed' => '已移除时间段'][$record->action] ?? $record->action }}</span><time>{{ $record->created_at->timezone($timetable->timezone)->format('Y/m/d H:i') }}</time></div>
                    </article>
                @empty
                    <div class="wb-records-empty"><i data-lucide="calendar-days"></i><p>暂无符合条件的请假或停课记录</p></div>
                @endforelse
            </div>
            <div class="mt-6">{{ $records->links() }}</div>
        </main>
    </div>
</x-app-layout>
