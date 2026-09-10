@php
    $isNew=!$task->exists;
    $value=fn($key,$default=null)=>old('_form')==='task'?old($key,$default):($task->$key??$default);
    $dateValue=fn($key)=>old('_form')==='task'?old($key):$task->$key?->timezone($timetable->timezone)->format('Y-m-d\TH:i');
@endphp
<x-academic-shell :timetable="$timetable" :title="$isNew?'新建学业任务':'任务详情'" :subtitle="$timetable->name.' · 时间按 '.$timetable->timezone">
    <x-slot name="actions"><a class="wb-btn" href="{{ route('academic-tasks.index',$timetable) }}"><i data-lucide="chevron-left"></i>任务列表</a></x-slot>
    <div class="academic-edit-layout">
        <div>
            <form class="academic-editor wb-records-panel" method="POST" action="{{ $isNew?route('academic-tasks.store',$timetable):route('academic-tasks.update',[$timetable,$task]) }}" x-data="{ taskType: @js($value('type','assignment')) }">
                @csrf @if(!$isNew) @method('PATCH') @endif<input type="hidden" name="_form" value="task">
                <section class="academic-form-section"><h2><i data-lucide="file-text"></i>基本信息</h2><div class="academic-form-grid">
                    <label class="wb-field-group academic-span"><span class="wb-label">任务名称 <span aria-hidden="true">*</span></span><input class="wb-field" name="title" required maxlength="160" value="{{ $value('title') }}" placeholder="例如：Assignment 2 · 微积分练习"></label>
                    <label class="wb-field-group"><span class="wb-label">任务类型</span><select class="wb-select" name="type" x-model="taskType">@foreach(\App\Models\AcademicTask::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="wb-field-group"><span class="wb-label">关联课程</span><select class="wb-select" name="course_id"><option value="">独立任务（不关联课程）</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($value('course_id')==$course->id)>{{ $course->code ? $course->code.' · ' : '' }}{{ $course->name }}{{ $course->is_archived?'（已隐藏）':'' }}</option>@endforeach</select></label>
                </div></section>
                <section class="academic-form-section">
                    <p class="academic-time-choice">下面两组时间按实际情况选填，不需要两组都填。</p>
                    <h2><i data-lucide="calendar-days"></i>提交期限 <small>选填</small></h2>
                    <p class="academic-hint" id="submission-time-help">什么时候可以开始作答或提交，最晚什么时候必须交？普通作业通常填这里。知道哪项就填哪项，另一项暂时不知道可以留空，之后再补。</p>
                    <p class="academic-time-example"><strong>例如作业：</strong>9 月 10 日 08:00 开放提交，9 月 17 日 23:59 截止。</p>
                    <div class="academic-form-grid">
                    <label class="wb-field-group"><span class="wb-label">开始接受作答 / 提交</span><input class="wb-field" type="datetime-local" name="opens_at" value="{{ $dateValue('opens_at') }}" aria-describedby="submission-time-help submission-opens-help"><span class="academic-field-help" id="submission-opens-help">可以单独填写；还不知道截止时间也能保存。</span></label>
                    <label class="wb-field-group"><span class="wb-label">最晚提交时间（截止）</span><input class="wb-field" type="datetime-local" name="due_at" value="{{ $dateValue('due_at') }}" aria-describedby="submission-time-help submission-due-help"><span class="academic-field-help" id="submission-due-help">可以只填这一项，作为任务的最后期限。</span></label>
                </div></section>
                <section class="academic-form-section"><h2><i data-lucide="clock-3"></i>考试或活动安排 <small>选填</small></h2>
                    <p class="academic-hint" id="scheduled-time-help">必须在某个固定时段参加的考试、课堂 Quiz 或活动填这里。开始和结束时间需一起填，会像课程一样显示在课表时间格里。</p>
                    <p class="academic-time-example"><strong>例如课堂 Quiz：</strong>9 月 12 日 10:00–10:30 参加测验，就填写这半小时；没有提交期限时，上面一组留空。</p>
                    <div class="academic-form-grid">
                    <label class="wb-field-group"><span class="wb-label">考试 / 活动开始</span><input class="wb-field" type="datetime-local" name="starts_at" value="{{ $dateValue('starts_at') }}" aria-describedby="scheduled-time-help"></label>
                    <label class="wb-field-group"><span class="wb-label">考试 / 活动结束</span><input class="wb-field" type="datetime-local" name="ends_at" value="{{ $dateValue('ends_at') }}" aria-describedby="scheduled-time-help"></label>
                    <label class="wb-field-group academic-span"><span class="wb-label">地点</span><input class="wb-field" name="location" maxlength="200" value="{{ $value('location') }}" placeholder="教室、线上会议或其他地点"></label>
                </div>
                    <p class="academic-personal-time">想安排自己写作业或复习的时间？@if($isNew)创建任务后，在「我的学习计划」中添加。@else<a href="#study-plans">到「我的学习计划」中添加</a>。@endif例如今晚 19:00–21:00 写作业，可以安排多次。</p>
                </section>
                <section class="academic-form-section"><h2><i data-lucide="link-2"></i>资料与提醒</h2><div class="academic-form-grid">
                    <label class="wb-field-group academic-span"><span class="wb-label">相关链接</span><input class="wb-field" type="url" name="url" maxlength="2000" value="{{ $value('url') }}" placeholder="https://"></label>
                    <label class="wb-field-group academic-span"><span class="wb-label">备注</span><textarea class="wb-textarea" name="notes" maxlength="10000" rows="4" placeholder="提交要求、复习范围、资料说明…">{{ $value('notes') }}</textarea></label>
                    <label class="wb-field-group"><span class="wb-label">站内提醒</span><select class="wb-select" name="reminder_minutes"><option value="">不提醒</option>@foreach(\App\Models\AcademicTask::REMINDERS as $key=>$label)<option value="{{ $key }}" @selected($value('reminder_minutes')!==null && (string)$value('reminder_minutes')===(string)$key)>{{ $label }}</option>@endforeach</select></label>
                    <p class="academic-hint">分别在任务截止、考试开始前提醒。项目阶段与学习计划可以单独设置提醒。</p>
                </div></section>
                <div class="academic-form-footer"><a class="wb-btn" href="{{ route('academic-tasks.index',$timetable) }}">返回列表</a><button class="wb-btn wb-btn--primary" type="submit">{{ $isNew?'创建任务':'保存任务' }}</button></div>
            </form>
            @if(!$isNew)
                @if($task->type==='project')
                    @php($milestones=$task->entries->where('kind','milestone'))
                    <section class="academic-entry-section" id="milestones"><div class="academic-section-heading"><h2>Project 阶段</h2><span>{{ $milestones->whereNotNull('completed_at')->count() }} / {{ $milestones->count() }} 已完成</span></div><p class="academic-hint">把提案、初稿、展示拆成独立节点。完成全部阶段后，仍由你确认整个项目完成。</p>
                    @foreach($milestones as $entry) @include('academic.entry',['kind'=>'milestone']) @endforeach
                    @include('academic.entry',['kind'=>'milestone','entry'=>null])</section>
                @endif
                <section class="academic-entry-section" id="study-plans"><div class="academic-section-heading"><h2>我的学习计划</h2><span>{{ $task->entries->where('kind','study')->count() }} 次安排</span></div><p class="academic-hint">安排自己实际写作业、复习或做项目的时间。可添加多次，支持跨午夜。</p>
                    @foreach($task->entries->where('kind','study') as $entry) @include('academic.entry',['kind'=>'study']) @endforeach
                    @include('academic.entry',['kind'=>'study','entry'=>null])
                </section>
            @endif
        </div>
        <aside class="academic-edit-aside">
            <section class="academic-aside-card"><h2>安排好下一步</h2>
                @if($isNew)<p>先保存任务，然后就可以添加多次学习计划。选择 Project 还能添加阶段节点。</p>@else
                    <form method="POST" action="{{ route('academic-tasks.complete',[$timetable,$task]) }}">@csrf @method('PATCH')<input type="hidden" name="completed" value="{{ $task->completed_at?0:1 }}"><button class="wb-btn {{ $task->completed_at?'':'wb-btn--primary' }}" type="submit"><i data-lucide="{{ $task->completed_at?'rotate-ccw':'check' }}"></i>{{ $task->completed_at?'重新打开任务':'标记整个任务完成' }}</button></form>
                    @if($task->completed_at)<p>任务已完成，相关提醒已停止。子安排保留各自状态。</p>@endif
                    <a class="academic-aside-link" href="#study-plans">安排学习时间 <span>↓</span></a>
                    @if($task->type==='project')<a class="academic-aside-link" href="#milestones">管理项目阶段 <span>↓</span></a>@endif
                    @if($task->url)<a class="academic-aside-link" href="{{ $task->url }}" target="_blank" rel="noopener noreferrer">打开相关资料 <i data-lucide="external-link"></i></a>@endif
                @endif
                <a class="academic-aside-link" href="{{ route('timetables.show',$timetable) }}">查看课表 <i data-lucide="calendar-days"></i></a>
            </section>
            <section class="academic-aside-card"><h2>不知道该填哪一组？</h2><p><strong>普通作业：</strong>一般填「最晚提交时间」；老师规定了开放时间，再补「开始接受作答 / 提交」。</p><p><strong>固定时间的考试：</strong>填「考试或活动安排」的开始和结束。</p><p><strong>两种要求都有：</strong>两组都可以填。例如项目有提交截止时间，也有固定的展示时段。</p></section>
            @if(!$isNew)<form action="{{ route('academic-tasks.destroy',[$timetable,$task]) }}" method="POST" onsubmit="return confirm('删除此任务及其全部项目阶段和学习计划？此操作无法撤销。')">@csrf @method('DELETE')<button class="wb-btn academic-delete" type="submit"><i data-lucide="trash-2"></i>删除任务</button></form>@endif
        </aside>
    </div>
</x-academic-shell>
