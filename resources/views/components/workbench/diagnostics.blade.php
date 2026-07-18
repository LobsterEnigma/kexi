@props([
    'analysis' => [],
    'idPrefix' => 'diagnostics',
])

@php
    $summary = data_get($analysis, 'summary', []);
    $issues = collect(data_get($analysis, 'issues', []));

    $asCount = static function ($value): int {
        if (is_countable($value)) {
            return count($value);
        }

        return max(0, (int) $value);
    };

    $conflictCount = $asCount(data_get($summary, 'conflict_count', data_get($summary, 'conflicts', 0)));
    $nearCount = $asCount(data_get($summary, 'near_count', data_get($summary, 'near', 0)));
    $nearestGap = data_get($summary, 'closest_gap', data_get($summary, 'nearest_gap', data_get($summary, 'min_gap')));
    $nearThreshold = max(1, (int) data_get($analysis, 'policy.near_threshold_minutes', 30));
    $weekdayNames = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
@endphp

<section class="diagnostics-panel" data-diagnostics-panel aria-label="冲突与间隔诊断">
    <h2 class="diagnostics-title">冲突与间隔</h2>

    <div class="diagnostics-summary">
        <button
            class="diagnostics-summary__row diagnostics-summary__row--danger"
            type="button"
            x-on:click="selectDiagnosticFilter('conflict', $event.currentTarget, {{ $conflictCount }})"
            x-bind:aria-pressed="diagnosticFilter === 'conflict'"
            aria-controls="{{ $idPrefix }}-issue-list"
            @disabled($conflictCount === 0)
        >
            <span class="diagnostics-summary__icon diagnostics-summary__icon--danger">
                <i data-lucide="triangle-alert"></i>
            </span>
            <span><strong>{{ $conflictCount }}</strong>处时间冲突</span>
            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-600"></i>
        </button>

        <button
            class="diagnostics-summary__row diagnostics-summary__row--warning"
            type="button"
            x-on:click="selectDiagnosticFilter('near', $event.currentTarget, {{ $nearCount }})"
            x-bind:aria-pressed="diagnosticFilter === 'near'"
            aria-controls="{{ $idPrefix }}-issue-list"
            @disabled($nearCount === 0)
        >
            <span class="diagnostics-summary__icon diagnostics-summary__icon--warning">
                <i data-lucide="clock-3"></i>
            </span>
            <span><strong>{{ $nearCount }}</strong>组临近课程组合</span>
            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-600"></i>
        </button>

        <button
            class="diagnostics-summary__row diagnostics-summary__row--success"
            type="button"
            x-on:click="selectDiagnosticFilter('all', $event.currentTarget)"
            x-bind:aria-pressed="diagnosticFilter === 'all'"
            aria-controls="{{ $idPrefix }}-issue-list"
        >
            <span class="diagnostics-summary__icon diagnostics-summary__icon--success">
                <i data-lucide="clock-3"></i>
            </span>
            <span>
                最近间隔
                <strong>{{ is_numeric($nearestGap) ? (int) $nearestGap : '—' }}</strong>分钟
            </span>
            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-600"></i>
        </button>
    </div>

    <div class="diagnostics-section" data-diagnostic-list>
        <h3 class="diagnostics-section__title">问题列表</h3>

        @if ($issues->isEmpty())
            <div class="issue-list">
                <div class="issue-empty">
                    <i data-lucide="circle-check-big"></i>
                    <span>第 {{ request()->integer('week', 1) }} 周没有发现冲突或临近课程</span>
                </div>
            </div>
        @else
            <div class="issue-list" id="{{ $idPrefix }}-issue-list">
                @foreach ($issues as $issue)
                    @php
                        $type = (string) data_get($issue, 'type', data_get($issue, 'status', 'info'));
                        $isConflict = in_array($type, ['conflict', 'overlap'], true);
                        $isNear = $type === 'near';
                        $title = data_get($issue, 'title', data_get($issue, 'message'));
                        $detail = data_get($issue, 'detail', data_get($issue, 'description'));
                        $gap = data_get($issue, 'minutes', data_get($issue, 'gap', data_get($issue, 'nearest_gap')));
                        $weeks = data_get($issue, 'affected_weeks', data_get($issue, 'weeks'));
                        $weekday = (int) data_get($issue, 'weekday', 0);
                        $meetingDetails = collect(data_get($issue, 'meeting_details', []));
                        $issueKey = (string) data_get($issue, 'id', $idPrefix.'-'.$loop->index);
                        $issueDomId = $idPrefix.'-issue-detail-'.($loop->index + 1);

                        if (! $title) {
                            $title = $isConflict ? '课程时间发生重叠' : ($isNear ? '两门课程间隔较短' : '课程安排提醒');
                        }

                        if ($meetingDetails->isEmpty()) {
                            $meetingDetails = collect(data_get($issue, 'course_names', []))
                                ->map(fn ($name) => ['course_name' => $name]);
                        }

                        $intervalText = is_numeric($gap)
                            ? ($isConflict ? '重叠 '.abs((int) $gap).' 分钟' : '间隔 '.(int) $gap.' 分钟')
                            : null;
                        $weekText = $weeks
                            ? '第 '.(is_array($weeks) ? implode('、', $weeks) : $weeks).' 周'
                            : null;
                        $detail = implode(' · ', array_filter([
                            $weekdayNames[$weekday] ?? null,
                            $intervalText,
                            $weekText,
                        ]));
                    @endphp

                    <article
                        class="issue-item"
                        x-show="diagnosticFilter === 'all' || diagnosticFilter === '{{ $isConflict ? 'conflict' : ($isNear ? 'near' : 'info') }}'"
                    >
                        <button
                            class="issue-row w-full text-left"
                            type="button"
                            x-on:click="toggleDiagnostic(@js($issueKey))"
                            x-bind:aria-expanded="expandedDiagnostic === @js($issueKey)"
                            aria-controls="{{ $issueDomId }}"
                        >
                            <span class="issue-row__icon issue-row__icon--{{ $isConflict ? 'danger' : ($isNear ? 'warning' : 'info') }}">
                                <i data-lucide="{{ $isConflict ? 'triangle-alert' : ($isNear ? 'clock-3' : 'file-text') }}"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="issue-row__title">{{ $title }}</span>
                                @if ($detail)
                                    <span class="issue-row__detail">{{ $detail }}</span>
                                @endif
                            </span>
                            <i
                                data-lucide="chevron-right"
                                class="issue-row__chevron h-4 w-4 text-slate-600"
                                x-bind:class="{ 'issue-row__chevron--open': expandedDiagnostic === @js($issueKey) }"
                            ></i>
                        </button>

                        <div
                            class="issue-detail-panel"
                            id="{{ $issueDomId }}"
                            x-cloak
                            x-show="expandedDiagnostic === @js($issueKey)"
                        >
                            @if ($detail)
                                <p class="issue-detail-meta">{{ $detail }}</p>
                            @endif
                            <div class="issue-course-list">
                                @foreach ($meetingDetails as $meetingDetail)
                                    @php
                                        $courseName = (string) data_get($meetingDetail, 'course_name', '未命名课程');
                                        $courseCode = (string) data_get($meetingDetail, 'course_code', '');
                                        $startsAt = (string) data_get($meetingDetail, 'starts_at', '');
                                        $endsAt = (string) data_get($meetingDetail, 'ends_at', '');
                                        $meetingLabel = (string) data_get($meetingDetail, 'label', '');
                                        $teacher = (string) data_get($meetingDetail, 'teacher', '');
                                        $location = (string) data_get($meetingDetail, 'location', '');
                                        $timeText = $startsAt && $endsAt ? $startsAt.'–'.$endsAt : '';
                                        $secondaryMeta = implode(' · ', array_filter([$teacher, $location]));
                                    @endphp
                                    <div class="issue-course">
                                        <p class="issue-course__name">
                                            {{ $courseName }}@if ($courseCode)<span class="font-normal text-slate-500"> · {{ $courseCode }}</span>@endif
                                        </p>
                                        @if ($timeText || $meetingLabel)
                                            <p class="issue-course__meta">{{ implode(' · ', array_filter([$timeText, $meetingLabel])) }}</p>
                                        @endif
                                        @if ($secondaryMeta)
                                            <p class="issue-course__meta">{{ $secondaryMeta }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="diagnostics-section">
        <h3 class="diagnostics-section__title">图例说明</h3>
        <div class="legend-list">
            <div class="legend-item">
                <i data-lucide="triangle-alert" class="status-danger"></i>
                <span>课程冲突</span>
            </div>
            <div class="legend-item">
                <i data-lucide="clock-3" class="status-warning"></i>
                <span>间隔低于当前阈值</span>
            </div>
            <div class="legend-item">
                <i data-lucide="circle-check-big" class="text-lime-700"></i>
                <span>宽松 {{ $nearThreshold + 1 }}–{{ $nearThreshold * 2 }} 分钟</span>
            </div>
            <div class="legend-item">
                <i data-lucide="circle-check-big" class="text-green-700"></i>
                <span>宽松 {{ $nearThreshold * 2 + 1 }}–{{ $nearThreshold * 4 }} 分钟</span>
            </div>
            <div class="legend-item">
                <i data-lucide="circle-check-big" class="text-emerald-800"></i>
                <span>宽松 {{ $nearThreshold * 4 }}+ 分钟</span>
            </div>
        </div>
    </div>
</section>
