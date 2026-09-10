@php
    $editing = $event->exists;
    $timezone = old('timezone', $row['timezone'] ?? $event->timezone);
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) $timezone = $event->timezone;
    $start = $row ? $row['starts_at']->timezone($timezone) : now($timezone)->startOfHour()->addHour();
    $allDay = (bool) old('all_day', $row['all_day'] ?? false);
    $end = $row ? $row['ends_at']->timezone($timezone) : $start->copy()->addHour();
    if ($row && $row['all_day']) $end = $end->subDay();
    $selectedDays = array_map('intval', old('weekdays', $event->weekdays ?: [$start->isoWeekday()]) ?? []);
    $scope = old('scope', $occurrence && $event->repeat==='weekly' ? 'one' : 'all');
    $selectedColor = old('color', $row['color'] ?? $event->color);
    $selectedColor = is_string($selectedColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $selectedColor) ? strtolower($selectedColor) : $event->color;
    $colorPresets = ['#168575'=>'松绿', '#2f67c7'=>'湖蓝', '#7257cf'=>'鸢紫', '#bd4f76'=>'玫瑰', '#247ba0'=>'青蓝', '#c06135'=>'陶橙', '#558b2f'=>'草绿', '#a16207'=>'琥珀'];
    $formConfig = [
        'allDay' => $allDay, 'repeat' => old('repeat', $event->repeat), 'scope' => $scope,
        'timezone' => $timezone, 'detectTimezone' => !$editing && !session()->hasOldInput(),
        'startLocal' => old('start_local', old('start_date', $start->format('Y-m-d')).'T'.(old('start_time') ?: $start->format('H:i'))),
        'endLocal' => old('end_local', old('end_date', $end->format('Y-m-d')).'T'.(old('end_time') ?: $end->format('H:i'))),
    ];
    foreach (['startLocal', 'endLocal'] as $field) {
        if (!is_string($formConfig[$field])) $formConfig[$field] = '';
    }
@endphp
<x-personal-shell :timetable="$timetable" :title="$editing?'编辑个人安排':'添加个人安排'">
    <x-slot name="actions"><a class="wb-btn" href="{{ route('personal-events.index',['timetable'=>$timetable?->id,'month'=>$start->format('Y-m')]) }}">返回安排</a></x-slot>
    @if($conflicts)<div class="personal-conflicts"><strong>这段时间已有其他安排</strong><p>{{ implode('、',array_slice($conflicts,0,5)) }}{{ count($conflicts)>5?'等':'' }}。可以保留重叠，请自行确认时间。</p></div>@endif
    @if($row && $row['canceled'])<div class="personal-conflicts"><strong>这次活动已取消</strong><p>保存修改会恢复这次安排，也可以在下方直接恢复。</p></div>@endif
    <form class="wb-records-panel" method="POST" action="{{ $editing?route('personal-events.update',$event):route('personal-events.store') }}" x-data="personalEventForm(@js($formConfig))">
        @csrf @if($editing) @method('PATCH') @endif
        <input type="hidden" name="timetable" value="{{ $timetable?->id }}"><input type="hidden" name="occurrence" value="{{ $occurrence }}">
        @if($occurrence && $event->repeat==='weekly')<div class="academic-form-section"><h2>修改哪些安排？</h2><div class="personal-scope"><label><input type="radio" name="scope" value="one" x-model="scope">仅 {{ $occurrence }} 这一次</label><label><input type="radio" name="scope" value="future" x-model="scope">这次及后续</label></div><p class="academic-field-help mt-3">修改后续会保留过去记录，并重新设置后续重复与例外。<a class="text-blue-600" href="{{ route('personal-events.edit',['event'=>$event,'timetable'=>$timetable?->id]) }}">编辑整个系列</a></p></div>@else<input type="hidden" name="scope" value="all">@endif
        <div class="academic-form-section"><h2><i data-lucide="calendar-days"></i>活动内容</h2><div class="academic-form-grid">
            <label class="wb-field-group academic-span"><span class="wb-label">活动名称</span><input class="wb-field" name="title" required maxlength="160" placeholder="例如：篮球、摄影社、周末聚餐" value="{{ old('title',$row['title']??'') }}"></label>
            <label class="wb-field-group"><span class="wb-label">分类（可自定义）</span><input class="wb-field" name="category" required maxlength="40" list="personal-categories" value="{{ old('category',$row['category']??$event->category) }}"><datalist id="personal-categories">@foreach(['运动','社团','聚会','娱乐','兼职','生活','休息'] as $category)<option value="{{ $category }}">@endforeach</datalist></label>
            <fieldset class="wb-field-group personal-palette" x-data="{ color: @js($selectedColor) }">
                <legend class="wb-label">活动颜色</legend>
                <div class="personal-palette__choices" role="group" aria-label="预设活动颜色">
                    @foreach($colorPresets as $hex=>$colorName)
                        <button type="button" class="personal-palette__dot" style="--swatch: {{ $hex }}" aria-label="{{ $colorName }}" title="{{ $colorName }}" x-bind:aria-pressed="color === '{{ $hex }}'" x-on:click="color = '{{ $hex }}'"><span></span><i data-lucide="check" aria-hidden="true"></i></button>
                    @endforeach
                    <label class="personal-palette__custom" title="自定义颜色">
                        <i data-lucide="pipette" aria-hidden="true"></i><span>自定义</span>
                        <input type="color" name="color" aria-label="自定义活动颜色" value="{{ $selectedColor }}" x-model="color">
                    </label>
                </div>
                <div class="personal-palette__preview"><span class="personal-palette__sample" x-bind:style="{ '--swatch': color }"><i></i>活动预览</span><span>用于日历中的活动标记</span></div>
            </fieldset>
            <label class="wb-field-group academic-span"><span class="wb-label">地点</span><input class="wb-field" name="location" maxlength="200" value="{{ old('location',$row['location']??'') }}" placeholder="选填，例如体育馆、咖啡店"></label>
        </div></div>
        <div class="academic-form-section"><h2><i data-lucide="clock-3"></i>什么时候参加？</h2><input type="hidden" name="all_day" value="0"><label class="personal-checkbox mb-5"><input type="checkbox" name="all_day" value="1" x-model="allDay">全天安排</label><div class="academic-form-grid">
            <label class="wb-field-group" x-show="!allDay"><span class="wb-label">开始日期与时间</span><input class="wb-field personal-datetime" name="start_local" type="datetime-local" x-model="startLocal" x-on:change="startChanged()" x-bind:disabled="allDay" x-bind:required="!allDay"></label>
            <label class="wb-field-group" x-show="!allDay"><span class="wb-label">结束日期与时间</span><input class="wb-field personal-datetime" name="end_local" type="datetime-local" x-model="endLocal" x-bind:disabled="allDay" x-bind:required="!allDay"></label>
            <label class="wb-field-group" x-show="allDay" x-cloak><span class="wb-label">开始日期</span><input class="wb-field" name="start_date" type="date" x-model="startDate" x-on:change="startChanged()" x-bind:disabled="!allDay" x-bind:required="allDay"></label>
            <label class="wb-field-group" x-show="allDay" x-cloak><span class="wb-label">结束日期（包含这一天）</span><input class="wb-field" name="end_date" type="date" x-model="endDate" x-bind:disabled="!allDay" x-bind:required="allDay"></label>
            <p class="academic-field-help academic-span">跨天活动可直接选择次日结束；修改开始时间时，若结束已早于开始，会同步顺延结束时间。</p>
            <label class="wb-field-group academic-span"><span class="wb-label">活动时区</span><select class="wb-select" name="timezone" x-ref="timezone" x-model="timezone" x-on:change="zoneNotice = '已手动选择时区；上方日期与时间按此时区保存。'">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected($timezone===$zone)>{{ $zone }}</option>@endforeach</select><span class="academic-field-help" x-show="zoneNotice" x-text="zoneNotice"></span><span class="academic-field-help">按活动当地时间填写，每周重复保持当地钟点。已有安排保留保存时的时区，修改时区不会自动换算上方时间。</span></label>
        </div></div>
        <div class="academic-form-section" x-show="scope !== 'one'"><h2><i data-lucide="repeat"></i>是否重复？</h2><div class="academic-form-grid">
            <label class="wb-field-group"><span class="wb-label">重复方式</span><select class="wb-select" name="repeat" x-model="repeat"><option value="none">仅一次</option><option value="weekly">每周重复</option></select></label>
            <label class="wb-field-group" x-show="repeat==='weekly'"><span class="wb-label">重复到哪天（含当天）</span><input class="wb-field" type="date" name="repeat_until" value="{{ old('repeat_until',$event->repeat_until?->toDateString()??$start->copy()->addMonths(3)->format('Y-m-d')) }}"></label>
            <div class="academic-span personal-weekdays" x-show="repeat==='weekly'">@foreach([1=>'周一',2=>'周二',3=>'周三',4=>'周四',5=>'周五',6=>'周六',7=>'周日'] as $number=>$label)<label><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number,$selectedDays,true))>{{ $label }}</label>@endforeach</div>
        </div>@if($editing && !$occurrence)<p class="academic-field-help mt-4">保存整个系列会重新设置全部日期，并清除已有单次修改或取消。只改某一天请从安排列表点击那一次。</p>@endif</div>
        <div class="academic-form-section"><h2>私人备注</h2><textarea class="wb-field" name="notes" rows="3" maxlength="4000" placeholder="准备物品、提醒自己的事情…">{{ old('notes',$row['notes']??'') }}</textarea><p class="academic-field-help mt-2">备注始终仅自己可见，不进入公开链接或导出图片。</p></div>
        <div class="academic-form-footer"><button class="wb-btn wb-btn--primary" type="submit">保存安排</button></div>
    </form>
    @if($editing)<section class="personal-danger-zone">@if($occurrence)<form method="POST" action="{{ route('personal-events.cancel',$event) }}">@csrf<input type="hidden" name="timetable" value="{{ $timetable?->id }}"><input type="hidden" name="occurrence" value="{{ $occurrence }}"><button class="wb-btn" name="action" value="{{ $row['canceled']?'restore':'cancel' }}">{{ $row['canceled']?'恢复这一次':'取消这一次' }}</button>@if($event->repeat==='weekly')<button class="wb-btn" name="action" value="future" onclick="return confirm('结束这次及后续安排？过去的安排会保留。')">结束这次及后续</button>@endif</form>@endif<form method="POST" action="{{ route('personal-events.destroy',$event) }}" onsubmit="return confirm('删除整个安排及全部重复记录？此操作无法撤销。')">@csrf @method('DELETE')<input type="hidden" name="timetable" value="{{ $timetable?->id }}"><button class="wb-btn academic-delete">删除整个安排</button></form></section>@endif
</x-personal-shell>
