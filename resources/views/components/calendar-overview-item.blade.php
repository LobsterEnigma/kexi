@props(['event'])
<details class="calendar-overview-item {{ $event['completed'] ? 'is-complete' : '' }}" style="--task-color: {{ $event['color'] }}">
    <summary>
        <span class="calendar-overview-item__label">{{ $event['completed'] ? '已完成 · ' : '' }}{{ $event['label'] }}</span>
        <strong>{{ $event['title'] }}</strong>
        <i data-lucide="chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="calendar-overview-item__detail">
        <p class="calendar-overview-item__name">{{ $event['title'] }}</p>
        @if(!empty($event['full_time']))<p>起止：{{ $event['full_time'] }}</p>@endif
        @if(!empty($event['location']))<p>地点：{{ $event['location'] }}</p>@endif
        @if(!empty($event['url']))<a href="{{ $event['url'] }}">查看详情 <span aria-hidden="true">→</span></a>@endif
    </div>
</details>
