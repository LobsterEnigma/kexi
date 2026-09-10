<x-personal-shell :timetable="$timetable" title="个人安排">
    <x-slot name="actions"><a class="wb-btn wb-btn--primary" href="{{ route('personal-events.create', ['timetable'=>$timetable?->id]) }}"><i data-lucide="plus"></i>添加安排</a></x-slot>
    <section class="wb-records-panel">
        <form method="GET" class="personal-list-filter">
            <input type="hidden" name="timetable" value="{{ $timetable?->id }}">
            <label class="wb-field-group"><span class="wb-label">查看月份</span><input class="wb-field" type="month" name="month" value="{{ $month->format('Y-m') }}"></label>
            <label class="wb-field-group"><span class="wb-label">搜索活动</span><input class="wb-field" name="q" value="{{ $filters['q']??'' }}" placeholder="社团、运动、聚会…"></label>
            <label class="personal-checkbox"><input type="checkbox" name="canceled" value="1" @checked(request()->boolean('canceled'))>包括已取消</label>
            <button class="wb-btn" type="submit">查看</button>
        </form>
        <div class="personal-month-nav"><a class="wb-btn" href="{{ route('personal-events.index', ['timetable'=>$timetable?->id,'month'=>$month->subMonth()->format('Y-m')]) }}">上个月</a><span>{{ $month->format('Y年n月') }} · {{ $events->total() }} 项安排</span><a class="wb-btn" href="{{ route('personal-events.index', ['timetable'=>$timetable?->id,'month'=>$month->addMonth()->format('Y-m')]) }}">下个月</a></div>
        @forelse($events as $item)
            <a class="personal-event-row {{ $item['canceled']?'is-canceled':'' }}" href="{{ route('personal-events.edit',['event'=>$item['event_id'],'occurrence'=>$item['occurrence_date'],'timetable'=>$timetable?->id]) }}" style="--personal-color: {{ $item['color'] }}">
                <time class="personal-event-date">{{ $item['starts_at']->timezone($timezone)->format('n/j') }}<small>{{ ['日','一','二','三','四','五','六'][$item['starts_at']->timezone($timezone)->dayOfWeek] }}</small></time>
                <div class="personal-event-content"><div class="academic-task__tags"><span>{{ $item['category'] }}</span><span>{{ $item['repeat']==='weekly'?'每周重复':'单次安排' }}</span>@if($item['canceled'])<span>已取消</span>@endif</div><h2>{{ $item['title'] }}</h2><p>{{ $item['all_day']?'全天':$item['starts_at']->timezone($timezone)->format('H:i') }} — {{ ($item['all_day']?$item['ends_at']->subDay():$item['ends_at'])->timezone($timezone)->format($item['all_day']?'n/j':'n/j H:i') }}@if($item['location']) · {{ $item['location'] }}@endif</p></div><i data-lucide="chevron-right"></i>
            </a>
        @empty
            <div class="wb-records-empty"><div class="wb-records-empty__symbol"><i data-lucide="calendar-days"></i></div><h2>这个月还没有匹配的安排</h2><p>给运动、社团和休息留一点时间。</p><a class="wb-btn wb-btn--primary" href="{{ route('personal-events.create',['timetable'=>$timetable?->id]) }}">添加个人安排</a></div>
        @endforelse
        @if($events->hasPages())<div class="wb-records-pagination">{{ $events->links() }}</div>@endif
    </section>
    <p class="academic-hint mt-4">时间按 {{ $timezone }} 显示。个人安排默认私密，只有主动开启分享链接或图片导出的包含开关才会展示。</p>
</x-personal-shell>
