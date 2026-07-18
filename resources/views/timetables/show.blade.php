@php
    $weekNumber = max(1, (int) $week);
    $weekCount = max(1, (int) $timetable->week_count);
    $previousWeek = max(1, $weekNumber - 1);
    $nextWeek = min($weekCount, $weekNumber + 1);
    $weekStartDate = $timetable->term_start_date?->copy()->addWeeks($weekNumber - 1);
    $weekEndDate = $weekStartDate?->copy()->addDays(6);
    $termEndDate = $timetable->resolvedTermEndDate();
    $weekdays = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
    $items = collect(data_get($analysis, 'items', []));
    $itemsByDay = $items->groupBy(fn ($item) => (int) data_get($item, 'meeting.weekday'));
    $dayStart = (int) data_get($analysis, 'day_start', 8 * 60);
    $dayEnd = (int) data_get($analysis, 'day_end', 22 * 60);
    $calendarHeight = max(60, $dayEnd - $dayStart);
    $slotCount = (int) ceil($calendarHeight / 30);
    $startHour = (int) floor($dayStart / 60);
    $endHour = (int) ceil($dayEnd / 60);
    $summary = data_get($analysis, 'summary', []);
    $conflictCount = (int) data_get($summary, 'conflicts', 0);
    $nearCount = (int) data_get($summary, 'near', 0);
    $archivedCourses = $timetable->courses->where('is_archived', true)->values();
    $nearThreshold = max(1, (int) $timetable->near_threshold_minutes);
    $shareList = collect($shares ?? [])->when(
        ! isset($shares) && $activeShare,
        fn ($collection) => $collection->push($activeShare),
    );
    $newShareUrl = session('new_share_url');
    $initialModal = $newShareUrl ? 'share' : old('_form');
    $viewMode = ($viewMode ?? 'week') === 'month' ? 'month' : 'week';
    $isMonthView = $viewMode === 'month';
    $monthValue = $isMonthView ? data_get($monthCalendarData, 'month')?->format('Y-m') : null;
    $monthLabel = $isMonthView ? data_get($monthCalendarData, 'label') : null;

    $normalizeMeeting = static function (array $meeting) use ($weekCount): array {
        $specificWeeks = $meeting['specific_weeks'] ?? '';
        if (is_array($specificWeeks)) {
            $specificWeeks = implode(',', $specificWeeks);
        }

        return [
            'label' => $meeting['label'] ?? '',
            'teacher' => $meeting['teacher'] ?? '',
            'weekday' => (int) ($meeting['weekday'] ?? 1),
            'starts_at' => substr((string) ($meeting['starts_at'] ?? '08:00'), 0, 5),
            'ends_at' => substr((string) ($meeting['ends_at'] ?? '09:45'), 0, 5),
            'location' => $meeting['location'] ?? '',
            'week_mode' => $meeting['week_mode'] ?? 'all',
            'start_week' => (int) ($meeting['start_week'] ?? 1),
            'end_week' => (int) ($meeting['end_week'] ?? $weekCount),
            'specific_weeks' => $specificWeeks,
        ];
    };

    $createMeetings = collect(old('_form') === 'course-create' ? old('meetings', []) : [])
        ->map(fn ($meeting) => $normalizeMeeting((array) $meeting))
        ->values()
        ->all();

    $buildCoursePayload = static function ($course, $selectedMeeting = null, ?int $occurrenceWeek = null) use ($normalizeMeeting, $timetable, $weekCount, $weekdays): array {
        $courseMeetings = $course->relationLoaded('meetings') ? $course->meetings : collect();
        $payloadMeetings = $courseMeetings->map(function ($row) use ($normalizeMeeting) {
            $mode = $row->week_mode instanceof \BackedEnum ? $row->week_mode->value : (string) $row->week_mode;

            return $normalizeMeeting([
                'label' => $row->label,
                'teacher' => $row->teacher,
                'weekday' => $row->weekday,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'location' => $row->location,
                'week_mode' => $mode,
                'start_week' => $row->start_week,
                'end_week' => $row->end_week,
                'specific_weeks' => $row->specific_weeks,
            ]);
        })->values()->all();

        $occurrence = null;
        if ($selectedMeeting && $occurrenceWeek) {
            $canceledWeeks = $selectedMeeting->cancellations
                ->pluck('week_number')
                ->map(fn ($week) => (int) $week)
                ->sort()
                ->values();
            $occurringWeeks = collect(range(1, $weekCount))
                ->filter(fn (int $week): bool => $selectedMeeting->occursInWeek($week))
                ->map(function (int $week) use ($selectedMeeting, $timetable, $weekdays, $canceledWeeks): array {
                    $date = $timetable->term_start_date?->copy()
                        ->addWeeks($week - 1)
                        ->addDays((int) $selectedMeeting->weekday - 1);

                    return [
                        'week' => $week,
                        'label' => '第 '.$week.' 周',
                        'date' => implode(' · ', array_filter([
                            $date?->format('n月j日'),
                            $weekdays[(int) $selectedMeeting->weekday] ?? null,
                        ])),
                        'canceled' => $canceledWeeks->contains($week),
                    ];
                })
                ->values()
                ->all();
            $startsAt = substr((string) $selectedMeeting->starts_at, 0, 5);
            $endsAt = substr((string) $selectedMeeting->ends_at, 0, 5);

            $occurrence = [
                'week' => $occurrenceWeek,
                'isCanceled' => $canceledWeeks->contains($occurrenceWeek),
                'summary' => '第 '.$occurrenceWeek.' 周 · '.($weekdays[(int) $selectedMeeting->weekday] ?? '').' '.$startsAt.'–'.$endsAt,
                'cancelAction' => route('course-meeting-cancellations.store', [$timetable, $course, $selectedMeeting]),
                'syncAction' => route('course-meeting-cancellations.update', [$timetable, $course, $selectedMeeting]),
                'restoreAction' => route('course-meeting-cancellations.destroy', [$timetable, $course, $selectedMeeting]),
                'canceledWeeks' => $canceledWeeks->map(fn (int $week): string => (string) $week)->all(),
                'occurringWeeks' => $occurringWeeks,
            ];
        }

        return [
            'action' => route('courses.update', [$timetable, $course]),
            'destroyAction' => route('courses.destroy', [$timetable, $course]),
            'archiveAction' => route('courses.archive', [$timetable, $course]),
            'restoreAction' => route('courses.restore', [$timetable, $course]),
            'name' => $course->name,
            'code' => $course->code,
            'notes' => $course->notes,
            'isArchived' => (bool) $course->is_archived,
            'meetings' => $payloadMeetings,
            'occurrence' => $occurrence,
        ];
    };

    $statusLabels = static function (string $status, mixed $gap) use ($nearThreshold): string {
        return match ($status) {
            'conflict' => '时间冲突',
            'near' => is_numeric($gap) ? '间隔 '.(int) $gap.' 分钟' : '课程临近',
            'slack_light' => '宽松 '.($nearThreshold + 1).'–'.($nearThreshold * 2).' 分钟',
            'slack_medium' => '宽松 '.($nearThreshold * 2 + 1).'–'.($nearThreshold * 4).' 分钟',
            'canceled' => '已取消',
            default => '宽松 '.($nearThreshold * 4).'+ 分钟',
        };
    };

    $statusIcons = [
        'conflict' => 'triangle-alert',
        'near' => 'clock-3',
        'slack_light' => 'circle-check-big',
        'slack_medium' => 'circle-check-big',
        'slack_deep' => 'circle-check-big',
        'canceled' => 'calendar-x-2',
    ];

    $exportWeekItems = $items->map(function (array $item): array {
        $meeting = data_get($item, 'meeting');
        $course = $meeting->course;

        return [
            'weekday' => (int) $meeting->weekday,
            'startMinute' => (int) data_get($item, 'start_minute'),
            'endMinute' => (int) data_get($item, 'end_minute'),
            'lane' => (int) data_get($item, 'lane', 0),
            'laneCount' => (int) data_get($item, 'lane_count', 1),
            'name' => (string) $course->name,
            'code' => trim((string) $course->code),
            'time' => substr((string) $meeting->starts_at, 0, 5).'–'.substr((string) $meeting->ends_at, 0, 5),
            'location' => (string) $meeting->location,
            'teacher' => (string) $meeting->teacher,
            'status' => (string) data_get($item, 'status', 'slack_deep'),
            'tone' => (int) $course->getKey() % 6,
        ];
    })->values()->all();
    $exportMonthCells = $isMonthView
        ? collect(data_get($monthCalendarData, 'cells', []))->map(function (array $cell): array {
            $cellWeek = (int) data_get($cell, 'week', 0);
            $cellDate = data_get($cell, 'date');
            $events = collect(data_get($cell, 'events', []))->map(function ($meeting) use ($cellWeek): array {
                $course = $meeting->course;

                return [
                    'name' => (string) $course->name,
                    'code' => trim((string) $course->code),
                    'time' => substr((string) $meeting->starts_at, 0, 5),
                    'status' => $meeting->isCanceledInWeek($cellWeek) ? 'canceled' : 'normal',
                    'tone' => (int) $course->getKey() % 6,
                ];
            })->values()->all();

            return [
                'day' => (int) $cellDate?->day,
                'weekday' => (int) $cellDate?->isoWeekday(),
                'inMonth' => (bool) data_get($cell, 'in_month'),
                'inTerm' => (bool) data_get($cell, 'in_term'),
                'isToday' => (bool) data_get($cell, 'is_today'),
                'week' => $cellWeek ?: null,
                'events' => $events,
                'overflowCount' => (int) data_get($cell, 'overflow_count', 0),
            ];
        })->values()->all()
        : [];
    $exportLabel = $isMonthView ? (string) $monthLabel : '第 '.$weekNumber.' 周';
    $exportDateRange = $isMonthView
        ? (string) $monthLabel
        : ($weekStartDate ? $weekStartDate->format('Y年n月j日').'–'.$weekEndDate->format('n月j日') : $exportLabel);
    $exportData = [
        'view' => $viewMode,
        'siteName' => config('app.name', '课隙'),
        'timetableName' => (string) $timetable->name,
        'termName' => (string) $timetable->term_name,
        'label' => $exportLabel,
        'dateRange' => $exportDateRange,
        'weekdays' => array_values($weekdays),
        'dayStart' => $dayStart,
        'dayEnd' => $dayEnd,
        'summary' => [
            'courseCount' => $items->pluck('meeting.course_id')->unique()->count(),
            'conflicts' => $conflictCount,
            'near' => $nearCount,
            'canceled' => (int) data_get($summary, 'canceled', 0),
        ],
        'items' => $exportWeekItems,
        'cells' => $exportMonthCells,
        'filename' => trim(implode('-', array_filter([
            $timetable->term_name ?: $timetable->name,
            $exportLabel,
            $isMonthView ? '月课表' : '周课表',
        ]))),
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $timetable->name }}</x-slot>

    <div
        class="wb-shell {{ $isMonthView ? 'wb-shell--month' : '' }}"
        data-view-mode="{{ $viewMode }}"
        x-data="timetableWorkbench({
            weekCount: @js($weekCount),
            createMeetings: @js($createMeetings),
            initialModal: @js($initialModal),
            termStartDate: @js($timetable->term_start_date?->toDateString()),
            timetableUrl: @js(route('timetables.show', $timetable)),
            exportData: @js($exportData),
        })"
    >
        <aside class="wb-sidebar--desktop">
            <x-workbench.sidebar :timetable="$timetable" :timetables="$timetables" />
        </aside>

        <header class="wb-header">
            <div class="wb-header__top">
                <div class="wb-mobile-controls">
                    <button
                        class="wb-icon-btn"
                        type="button"
                        x-on:click="sidebarOpen = true"
                        title="打开导航"
                        aria-label="打开导航"
                    >
                        <i data-lucide="menu"></i>
                    </button>
                </div>

                <h1 class="wb-title">
                    {{ $timetable->term_name ? $timetable->term_name.' · ' : '' }}{{ $timetable->name }}
                </h1>

                <div class="wb-header__actions wb-header__desktop-actions">
                    @if ($isMonthView)
                        <div class="wb-week-control" aria-label="月份切换">
                            <a
                                class="wb-icon-btn"
                                href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'month', 'month' => data_get($monthCalendarData, 'previous_month') ?? $monthValue]) }}"
                                title="上个月"
                                aria-label="上个月"
                                @if (! data_get($monthCalendarData, 'previous_month')) aria-disabled="true" tabindex="-1" @endif
                            >
                                <i data-lucide="chevron-left"></i>
                            </a>
                            <span class="wb-week-display">
                                <span class="wb-week-label wb-month-label">{{ $monthLabel }}</span>
                                <span class="wb-week-range">{{ $timetable->term_name ?: '教学月历' }}</span>
                            </span>
                            <label class="wb-date-picker" title="选择月份" aria-label="选择月份">
                                <i data-lucide="calendar-days"></i>
                                <input
                                    type="month"
                                    value="{{ $monthValue }}"
                                    min="{{ data_get($monthCalendarData, 'first_month') }}"
                                    max="{{ data_get($monthCalendarData, 'last_month') }}"
                                    x-on:change="goToMonth($event.target.value)"
                                    aria-label="选择要查看的月份"
                                >
                            </label>
                            <a
                                class="wb-icon-btn"
                                href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'month', 'month' => data_get($monthCalendarData, 'next_month') ?? $monthValue]) }}"
                                title="下个月"
                                aria-label="下个月"
                                @if (! data_get($monthCalendarData, 'next_month')) aria-disabled="true" tabindex="-1" @endif
                            >
                                <i data-lucide="chevron-right"></i>
                            </a>
                        </div>
                    @else
                        <div class="wb-week-control" aria-label="周次切换">
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $previousWeek]) }}"
                            title="上一周"
                            aria-label="上一周"
                            @if ($weekNumber <= 1) aria-disabled="true" tabindex="-1" @endif
                        >
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <span class="wb-week-display">
                            <span class="wb-week-label">第 {{ $weekNumber }} 周</span>
                            @if ($weekStartDate)
                                <span class="wb-week-range">{{ $weekStartDate->format('n月j日') }}–{{ $weekEndDate->format('n月j日') }}</span>
                            @endif
                        </span>
                        @if ($weekStartDate)
                            <label class="wb-date-picker" title="按日期跳转" aria-label="按日期跳转">
                                <i data-lucide="calendar-days"></i>
                                <input
                                    type="date"
                                    value="{{ $weekStartDate->toDateString() }}"
                                    min="{{ $timetable->term_start_date->toDateString() }}"
                                    max="{{ $termEndDate->toDateString() }}"
                                    x-on:change="goToDate($event.target.value)"
                                    aria-label="选择日期并跳转到对应教学周"
                                >
                            </label>
                        @else
                            <button class="wb-icon-btn" type="button" disabled title="请先在课表设置中填写学期开始日期" aria-label="日历不可用：未设置学期开始日期">
                                <i data-lucide="calendar-days"></i>
                            </button>
                        @endif
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $nextWeek]) }}"
                            title="下一周"
                            aria-label="下一周"
                            @if ($weekNumber >= $weekCount) aria-disabled="true" tabindex="-1" @endif
                        >
                            <i data-lucide="chevron-right"></i>
                        </a>
                        </div>
                    @endif

                    <button class="wb-btn wb-btn--primary" type="button" x-on:click="openDialog('course-create')">
                        <i data-lucide="plus"></i>
                        添加课程
                    </button>

                    @if ($archivedCourses->isNotEmpty())
                        <button class="wb-btn" type="button" x-on:click="openDialog('archived-courses')">
                            <i data-lucide="eye-off"></i>
                            已隐藏 {{ $archivedCourses->count() }}
                        </button>
                    @endif

                    <button class="wb-btn" type="button" x-on:click="openDialog('share')">
                        <i data-lucide="share-2"></i>
                        分享
                    </button>

                    <button class="wb-btn" type="button" x-on:click="prepareExport()">
                        <i data-lucide="download"></i>
                        导出图片
                    </button>

                    <button
                        class="wb-icon-btn"
                        type="button"
                        x-on:click="openDialog('timetable-settings')"
                        title="课表设置"
                        aria-label="课表设置"
                    >
                        <i data-lucide="settings"></i>
                    </button>
                </div>

                <div class="wb-mobile-controls relative">
                    <button
                        class="wb-icon-btn relative"
                        type="button"
                        x-on:click="diagnosticsOpen = true"
                        title="查看冲突与间隔"
                        aria-label="查看冲突与间隔"
                    >
                        <i data-lucide="panel-right-open"></i>
                        @if ($conflictCount + $nearCount > 0)
                            <span class="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white">
                                {{ min(99, $conflictCount + $nearCount) }}
                            </span>
                        @endif
                    </button>

                    <button
                        class="wb-icon-btn"
                        type="button"
                        x-on:click="mobileActionsOpen = ! mobileActionsOpen"
                        title="更多操作"
                        aria-label="更多操作"
                        x-bind:aria-expanded="mobileActionsOpen"
                    >
                        <i data-lucide="more-horizontal"></i>
                    </button>

                    <div
                        class="absolute right-0 top-11 z-50 w-44 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                        x-cloak
                        x-show="mobileActionsOpen"
                        x-on:click.outside="mobileActionsOpen = false"
                    >
                        <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" x-on:click="openDialog('course-create')">
                            <i data-lucide="plus" class="h-4 w-4"></i>添加课程
                        </button>
                        @if ($archivedCourses->isNotEmpty())
                            <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" x-on:click="openDialog('archived-courses')">
                                <i data-lucide="eye-off" class="h-4 w-4"></i>已隐藏 {{ $archivedCourses->count() }}
                            </button>
                        @endif
                        <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" x-on:click="openDialog('share')">
                            <i data-lucide="share-2" class="h-4 w-4"></i>分享课表
                        </button>
                        <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" x-on:click="prepareExport()">
                            <i data-lucide="download" class="h-4 w-4"></i>导出图片
                        </button>
                        <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50" type="button" x-on:click="openDialog('timetable-settings')">
                            <i data-lucide="settings" class="h-4 w-4"></i>课表设置
                        </button>
                    </div>
                </div>
            </div>

            <div class="wb-tabs">
                <a
                    class="wb-tab"
                    href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $weekNumber]) }}"
                    aria-label="切换到周课表"
                    aria-selected="{{ $isMonthView ? 'false' : 'true' }}"
                >周课表</a>
                <a
                    class="wb-tab"
                    href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'month', 'month' => $monthValue ?? $weekStartDate?->format('Y-m')]) }}"
                    aria-label="切换到月视图"
                    aria-selected="{{ $isMonthView ? 'true' : 'false' }}"
                    @if (! $timetable->term_start_date) aria-disabled="true" tabindex="-1" title="请先设置开学日期" @endif
                >月视图</a>
                <button class="wb-tab wb-tab--diagnostics" type="button" aria-selected="false" x-on:click="diagnosticsOpen = true; diagnosticFilter = 'all'">
                    学期问题
                </button>

                <div class="wb-mobile-controls wb-week-control ml-auto" aria-label="{{ $isMonthView ? '月份切换' : '周次切换' }}">
                    @if ($isMonthView)
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'month', 'month' => data_get($monthCalendarData, 'previous_month') ?? $monthValue]) }}"
                            title="上个月"
                            aria-label="上个月"
                            @if (! data_get($monthCalendarData, 'previous_month')) aria-disabled="true" tabindex="-1" @endif
                        ><i data-lucide="chevron-left"></i></a>
                        <span class="wb-week-label wb-month-label">{{ data_get($monthCalendarData, 'month')?->format('n月') }}</span>
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'month', 'month' => data_get($monthCalendarData, 'next_month') ?? $monthValue]) }}"
                            title="下个月"
                            aria-label="下个月"
                            @if (! data_get($monthCalendarData, 'next_month')) aria-disabled="true" tabindex="-1" @endif
                        ><i data-lucide="chevron-right"></i></a>
                    @else
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $previousWeek]) }}"
                            title="上一周"
                            aria-label="上一周"
                        ><i data-lucide="chevron-left"></i></a>
                        <span class="wb-week-label">第 {{ $weekNumber }} 周</span>
                        @if ($weekStartDate)
                            <label class="wb-date-picker" title="按日期跳转" aria-label="按日期跳转">
                                <i data-lucide="calendar-days"></i>
                                <input
                                    type="date"
                                    value="{{ $weekStartDate->toDateString() }}"
                                    min="{{ $timetable->term_start_date->toDateString() }}"
                                    max="{{ $termEndDate->toDateString() }}"
                                    x-on:change="goToDate($event.target.value)"
                                    aria-label="选择日期并跳转到对应教学周"
                                >
                            </label>
                        @else
                            <button class="wb-icon-btn" type="button" disabled title="请先设置学期开始日期" aria-label="日历不可用：未设置学期开始日期">
                                <i data-lucide="calendar-days"></i>
                            </button>
                        @endif
                        <a
                            class="wb-icon-btn"
                            href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $nextWeek]) }}"
                            title="下一周"
                            aria-label="下一周"
                        ><i data-lucide="chevron-right"></i></a>
                    @endif
                </div>
            </div>
        </header>

        @if ($isMonthView)
            <main class="wb-main wb-main--month" id="month-calendar" aria-label="{{ $monthLabel }}月课表">
                <div class="month-calendar-scroll">
                    <div class="month-calendar-canvas">
                        <div class="month-calendar-weekdays" aria-hidden="true">
                            @foreach ($weekdays as $weekdayNumber => $weekday)
                                <div class="month-calendar-weekday {{ $weekdayNumber >= 6 ? 'month-calendar-weekday--weekend' : '' }}">{{ $weekday }}</div>
                            @endforeach
                        </div>

                        <div class="month-calendar-grid">
                            @foreach (data_get($monthCalendarData, 'cells', collect()) as $cell)
                                @php
                                    $cellDate = data_get($cell, 'date');
                                    $cellWeek = data_get($cell, 'week');
                                    $cellEvents = collect(data_get($cell, 'events', []));
                                    $cellClasses = collect([
                                        ! data_get($cell, 'in_month') ? 'month-day--outside' : null,
                                        ! data_get($cell, 'in_term') ? 'month-day--disabled' : null,
                                        data_get($cell, 'is_today') ? 'month-day--today' : null,
                                        $cellDate?->isoWeekday() >= 6 ? 'month-day--weekend' : null,
                                    ])->filter()->implode(' ');
                                @endphp
                                <section
                                    class="month-day {{ $cellClasses }}"
                                    data-month-date="{{ data_get($cell, 'date_key') }}"
                                    aria-label="{{ $cellDate?->isoFormat('M月D日 dddd') }}{{ $cellWeek ? '，第 '.$cellWeek.' 教学周' : '，学期范围外' }}"
                                >
                                    <div class="month-day__header">
                                        @if (data_get($cell, 'in_term'))
                                            <a
                                                class="month-day__number"
                                                href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $cellWeek]) }}"
                                                title="查看第 {{ $cellWeek }} 周"
                                            >{{ $cellDate?->day }}</a>
                                        @else
                                            <span class="month-day__number">{{ $cellDate?->day }}</span>
                                        @endif

                                        @if ($cellDate?->isoWeekday() === 1 && $cellWeek)
                                            <span class="month-day__week">第 {{ $cellWeek }} 周</span>
                                        @elseif (data_get($cell, 'is_today'))
                                            <span class="month-day__today">今天</span>
                                        @endif
                                    </div>

                                    <div class="month-day__events">
                                        @foreach ($cellEvents as $meeting)
                                            @php
                                                $course = $meeting->course;
                                                $coursePayload = $buildCoursePayload($course, $meeting, (int) $cellWeek);
                                                $startsAt = substr((string) $meeting->starts_at, 0, 5);
                                                $endsAt = substr((string) $meeting->ends_at, 0, 5);
                                                $isCanceled = $meeting->isCanceledInWeek((int) $cellWeek);
                                            @endphp
                                            <button
                                                class="month-event {{ $isCanceled ? 'month-event--canceled' : '' }}"
                                                data-course-tone="{{ (int) $course->getKey() % 6 }}"
                                                type="button"
                                                x-on:click="openCourse({{ Illuminate\Support\Js::from($coursePayload) }})"
                                                title="{{ $course->name }} · {{ $startsAt }}–{{ $endsAt }}{{ $isCanceled ? ' · 已取消' : '' }}{{ $meeting->location ? ' · '.$meeting->location : '' }}"
                                                aria-label="编辑 {{ $course->name }}，{{ $startsAt }} 至 {{ $endsAt }}{{ $isCanceled ? '，已取消' : '' }}"
                                            >
                                                <span class="month-event__time">{{ $startsAt }}</span>
                                                <span class="month-event__name">{{ $course->name }}</span>
                                                @if ($isCanceled)
                                                    <span class="month-event__state">已取消</span>
                                                @endif
                                            </button>
                                        @endforeach

                                        @if ((int) data_get($cell, 'overflow_count', 0) > 0)
                                            <a
                                                class="month-day__more"
                                                href="{{ route('timetables.show', ['timetable' => $timetable, 'view' => 'week', 'week' => $cellWeek]) }}"
                                            >另有 {{ (int) data_get($cell, 'overflow_count') }} 节</a>
                                        @endif
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            </main>
        @else
        <main class="wb-main" id="week-calendar">
            <div class="calendar-scroll" x-ref="calendarScroll">
                <div class="calendar-canvas">
                    <div class="calendar-weekdays">
                        <div class="calendar-time-heading">时间</div>
                        @foreach ($weekdays as $weekdayNumber => $weekday)
                            <div class="calendar-weekday {{ $weekdayNumber >= 6 ? 'calendar-weekday--weekend' : '' }}">{{ $weekday }}</div>
                        @endforeach
                    </div>

                    <div class="calendar-body" style="height: {{ $calendarHeight }}px">
                        <div class="calendar-time-axis" style="height: {{ $calendarHeight }}px">
                            @for ($hour = $startHour; $hour < $endHour; $hour++)
                                <span
                                    class="calendar-time-label"
                                    style="--hour-index: {{ $hour - $startHour }}"
                                >{{ sprintf('%02d:00', $hour) }}</span>
                            @endfor
                        </div>

                        @foreach ($weekdays as $weekdayNumber => $weekdayLabel)
                            <div
                                class="calendar-day {{ $weekdayNumber >= 6 ? 'calendar-day--weekend' : '' }}"
                                style="height: {{ $calendarHeight }}px; grid-template-rows: repeat({{ $slotCount }}, 30px)"
                                aria-label="{{ $weekdayLabel }}课程"
                            >
                                @for ($slot = 0; $slot < $slotCount; $slot++)
                                    <span class="calendar-slot" aria-hidden="true"></span>
                                @endfor

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
                                        $courseCode = trim((string) $course->code);
                                        $usesCodeAsTitle = $height < 104 && $courseCode !== '';
                                        $nameModeMeta = implode(' · ', array_filter([$courseCode, $startsAt.'–'.$endsAt]));
                                        $compactClass = $height < 48
                                            ? 'course-event--micro'
                                            : ($height < 104 ? 'course-event--compact' : ($height < 132 ? 'course-event--medium' : ''));
                                        $coursePayload = $buildCoursePayload($course, $meeting, $weekNumber);
                                    @endphp

                                    <button
                                        class="course-event course-event--{{ $status }} {{ $compactClass }} {{ $courseCode !== '' ? 'course-event--has-code' : '' }} {{ $usesCodeAsTitle ? 'course-event--code-title' : '' }}"
                                        data-course-tone="{{ (int) $course->getKey() % 6 }}"
                                        style="--event-top: {{ $top }}px; --event-height: {{ $height }}px; --lane: {{ $lane }}; --lane-count: {{ $laneCount }}"
                                        type="button"
                                        x-on:click="openCourse({{ Illuminate\Support\Js::from($coursePayload) }})"
                                        title="{{ implode(' · ', array_filter([(string) $course->name, $courseCode, $startsAt.'–'.$endsAt, (string) $meeting->location])) }}"
                                        aria-label="编辑 {{ $course->name }}，{{ $weekdayLabel }} {{ $startsAt }} 至 {{ $endsAt }}，{{ $statusLabels($status, $gap) }}"
                                    >
                                        <span class="course-event__signal" title="{{ $statusLabels($status, $gap) }}" aria-hidden="true">
                                            <i data-lucide="{{ $statusIcons[$status] ?? 'circle-check-big' }}"></i>
                                        </span>
                                        <span class="course-event__title course-event__title--name">{{ $course->name }}</span>
                                        @if ($courseCode !== '')
                                            <span class="course-event__title course-event__title--code">{{ $courseCode }}</span>
                                        @endif
                                        <span class="course-event__meta course-event__meta--primary course-event__meta--name-mode">{{ $nameModeMeta }}</span>
                                        @if ($courseCode !== '')
                                            <span class="course-event__meta course-event__meta--primary course-event__meta--code-mode">{{ $startsAt }}–{{ $endsAt }}</span>
                                        @endif
                                        @if ($meeting->location || $meeting->teacher)
                                            <span class="course-event__meta course-event__meta--secondary">
                                                {{ $meeting->location ?: $meeting->teacher }}
                                            </span>
                                        @endif
                                        <span class="course-event__status">
                                            <i data-lucide="{{ $statusIcons[$status] ?? 'circle-check-big' }}"></i>
                                            {{ $statusLabels($status, $gap) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($items->isEmpty())
                            <div class="wb-empty-calendar">
                                <i data-lucide="calendar-range"></i>
                                <strong class="text-sm text-slate-800">第 {{ $weekNumber }} 周还没有课程</strong>
                                <span>添加课程后，冲突与间隔会在右侧自动分析。</span>
                                <button class="wb-btn wb-btn--primary" type="button" x-on:click="openDialog('course-create')">
                                    添加课程
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </main>
        @endif

        @unless ($isMonthView)
            <aside class="wb-diagnostics wb-diagnostics--desktop">
                <x-workbench.diagnostics :analysis="$analysis" id-prefix="desktop" />
            </aside>
        @endunless

        <div
            class="wb-drawer-backdrop"
            x-cloak
            x-show="sidebarOpen || diagnosticsOpen"
            x-on:click="closeOverlays()"
            x-transition.opacity
        ></div>

        <aside
            class="wb-drawer wb-drawer--left"
            x-cloak
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
        >
            <button class="wb-icon-btn wb-drawer__close" type="button" x-on:click="sidebarOpen = false" title="关闭导航" aria-label="关闭导航">
                <i data-lucide="x"></i>
            </button>
            <x-workbench.sidebar :timetable="$timetable" :timetables="$timetables" />
        </aside>

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
            <button class="wb-icon-btn wb-drawer__close" type="button" x-on:click="diagnosticsOpen = false" title="关闭诊断" aria-label="关闭诊断">
                <i data-lucide="x"></i>
            </button>
            <x-workbench.diagnostics :analysis="$analysis" id-prefix="mobile" />
        </aside>

        @if (session('status'))
            <div
                class="fixed bottom-5 left-1/2 z-[120] flex -translate-x-1/2 items-center gap-2 rounded-md border border-green-200 bg-white px-4 py-3 text-sm font-medium text-green-800 shadow-lg"
                x-data="{ visible: true }"
                x-show="visible"
                x-init="setTimeout(() => visible = false, 3200)"
                x-transition.opacity
                role="status"
            >
                <i data-lucide="circle-check-big" class="h-5 w-5"></i>
                {{ session('status') }}
            </div>
        @endif

        <x-workbench.dialog name="course-create">
            <form class="wb-modal-form" method="POST" action="{{ route('courses.store', $timetable) }}">
                @csrf
                <input type="hidden" name="_form" value="course-create">
                <input type="hidden" name="view_week" value="{{ $weekNumber }}">
                <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                <input type="hidden" name="view_month" value="{{ $monthValue }}">

                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title">添加课程</h2>
                        <p class="wb-modal-subtitle">一门课程可以添加多个上课时间段。</p>
                    </div>
                    <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                @if ($errors->any() && old('_form') === 'course-create')
                    <div class="wb-alert" role="alert">
                        <i data-lucide="triangle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div class="wb-modal-body">
                    <div class="wb-form-grid">
                        <label class="wb-field-group">
                            <span class="wb-label">课程名称</span>
                            <input class="wb-field" type="text" name="name" value="{{ old('name') }}" maxlength="120" required autofocus placeholder="例：高等数学">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">课程代码</span>
                            <input class="wb-field" type="text" name="code" value="{{ old('code') }}" maxlength="60" placeholder="选填">
                        </label>
                        <label class="wb-field-group wb-field-group--full">
                            <span class="wb-label">备注</span>
                            <textarea class="wb-textarea" name="notes" maxlength="2000" placeholder="作业、考试或其他提醒">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <x-workbench.meeting-list source="createMeetings" target="create" />
                </div>

                <div class="wb-modal-footer">
                    <button class="wb-btn" type="button" x-on:click="closeDialog()">取消</button>
                    <button class="wb-btn wb-btn--primary" type="submit">
                        <i data-lucide="plus"></i>保存课程
                    </button>
                </div>
            </form>
        </x-workbench.dialog>

        <x-workbench.dialog name="course-editor">
            <div class="wb-modal-header">
                <div>
                    <h2 class="wb-modal-title">编辑课程</h2>
                    <p class="wb-modal-subtitle">修改后会重新计算所有冲突与课程间隔。</p>
                </div>
                <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <form id="course-editor-form" class="wb-modal-form" method="POST" x-bind:action="courseEditor.action">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_form" value="course-editor">

                <div class="wb-modal-body">
                    <template x-if="courseEditor.occurrence">
                        <section class="wb-occurrence-panel" aria-label="本次课程状态">
                            <div class="min-w-0">
                                <div class="wb-occurrence-panel__title">
                                    <i data-lucide="calendar-days"></i>
                                    <span x-text="courseEditor.occurrence.summary"></span>
                                </div>
                                <span
                                    class="wb-occurrence-panel__state"
                                    x-bind:data-canceled="courseEditor.occurrence.isCanceled ? 'true' : 'false'"
                                    x-text="courseEditor.occurrence.isCanceled ? '本次已取消' : '本次正常上课'"
                                ></span>
                            </div>
                            <div class="wb-occurrence-panel__actions">
                                <button
                                    class="wb-btn"
                                    type="submit"
                                    form="course-occurrence-cancel-form"
                                    x-show="!courseEditor.occurrence.isCanceled"
                                >
                                    <i data-lucide="calendar-x-2"></i>取消本次
                                </button>
                                <button
                                    class="wb-btn"
                                    type="submit"
                                    form="course-occurrence-restore-form"
                                    x-show="courseEditor.occurrence.isCanceled"
                                >
                                    <i data-lucide="rotate-ccw"></i>恢复本次
                                </button>
                                <button class="wb-btn" type="button" x-on:click="openCancellationManager()">
                                    <i data-lucide="calendar-range"></i>批量管理
                                </button>
                            </div>
                        </section>
                    </template>

                    <div class="wb-form-grid">
                        <label class="wb-field-group">
                            <span class="wb-label">课程名称</span>
                            <input class="wb-field" type="text" name="name" x-model="courseEditor.name" maxlength="120" required>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">课程代码</span>
                            <input class="wb-field" type="text" name="code" x-model="courseEditor.code" maxlength="60">
                        </label>
                        <label class="wb-field-group wb-field-group--full">
                            <span class="wb-label">备注</span>
                            <textarea class="wb-textarea" name="notes" x-model="courseEditor.notes" maxlength="2000"></textarea>
                        </label>
                    </div>

                    <x-workbench.meeting-list source="courseEditor.meetings" target="course" />
                </div>
            </form>

            <form id="course-occurrence-cancel-form" method="POST" x-bind:action="courseEditor.occurrence?.cancelAction">
                @csrf
                <input type="hidden" name="weeks[]" x-bind:value="courseEditor.occurrence?.week">
            </form>

            <form id="course-occurrence-restore-form" method="POST" x-bind:action="courseEditor.occurrence?.restoreAction">
                @csrf
                @method('DELETE')
                <input type="hidden" name="weeks[]" x-bind:value="courseEditor.occurrence?.week">
            </form>

            <div class="wb-modal-footer wb-course-editor-footer justify-between">
                <div class="flex flex-wrap gap-2">
                    <form method="POST" x-bind:action="courseEditor.archiveAction" x-on:submit="confirm('暂时隐藏后，这门课程将不再显示或参与分析。确定继续吗？') || $event.preventDefault()">
                        @csrf
                        @method('PATCH')
                        <button class="wb-btn" type="submit">
                            <i data-lucide="eye-off"></i>暂时隐藏
                        </button>
                    </form>
                    <form method="POST" x-bind:action="courseEditor.destroyAction" x-on:submit="confirm('确定删除这门课程及其全部时间段吗？') || $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button class="wb-btn wb-btn--danger" type="submit">
                            <i data-lucide="trash-2"></i>删除课程
                        </button>
                    </form>
                </div>
                <div class="flex gap-2">
                    <button class="wb-btn" type="button" x-on:click="closeDialog()">取消</button>
                    <button class="wb-btn wb-btn--primary" type="submit" form="course-editor-form">
                        <i data-lucide="check"></i>保存修改
                    </button>
                </div>
            </div>
        </x-workbench.dialog>

        <x-workbench.dialog name="occurrence-cancellations" size="sm">
            <form class="wb-modal-form" method="POST" x-bind:action="cancellationEditor.syncAction">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="occurrence-cancellations">

                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title">批量管理停课</h2>
                        <p class="wb-modal-subtitle" x-text="cancellationEditor.summary"></p>
                    </div>
                    <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                @if ($errors->any() && old('_form') === 'occurrence-cancellations')
                    <div class="wb-alert" role="alert">
                        <i data-lucide="triangle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div class="wb-modal-body">
                    <div class="wb-cancellation-toolbar">
                        <span><strong x-text="cancellationEditor.weeks.length"></strong> 次已取消</span>
                        <button class="wb-btn" type="button" x-on:click="cancellationEditor.weeks = []">
                            <i data-lucide="rotate-ccw"></i>全部恢复
                        </button>
                    </div>
                    <div class="wb-cancellation-grid">
                        <template x-for="option in cancellationEditor.options" x-bind:key="option.week">
                            <label class="wb-cancellation-option">
                                <input
                                    type="checkbox"
                                    name="weeks[]"
                                    x-bind:value="String(option.week)"
                                    x-model="cancellationEditor.weeks"
                                >
                                <span class="wb-cancellation-option__check"><i data-lucide="check"></i></span>
                                <span class="min-w-0">
                                    <strong x-text="option.label"></strong>
                                    <small x-text="option.date"></small>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="wb-modal-footer">
                    <button class="wb-btn" type="button" x-on:click="openDialog('course-editor')">返回课程</button>
                    <button class="wb-btn wb-btn--primary" type="submit">
                        <i data-lucide="check"></i>保存停课安排
                    </button>
                </div>
            </form>
        </x-workbench.dialog>

        @if ($archivedCourses->isNotEmpty())
            <x-workbench.dialog name="archived-courses" size="sm">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title">已隐藏课程</h2>
                        <p class="wb-modal-subtitle">{{ $timetable->name }}</p>
                    </div>
                    <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <div class="wb-modal-body">
                    <div class="wb-archived-course-list">
                        @foreach ($archivedCourses as $archivedCourse)
                            <div class="wb-archived-course">
                                <span class="wb-archived-course__icon" aria-hidden="true"><i data-lucide="eye-off"></i></span>
                                <span class="min-w-0 flex-1">
                                    <strong>{{ $archivedCourse->name }}</strong>
                                    <small>{{ $archivedCourse->code ?: $archivedCourse->meetings->count().' 个时间段' }}</small>
                                </span>
                                <form method="POST" action="{{ route('courses.restore', [$timetable, $archivedCourse]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="wb-btn" type="submit">
                                        <i data-lucide="rotate-ccw"></i>恢复
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="wb-modal-footer">
                    <button class="wb-btn" type="button" x-on:click="closeDialog()">关闭</button>
                </div>
            </x-workbench.dialog>
        @endif

        <x-workbench.dialog name="export-image">
            <div class="wb-modal-header">
                <div>
                    <h2 class="wb-modal-title">导出高清图片</h2>
                    <p class="wb-modal-subtitle">{{ $exportLabel }} · {{ $isMonthView ? '月课表' : '周课表' }}</p>
                </div>
                <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="wb-modal-body wb-export-body">
                <div class="wb-export-toolbar">
                    <span class="wb-export-toolbar__label"><i data-lucide="palette"></i>图片主题</span>
                    <div class="wb-export-themes" role="group" aria-label="选择导出图片主题">
                        <template x-for="theme in exportThemes" x-bind:key="theme.key">
                            <button
                                class="wb-export-theme"
                                type="button"
                                x-bind:aria-pressed="exportTheme === theme.key"
                                x-on:click="setExportTheme(theme.key)"
                            >
                                <span
                                    class="wb-export-theme__swatch"
                                    x-bind:style="`--theme-accent: ${theme.accent}; --theme-soft: ${theme.accentSoft}`"
                                    aria-hidden="true"
                                ></span>
                                <span x-text="theme.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="wb-export-preview" x-bind:data-ready="exportReady ? 'true' : 'false'">
                    <canvas
                        class="wb-export-canvas"
                        x-ref="exportCanvas"
                        aria-label="{{ $exportLabel }}课表图片预览"
                    ></canvas>
                    <div class="wb-export-loading" x-show="!exportReady" aria-live="polite">
                        <span class="wb-export-spinner" aria-hidden="true"></span>
                        正在排版图片
                    </div>
                </div>

                <div class="wb-export-meta">
                    <span>{{ $isMonthView ? '2400 × 1800' : '2400 × 1600' }} PNG</span>
                    <span>课程颜色、地点和状态均会保留</span>
                </div>
            </div>

            <div class="wb-modal-footer">
                <button class="wb-btn" type="button" x-on:click="closeDialog()">取消</button>
                <button class="wb-btn wb-btn--primary" type="button" x-on:click="downloadExportImage()" x-bind:disabled="!exportReady || exportingImage">
                    <i data-lucide="download"></i>
                    <span x-text="exportingImage ? '正在生成…' : '下载高清 PNG'"></span>
                </button>
            </div>
        </x-workbench.dialog>

        <x-workbench.dialog name="share" size="sm">
            <div class="wb-modal-header">
                <div>
                    <h2 class="wb-modal-title">分享课表</h2>
                    <p class="wb-modal-subtitle">公开链接仅提供只读周视图，可随时撤销。</p>
                </div>
                <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭">
                    <i data-lucide="x"></i>
                </button>
            </div>

            @if ($errors->any() && old('_form') === 'share')
                <div class="wb-alert" role="alert">
                    <i data-lucide="triangle-alert"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div class="wb-modal-body">
                @if ($newShareUrl)
                    <div class="rounded-md border border-blue-200 bg-blue-50 p-3">
                        <p class="text-sm font-semibold text-blue-900">新链接已创建，请现在保存</p>
                        <p class="mt-1 text-xs leading-5 text-blue-700">为保护链接安全，关闭本页后不会再次显示完整地址。</p>
                        <div class="share-link">
                            <span class="share-link__value">{{ $newShareUrl }}</span>
                            <button class="wb-btn" type="button" x-on:click="copyShare(@js($newShareUrl))">
                                <i data-lucide="copy" x-show="!shareCopied"></i>
                                <i data-lucide="check" x-show="shareCopied"></i>
                                <span x-text="shareCopied ? '已复制' : '复制'"></span>
                            </button>
                        </div>
                    </div>
                @endif

                <form id="share-create-form" class="mt-5" method="POST" action="{{ route('shares.store', $timetable) }}">
                    @csrf
                    <input type="hidden" name="_form" value="share">
                    <div class="wb-form-grid">
                        <label class="wb-field-group wb-field-group--full">
                            <span class="wb-label">链接备注</span>
                            <input class="wb-field" type="text" name="label" value="{{ old('label') }}" maxlength="100" placeholder="例：发给学习小组">
                        </label>
                        <label class="wb-field-group wb-field-group--full">
                            <span class="wb-label">失效时间</span>
                            <input class="wb-field" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
                            <span class="wb-help">留空表示一直有效，最长可设置为一年。</span>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">访问密码</span>
                            <input class="wb-field" type="password" name="password" minlength="6" maxlength="100" autocomplete="new-password" placeholder="选填，至少 6 位">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">确认密码</span>
                            <input class="wb-field" type="password" name="password_confirmation" minlength="6" maxlength="100" autocomplete="new-password">
                        </label>
                    </div>
                </form>

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h3 class="text-sm font-bold text-slate-800">分享记录</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($shareList as $share)
                            @php
                                $isRevoked = (bool) $share->revoked_at;
                                $isDisabled = (bool) $share->disabled_by_admin_at;
                                $isExpired = $share->expires_at?->isPast() ?? false;
                                $canRevoke = ! $isRevoked;
                                $shareStatus = $isRevoked ? '已撤销' : ($isDisabled ? '已停用' : ($isExpired ? '已过期' : '有效'));
                            @endphp
                            <div class="flex items-center gap-3 rounded-md border border-slate-200 px-3 py-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600">
                                    <i data-lucide="link-2" class="h-5 w-5"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-800">{{ $share->label ?: '未命名分享' }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $shareStatus }} · 查看 {{ (int) $share->views_count }} 次</span>
                                </span>
                                @if ($canRevoke)
                                    <form method="POST" action="{{ route('shares.destroy', [$timetable, $share]) }}" x-on:submit="confirm('撤销后，该链接会立即失效。确定继续吗？') || $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="wb-btn wb-btn--danger !h-8 !px-2.5" type="submit">撤销</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 px-3 py-4 text-center text-sm text-slate-500">还没有创建分享链接</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="wb-modal-footer">
                <button class="wb-btn" type="button" x-on:click="closeDialog()">关闭</button>
                <button class="wb-btn wb-btn--primary" type="submit" form="share-create-form">
                    <i data-lucide="share-2"></i>创建新链接
                </button>
            </div>
        </x-workbench.dialog>

        <x-workbench.dialog name="timetable-create" size="sm">
            <form
                method="POST"
                action="{{ route('timetables.store') }}"
                x-data="timetableTermRange({
                    startDate: @js(old('_form') === 'timetable-create' ? old('term_start_date') : null),
                    endDate: @js(old('_form') === 'timetable-create' ? old('term_end_date') : null),
                    weekCount: @js(old('_form') === 'timetable-create' ? old('week_count', 18) : 18),
                })"
            >
                @csrf
                <input type="hidden" name="_form" value="timetable-create">
                <div class="wb-modal-header">
                    <div>
                        <h2 class="wb-modal-title">新建课表方案</h2>
                        <p class="wb-modal-subtitle">为不同学期或安排建立独立方案。</p>
                    </div>
                    <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭"><i data-lucide="x"></i></button>
                </div>
                @if ($errors->any() && old('_form') === 'timetable-create')
                    <div class="wb-alert" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $errors->first() }}</span></div>
                @endif
                <div class="wb-modal-body">
                    <div class="wb-form-grid">
                        <label class="wb-field-group">
                            <span class="wb-label">方案名称</span>
                            <input class="wb-field" type="text" name="name" value="{{ old('name', '主课表') }}" maxlength="80" required autofocus>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">学期名称</span>
                            <input class="wb-field" type="text" name="term_name" value="{{ old('term_name') }}" maxlength="100" placeholder="例：2026 秋季">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">开学日期</span>
                            <input class="wb-field" type="date" name="term_start_date" x-model="startDate" x-on:change="startChanged()">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">学期截止日期</span>
                            <input class="wb-field" type="date" name="term_end_date" x-model="endDate" x-on:change="syncWeeksFromEnd()" x-bind:min="startDate || null">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">总周数</span>
                            <input class="wb-field" type="number" name="week_count" x-model.number="weekCount" x-on:change="syncEndFromWeeks()" min="1" max="30" required>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">临近提醒阈值</span>
                            <select class="wb-select" name="near_threshold_minutes" required>
                                @foreach ([15, 30, 45, 60] as $minutes)
                                    <option value="{{ $minutes }}" @selected((int) old('near_threshold_minutes', 30) === $minutes)>{{ $minutes }} 分钟</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="wb-field-group wb-field-group--full flex items-center gap-2">
                            <input class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                            <span class="text-sm text-slate-700">设为默认课表</span>
                        </label>
                    </div>
                </div>
                <div class="wb-modal-footer">
                    <button class="wb-btn" type="button" x-on:click="closeDialog()">取消</button>
                    <button class="wb-btn wb-btn--primary" type="submit"><i data-lucide="plus"></i>创建课表</button>
                </div>
            </form>
        </x-workbench.dialog>

        <x-workbench.dialog name="timetable-settings" size="sm">
            <div class="wb-modal-header">
                <div>
                    <h2 class="wb-modal-title">课表设置</h2>
                    <p class="wb-modal-subtitle">调整学期范围与临近课程提醒阈值。</p>
                </div>
                <button class="wb-icon-btn" type="button" x-on:click="closeDialog()" title="关闭" aria-label="关闭"><i data-lucide="x"></i></button>
            </div>
            <form
                id="timetable-settings-form"
                method="POST"
                action="{{ route('timetables.update', $timetable) }}"
                x-data="timetableTermRange({
                    startDate: @js(old('_form') === 'timetable-settings' ? old('term_start_date') : $timetable->term_start_date?->format('Y-m-d')),
                    endDate: @js(old('_form') === 'timetable-settings' ? old('term_end_date') : $termEndDate?->format('Y-m-d')),
                    weekCount: @js(old('_form') === 'timetable-settings' ? old('week_count', $weekCount) : $weekCount),
                })"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="_form" value="timetable-settings">
                <div class="wb-modal-body">
                    <div class="wb-form-grid">
                        <label class="wb-field-group">
                            <span class="wb-label">方案名称</span>
                            <input class="wb-field" type="text" name="name" value="{{ old('_form') === 'timetable-settings' ? old('name') : $timetable->name }}" maxlength="80" required>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">学期名称</span>
                            <input class="wb-field" type="text" name="term_name" value="{{ old('_form') === 'timetable-settings' ? old('term_name') : $timetable->term_name }}" maxlength="100">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">开学日期</span>
                            <input class="wb-field" type="date" name="term_start_date" x-model="startDate" x-on:change="startChanged()">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">学期截止日期</span>
                            <input class="wb-field" type="date" name="term_end_date" x-model="endDate" x-on:change="syncWeeksFromEnd()" x-bind:min="startDate || null">
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">总周数</span>
                            <input class="wb-field" type="number" name="week_count" x-model.number="weekCount" x-on:change="syncEndFromWeeks()" min="1" max="30" required>
                        </label>
                        <label class="wb-field-group">
                            <span class="wb-label">临近提醒阈值</span>
                            <select class="wb-select" name="near_threshold_minutes" required>
                                @foreach ([15, 30, 45, 60] as $minutes)
                                    <option value="{{ $minutes }}" @selected((int) (old('_form') === 'timetable-settings' ? old('near_threshold_minutes') : $timetable->near_threshold_minutes) === $minutes)>{{ $minutes }} 分钟</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="wb-field-group wb-field-group--full flex items-center gap-2">
                            <input class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" type="checkbox" name="is_default" value="1" @checked(old('_form') === 'timetable-settings' ? old('is_default') : $timetable->is_default)>
                            <span class="text-sm text-slate-700">设为默认课表</span>
                        </label>
                    </div>
                </div>
            </form>
            <div class="wb-modal-footer justify-between">
                <form method="POST" action="{{ route('timetables.destroy', $timetable) }}" x-on:submit="confirm('删除后，课程和分享链接都无法恢复。确定删除吗？') || $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button class="wb-btn wb-btn--danger" type="submit"><i data-lucide="trash-2"></i>删除课表</button>
                </form>
                <div class="flex gap-2">
                    <button class="wb-btn" type="button" x-on:click="closeDialog()">取消</button>
                    <button class="wb-btn wb-btn--primary" type="submit" form="timetable-settings-form"><i data-lucide="check"></i>保存设置</button>
                </div>
            </div>
        </x-workbench.dialog>
    </div>
</x-app-layout>
