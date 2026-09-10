@php
    $weekNumber = max(1, (int) $week);
    $weekCount = max(1, (int) $timetable->week_count);
    $previousWeek = max(1, $weekNumber - 1);
    $nextWeek = min($weekCount, $weekNumber + 1);
    $weekStartDate = $timetable->weekStartDate($weekNumber);
    $weekDates = $weekStartDate
        ? collect(range(0, 6))->map(fn (int $day) => $weekStartDate->copy()->addDays($day))
        : collect();
    $weekdays = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
    $items = collect($personalLayout['items'] ?? data_get($analysis, 'items', []));
    $itemsByDay = $items->groupBy(fn ($item) => (int) data_get($item, 'meeting.weekday'));
    $dayStart = (int) ($personalLayout['day_start'] ?? data_get($analysis, 'day_start', 8 * 60));
    $dayEnd = (int) ($personalLayout['day_end'] ?? data_get($analysis, 'day_end', 22 * 60));
    $calendarHeight = max(60, $dayEnd - $dayStart);
    $slotCount = (int) ceil($calendarHeight / 30);
    $startHour = (int) floor($dayStart / 60);
    $endHour = (int) ceil($dayEnd / 60);
    $summary = data_get($analysis, 'summary', []);
    $issueCount = (int) data_get($summary, 'conflicts', 0) + (int) data_get($summary, 'near', 0);
    $nearThreshold = max(1, (int) $timetable->near_threshold_minutes);
    $statusLabels = [
        'conflict' => '时间冲突',
        'near' => '课程临近',
        'slack_light' => '宽松 '.($nearThreshold + 1).'–'.($nearThreshold * 2).' 分钟',
        'slack_medium' => '宽松 '.($nearThreshold * 2 + 1).'–'.($nearThreshold * 4).' 分钟',
        'slack_deep' => '宽松 '.($nearThreshold * 4).'+ 分钟',
        'canceled' => '已取消',
    ];
    $statusIcons = [
        'conflict' => 'triangle-alert',
        'near' => 'clock-3',
        'slack_light' => 'circle-check-big',
        'slack_medium' => 'circle-check-big',
        'slack_deep' => 'circle-check-big',
        'canceled' => 'calendar-x-2',
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $timetable->name }} · 只读课表</x-slot>

    <div class="public-share-shell" x-data="timetableWorkbench({ weekCount: @js($weekCount) })">
        <header class="wb-header">
            <div class="wb-header__top">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="wb-brand__mark shrink-0" aria-hidden="true">
                        <i data-lucide="book-open" class="h-5 w-5"></i>
                    </span>
                    <h1 class="wb-title">
                        {{ $timetable->term_name ? $timetable->term_name.' · ' : '' }}{{ $timetable->name }}
                    </h1>
                </div>

                <div class="wb-header__actions wb-header__desktop-actions">
                    <div class="wb-week-control" aria-label="周次切换">
                        <a class="wb-icon-btn" href="{{ route('public-shares.show', ['token' => $token, 'week' => $previousWeek]) }}" title="上一周" aria-label="上一周">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <span class="wb-week-label">第 {{ $weekNumber }} 周</span>
                        <a class="wb-icon-btn" href="{{ route('public-shares.show', ['token' => $token, 'week' => $nextWeek]) }}" title="下一周" aria-label="下一周">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="wb-mobile-controls">
                    <button class="wb-icon-btn relative" type="button" x-on:click="diagnosticsOpen = true" title="查看问题" aria-label="查看问题">
                        <i data-lucide="panel-right-open"></i>
                        @if ($issueCount > 0)
                            <span class="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white">{{ min(99, $issueCount) }}</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="wb-tabs">
                <span class="wb-tab" aria-selected="true">只读周课表</span>
                <span class="hidden text-xs text-slate-500 sm:inline">由 {{ config('app.name', '课隙') }} 安全分享</span>

                <div class="wb-mobile-controls wb-week-control ml-auto" aria-label="周次切换">
                    <a class="wb-icon-btn" href="{{ route('public-shares.show', ['token' => $token, 'week' => $previousWeek]) }}" title="上一周" aria-label="上一周">
                        <i data-lucide="chevron-left"></i>
                    </a>
                    <span class="wb-week-label">第 {{ $weekNumber }} 周</span>
                    <a class="wb-icon-btn" href="{{ route('public-shares.show', ['token' => $token, 'week' => $nextWeek]) }}" title="下一周" aria-label="下一周">
                        <i data-lucide="chevron-right"></i>
                    </a>
                </div>
            </div>
        </header>

        <main class="wb-main">
            <div class="calendar-scroll">
                <div class="calendar-canvas">
                    <div class="calendar-weekdays">
                        <div class="calendar-time-heading">时间</div>
                        @foreach ($weekdays as $weekdayNumber => $weekday)
                            @php
                                $weekdayDate = $weekDates->get($weekdayNumber - 1);
                            @endphp
                            <div
                                class="calendar-weekday {{ $weekdayNumber >= 6 ? 'calendar-weekday--weekend' : '' }}"
                                aria-label="{{ $weekdayDate ? $weekday.'，'.$weekdayDate->format('n月j日') : $weekday }}"
                            >
                                <span class="calendar-weekday__label">{{ $weekday }}</span>
                                @if ($weekdayDate)
                                    <time class="calendar-weekday__date" datetime="{{ $weekdayDate->toDateString() }}">{{ $weekdayDate->format('n/j') }}</time>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if(collect($personalDays)->contains(fn($day)=>!empty($day['banners'])))<div class="academic-deadlines"><div class="academic-deadlines__label">全天安排</div>@foreach($weekDates as $date)<div class="academic-deadlines__day">@foreach($personalDays[$date->toDateString()]['banners']??[] as $event)<article class="academic-calendar-chip" style="--task-color: {{ $event['color'] }}"><span>{{ $event['label'] }}</span><strong>{{ $event['title'] }}</strong><span>{{ $event['location'] }}</span></article>@endforeach</div>@endforeach</div>@endif
                    <div class="calendar-body" style="height: {{ $calendarHeight }}px">
                        <div class="calendar-time-axis" style="height: {{ $calendarHeight }}px">
                            @for ($hour = $startHour; $hour < $endHour; $hour++)
                                <span class="calendar-time-label" style="--hour-index: {{ $hour - $startHour }}">{{ sprintf('%02d:00', $hour) }}</span>
                            @endfor
                        </div>

                        @foreach ($weekdays as $weekdayNumber => $weekdayLabel)
                            <div class="calendar-day {{ $weekdayNumber >= 6 ? 'calendar-day--weekend' : '' }}" style="height: {{ $calendarHeight }}px; grid-template-rows: repeat({{ $slotCount }}, 30px)" aria-label="{{ $weekdayLabel }}课程">
                                @for ($slot = 0; $slot < $slotCount; $slot++)
                                    <span class="calendar-slot" aria-hidden="true"></span>
                                @endfor

                                @foreach(collect($personalLayout['academic']??[])->where('weekday',$weekdayNumber) as $event)<article class="academic-time-event {{ $event['conflict']?'has-conflict':'' }}" style="--task-color: {{ $event['color'] }}; --event-top: {{ $event['start_minute']-$dayStart }}px; --event-height: {{ max(18,$event['end_minute']-$event['start_minute']) }}px; --lane: {{ $event['lane'] }}; --lane-count: {{ $event['lane_count'] }}" tabindex="0" title="{{ $event['title'] }} · {{ $event['full_time'] }} · {{ $event['location'] }}"><small>{{ $event['label'] }}{{ $event['conflict']?' · 时间重叠':'' }}</small><strong>{{ $event['title'] }}</strong><span>{{ $event['time'] }}</span><span>{{ $event['location'] }}</span></article>@endforeach
                                @foreach ($itemsByDay->get($weekdayNumber, collect()) as $item)
                                    @php
                                        $meeting = data_get($item, 'meeting');
                                        $course = $meeting->course;
                                        $status = (string) data_get($item, 'status', 'slack_deep');
                                        $top = max(0, (float) data_get($item, 'top', 0));
                                        $height = max(18, (float) data_get($item, 'height', 18));
                                        $lane = max(0, (int) data_get($item, 'lane', 0));
                                        $laneCount = max(1, (int) data_get($item, 'lane_count', 1));
                                        $gap = data_get($item, 'nearest_gap');
                                        $startsAt = substr((string) $meeting->starts_at, 0, 5);
                                        $endsAt = substr((string) $meeting->ends_at, 0, 5);
                                        $compactClass = $height < 50 ? 'course-event--micro' : ($height < 104 ? 'course-event--compact' : ($height < 132 ? 'course-event--medium' : ''));
                                        $statusText = $status === 'near' && is_numeric($gap)
                                            ? '间隔 '.(int) $gap.' 分钟'
                                            : ($statusLabels[$status] ?? $statusLabels['slack_deep']);
                                    @endphp

                                    <article
                                        class="course-event course-event--{{ $status }} {{ $compactClass }}"
                                        data-course-tone="{{ (int) $course->getKey() % 6 }}"
                                        style="--event-top: {{ $top }}px; --event-height: {{ $height }}px; --lane: {{ $lane }}; --lane-count: {{ $laneCount }}; {{ $meeting->colorStyle() }}"
                                        title="{{ implode(' · ', array_filter([(string) $course->name, (string) $course->code, $startsAt.'–'.$endsAt, (string) $meeting->location])) }}"
                                        tabindex="0"
                                        aria-label="{{ $course->name }}，{{ $weekdayLabel }} {{ $startsAt }} 至 {{ $endsAt }}{{ $meeting->location ? '，地点：'.$meeting->location : '' }}，{{ $statusText }}"
                                    >
                                        <span class="course-event__signal" title="{{ $statusText }}" aria-hidden="true">
                                            <i data-lucide="{{ $statusIcons[$status] ?? 'circle-check-big' }}"></i>
                                        </span>
                                        <span class="course-event__title">{{ $height < 104 && filled($course->code) ? $course->code : $course->name }}</span>
                                        <span class="course-event__meta">{{ $startsAt }}–{{ $endsAt }}</span>
                                        @if ($meeting->location || $meeting->teacher)
                                            <span class="course-event__meta course-event__meta--secondary" title="{{ $meeting->location ?: $meeting->teacher }}">{{ $meeting->location ?: $meeting->teacher }}</span>
                                        @endif
                                        <span class="course-event__status">
                                            <i data-lucide="{{ $statusIcons[$status] ?? 'circle-check-big' }}"></i>
                                            {{ $statusText }}
                                        </span>
                                    </article>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($items->isEmpty())
                            <div class="wb-empty-calendar">
                                <i data-lucide="calendar-range"></i>
                                <strong class="text-sm text-slate-800">第 {{ $weekNumber }} 周没有课程</strong>
                                <span>可切换周次查看其他安排。</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </main>

        <aside class="wb-diagnostics wb-diagnostics--desktop">
            <x-workbench.diagnostics :analysis="$analysis" />
        </aside>

        <div class="wb-drawer-backdrop" x-cloak x-show="diagnosticsOpen" x-on:click="diagnosticsOpen = false" x-transition.opacity></div>
        <aside
            class="wb-drawer wb-drawer--right"
            x-cloak
            x-show="diagnosticsOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <button class="wb-icon-btn wb-drawer__close" type="button" x-on:click="diagnosticsOpen = false" title="关闭诊断" aria-label="关闭诊断"><i data-lucide="x"></i></button>
            <x-workbench.diagnostics :analysis="$analysis" />
        </aside>
    </div>
</x-app-layout>
