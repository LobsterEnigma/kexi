@php
    $entryKey=$entry?'entry-'.$entry->id:'new-'.$kind;
    $entryValue=fn($key,$default=null)=>old('_form')===$entryKey?old($key,$default):($entry ? $entry->$key : $default);
    $entryDate=fn($key)=>old('_form')===$entryKey?old($key):$entry?->$key?->timezone($timetable->timezone)->format('Y-m-d\TH:i');
    $kindLabel=$kind==='study'?'学习计划':'项目阶段';
@endphp
<div class="academic-entry {{ $entry?->completed_at?'is-complete':'' }}" id="{{ $entryKey }}">
    @if($entry)<div class="academic-entry__summary">
        <form action="{{ route('academic-entries.complete',[$timetable,$task,$entry]) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="completed" value="{{ $entry->completed_at?0:1 }}"><button class="academic-check" aria-label="{{ $entry->completed_at?'重新打开':'完成' }} {{ $entry->title }}" aria-pressed="{{ $entry->completed_at?'true':'false' }}" type="submit">@if($entry->completed_at)<i data-lucide="check"></i>@endif</button></form>
        <div><h3>{{ $entry->title }}</h3><p>{{ ($entry->due_at??$entry->starts_at)?->timezone($timetable->timezone)->format('Y/n/j H:i') }}{{ $entry->ends_at?' – '.$entry->ends_at->timezone($timetable->timezone)->format('n/j H:i'): ' 截止' }}{{ $entry->completed_at?' · 已完成':'' }}</p>@if($entry->location)<p>{{ $entry->location }}</p>@endif</div>
    </div>@endif
    <details @if(old('_form')===$entryKey) open @endif>
        <summary>{{ $entry?'编辑安排':'＋ 添加'.$kindLabel }}</summary>
        <form class="academic-entry-form" method="POST" action="{{ $entry?route('academic-entries.update',[$timetable,$task,$entry]):route('academic-entries.store',[$timetable,$task]) }}">
            @csrf @if($entry) @method('PATCH') @endif<input type="hidden" name="_form" value="{{ $entryKey }}"><input type="hidden" name="kind" value="{{ $kind }}">
            <div class="academic-form-grid"><label class="wb-field-group academic-span"><span class="wb-label">{{ $kindLabel }}名称</span><input class="wb-field" name="title" required maxlength="160" value="{{ $entryValue('title') }}" placeholder="{{ $kind==='study'?'例如：完成作业前半部分':'例如：提交项目提案' }}"></label>
            @if($kind==='milestone')<label class="wb-field-group academic-span"><span class="wb-label">阶段截止时间</span><input class="wb-field" name="due_at" type="datetime-local" required value="{{ $entryDate('due_at') }}"></label>
            @else
                <label class="wb-field-group"><span class="wb-label">开始时间</span><input class="wb-field" name="starts_at" type="datetime-local" required value="{{ $entryDate('starts_at') }}"></label><label class="wb-field-group"><span class="wb-label">结束时间</span><input class="wb-field" name="ends_at" type="datetime-local" required value="{{ $entryDate('ends_at') }}"></label>
                <label class="wb-field-group academic-span"><span class="wb-label">学习地点</span><input class="wb-field" name="location" maxlength="200" value="{{ $entryValue('location') }}"></label>
            @endif
            <label class="wb-field-group academic-span"><span class="wb-label">备注</span><textarea class="wb-textarea" name="notes" rows="2" maxlength="10000">{{ $entryValue('notes') }}</textarea></label>
            <label class="wb-field-group"><span class="wb-label">站内提醒</span><select class="wb-select" name="reminder_minutes"><option value="">不提醒</option>@foreach(\App\Models\AcademicTask::REMINDERS as $key=>$label)<option value="{{ $key }}" @selected($entryValue('reminder_minutes',60)!==null && (string)$entryValue('reminder_minutes',60)===(string)$key)>{{ $label }}</option>@endforeach</select></label>
            </div><button class="wb-btn wb-btn--primary" type="submit">{{ $entry?'保存安排':'添加'.$kindLabel }}</button>
        </form>
        @if($entry)<form class="academic-entry-delete" method="POST" action="{{ route('academic-entries.destroy',[$timetable,$task,$entry]) }}" onsubmit="return confirm('确定删除这条安排？')">@csrf @method('DELETE')<button class="wb-btn academic-delete" type="submit">删除这条安排</button></form>@endif
    </details>
</div>
