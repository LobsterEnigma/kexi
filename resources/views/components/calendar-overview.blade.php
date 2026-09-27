@props(['days', 'dates'])
@if(collect($days)->contains(fn($day) => !empty($day['banners'])))
<section class="academic-deadlines calendar-overview" aria-label="全天、跨日与截止事项">
    <div class="academic-deadlines__label calendar-overview__label"><i data-lucide="calendar-range" aria-hidden="true"></i><span>全天 / 跨日</span><small>及截止事项</small></div>
    @foreach($dates as $date)
        @php($events = collect($days[$date->toDateString()]['banners'] ?? []))
        <div class="academic-deadlines__day" data-agenda-date="{{ $date->toDateString() }}" aria-label="{{ $date->format('n月j日') }}事项">
            <div class="calendar-overview__events">
                @foreach($events->take(2) as $event)<x-calendar-overview-item :event="$event" />@endforeach
                @if($events->count() > 2)
                    <details class="calendar-overview__more"><summary><span>另有 {{ $events->count() - 2 }} 项</span><i data-lucide="chevron-down" aria-hidden="true"></i></summary>
                        @foreach($events->slice(2) as $event)<x-calendar-overview-item :event="$event" />@endforeach
                    </details>
                @endif
            </div>
        </div>
    @endforeach
</section>
@endif
