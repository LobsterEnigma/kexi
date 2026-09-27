<x-academic-shell :timetable="$timetable" title="确认导入安排" subtitle="检查时间和用途，再把需要的安排添加到课隙。">
    <x-slot name="actions"><a class="wb-btn" href="{{ route('calendar.index',$timetable) }}"><i data-lucide="chevron-left"></i>重新选择文件</a></x-slot>
    <div class="calendar-preview-meta"><span>{{ $payload['from'] }} — {{ $payload['to'] }}</span><span>{{ count($payload['rows']) }} 次安排</span><span>{{ count($duplicates) }} 项已导入</span></div>
    @if(!empty($payload['notices']))<div class="calendar-detection-notes">@foreach($payload['notices'] as $notice)<p><i data-lucide="info"></i>{{ $notice }}</p>@endforeach<p>下方时间统一显示为 {{ $timetable->timezone }}；全天安排保留原日期。</p></div>@endif
    @if($payload['warnings'])<section class="calendar-warnings" role="status"><h2><i data-lucide="triangle-alert"></i>这些内容需要注意</h2><ul>@foreach($payload['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></section>@endif
    <p class="calendar-note"><i data-lucide="circle-help"></i><span>课程会进入当前课表；学业活动保留起止时间；「提交截止」使用预览中的开始时刻，全天事项使用最后一天 23:59。个人安排默认私密。ICS 不会自动判断课程类型，请确认归类。重复安排已展开为单次记录，不与原日历同步。</span></p>
    @if(count($payload['rows']))
    <form method="POST" action="{{ route('calendar.import',[$timetable,$batch]) }}" x-data="calendarImport" x-on:change="refresh()">
        @csrf
        <div class="calendar-selection-bar"><div><button class="wb-btn" type="button" x-on:click="selectAll(true)">选择全部可导入项</button><button class="wb-btn" type="button" x-on:click="selectAll(false)">清空选择</button></div><span>已导入项自动跳过，不覆盖原内容</span></div>
        <label class="calendar-bulk-kind"><span>将已选项目统一设为</span><select class="wb-select" aria-label="批量归类" x-on:change="setKind($el.value); $el.value = ''"><option value="">选择类型…</option><option value="personal">个人安排</option><option value="course">课程</option><option value="event">学业活动 / 考试</option><option value="deadline">提交截止</option></select></label><p class="academic-field-help mb-3" role="status" x-text="notice"></p>
        <div class="calendar-preview-list">
        @foreach($payload['rows'] as $index=>$row)
            @php
                $duplicate = in_array($row['fingerprint'],$duplicates,true);
                $start = \Carbon\CarbonImmutable::parse($row['starts_at'])->timezone($timetable->timezone);
                $end = \Carbon\CarbonImmutable::parse($row['ends_at'])->timezone($timetable->timezone);
                if($row['all_day']) { $start = \Carbon\CarbonImmutable::parse($row['date_start'],$timetable->timezone); $end = \Carbon\CarbonImmutable::parse($row['date_end'],$timetable->timezone); }
                $instant = $end->lte($start);
                $defaultKind = $row['deadline'] || $instant ? 'deadline' : 'personal';
            @endphp
            <article class="calendar-preview-item {{ $duplicate?'is-duplicate':'' }}">
                <label class="calendar-preview-select"><input type="checkbox" name="selected[]" value="{{ $index }}" @disabled($duplicate) @checked(!$duplicate && in_array((string)$index,array_map('strval',old('selected',array_keys($payload['rows']))),true))><span class="sr-only">导入 {{ $row['title'] }}</span></label>
                <div class="calendar-preview-content">
                    <h2>{{ $row['title'] }} @if($duplicate)<small>已导入</small>@endif</h2>
                    <p><i data-lucide="clock-3"></i><span>
                        @if($row['all_day'])
                            {{ $start->format('Y/m/d') }}
                            @if($end->subDay()->toDateString()!==$start->toDateString()) — {{ $end->subDay()->format('Y/m/d') }} @endif
                            · 全天
                        @else
                            {{ $start->format('Y/m/d H:i') }}
                            @if(!$instant) — {{ $end->format($end->isSameDay($start)?'H:i':'Y/m/d H:i') }} @endif
                        @endif
                    </span></p>
                    @if($row['location'])<p><i data-lucide="map-pin"></i>{{ $row['location'] }}</p>@endif
                    @if($row['notes'])<details><summary>查看备注</summary><p class="calendar-import-notes">{{ $row['notes'] }}</p></details>@endif
                </div>
                <label class="calendar-kind"><span class="wb-label">导入为</span><select class="wb-select" name="kinds[{{ $index }}]" @disabled($duplicate)><option value="personal" @disabled($instant) @selected(old('kinds.'.$index,$defaultKind)==='personal')>个人安排</option><option value="course" @disabled($row['all_day']||$instant||!$start->isSameDay($end)||!$timetable->term_start_date) @selected(old('kinds.'.$index,$defaultKind)==='course')>课程</option><option value="event" @disabled($row['all_day']||$instant) @selected(old('kinds.'.$index,$defaultKind)==='event')>学业活动 / 考试</option><option value="deadline" @selected(old('kinds.'.$index,$defaultKind)==='deadline')>提交截止</option></select></label>
            </article>
        @endforeach
        </div>
        <div class="calendar-import-footer"><p>已选择 <strong x-text="count"></strong> 次安排<span>预览 30 分钟内有效</span></p><button class="wb-btn wb-btn--primary" type="submit" x-bind:disabled="count===0">确认导入<i data-lucide="check"></i></button></div>
    </form>
    @else <div class="calendar-empty">此日期范围没有可导入的安排。请检查范围，或根据上方提示调整文件。</div>@endif
</x-academic-shell>
