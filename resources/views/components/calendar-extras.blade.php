@props(['events'])
@foreach(collect($events)->take(3) as $event)
    <a class="academic-calendar-chip {{ $event['completed']?'is-complete':'' }}" style="--task-color: {{ $event['color'] }}" href="{{ $event['url'] }}" title="{{ $event['title'] }} · {{ $event['label'] }} {{ $event['time']??'' }} {{ $event['location']??'' }}"><span>{{ $event['completed']?'✓ ':'' }}{{ $event['label'] }} {{ $event['time']??'' }}</span><strong>{{ $event['title'] }}</strong></a>
@endforeach
@if(count($events)>3)<details class="calendar-extra-list"><summary>另有 {{ count($events)-3 }} 项 · 展开</summary>@foreach(collect($events)->slice(3) as $event)<a class="academic-calendar-chip {{ $event['completed']?'is-complete':'' }}" style="--task-color: {{ $event['color'] }}" href="{{ $event['url'] }}"><span>{{ $event['label'] }} {{ $event['time']??'' }}</span><strong>{{ $event['title'] }}</strong></a>@endforeach</details>@endif
