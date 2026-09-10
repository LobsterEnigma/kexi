<x-academic-shell :timetable="$timetable" title="导出学业任务" subtitle="按课程整理成清晰的任务清单，方便查看、打印或用 Excel 整理。">
    <x-slot name="actions"><a class="wb-btn" href="{{ route('academic-tasks.index', $timetable) }}"><i data-lucide="chevron-left"></i>任务列表</a></x-slot>
    <form class="wb-records-panel academic-export-options" method="GET" action="{{ route('academic-tasks.export-download', $timetable) }}" x-data="{ scope: @js(old('scope', 'all')) }">
        <input type="hidden" name="_export" value="1">
        <section class="academic-form-section">
            <h2><i data-lucide="book-open"></i>选择导出范围</h2>
            <p class="academic-hint">只导出当前课表「{{ $timetable->name }}」的任务。包含全部匹配任务，不限于列表当前一页。</p>
            <div class="academic-export-scope">
                <label><input type="radio" name="scope" value="all" x-model="scope"><span><strong>全部课程与独立任务</strong><small>一次导出当前课表的完整清单</small></span></label>
                <label><input type="radio" name="scope" value="selected" x-model="scope"><span><strong>选择课程</strong><small>可选一门、多门，或仅独立任务</small></span></label>
            </div>
            <fieldset class="academic-export-courses" x-show="scope === 'selected'" x-bind:disabled="scope !== 'selected'" x-cloak x-ref="courseChoices">
                <legend class="sr-only">选择要导出的课程</legend>
                <div class="academic-export-selection"><span>勾选需要的课程</span><div><button type="button" x-on:click="$refs.courseChoices.querySelectorAll('input[type=checkbox]').forEach(input => input.checked = true)">全选</button><button type="button" x-on:click="$refs.courseChoices.querySelectorAll('input[type=checkbox]').forEach(input => input.checked = false)">清空</button></div></div>
                @foreach($courses as $course)
                    <label><input type="checkbox" name="courses[]" value="{{ $course->id }}" @checked(in_array((string)$course->id, array_map('strval', (array)old('courses', [])), true))><span><strong>{{ $course->code ? $course->code.' · ' : '' }}{{ $course->name }}</strong><small>{{ $course->academic_tasks_count }} 项任务{{ $course->is_archived ? ' · 课程已隐藏' : '' }}</small></span></label>
                @endforeach
                <label><input type="checkbox" name="independent" value="1" @checked(old('independent'))><span><strong>独立任务</strong><small>{{ $independentCount }} 项未关联课程的任务</small></span></label>
            </fieldset>
        </section>
        <section class="academic-form-section">
            <h2><i data-lucide="list-checks"></i>清单内容</h2>
            <label class="wb-field-group"><span class="wb-label">任务状态</span><select class="wb-select" name="status"><option value="all" @selected(old('status','all') === 'all')>全部任务（含已完成）</option><option value="pending" @selected(old('status') === 'pending')>仅待完成</option><option value="completed" @selected(old('status') === 'completed')>仅已完成</option></select></label>
            <div class="academic-export-checks">
                <label><input type="checkbox" name="details" value="1" @checked(old('details', old('_export') ? 0 : 1))><span>包含学习计划与 Project 阶段<small>跟随所属任务导出，各项保留自己的完成状态。</small></span></label>
                <label><input type="checkbox" name="notes" value="1" @checked(old('notes'))><span>包含备注与资料链接<small>长备注会增加清单页数，简洁查看时可不勾选。</small></span></label>
            </div>
        </section>
        <section class="academic-form-section">
            <h2><i data-lucide="download"></i>选择导出方式</h2>
            <p class="academic-hint">清单会按课程分组，并标清开放时间、提交截止、考试时段和时区。预览后可在浏览器打印窗口选择「另存为 PDF」。</p>
            <div class="academic-export-formats"><button class="wb-btn wb-btn--primary" type="submit" name="format" value="preview"><i data-lucide="file-text"></i>预览 / 另存 PDF</button><button class="wb-btn" type="submit" name="format" value="csv"><i data-lucide="download"></i>下载 CSV 表格</button></div>
        </section>
    </form>
</x-academic-shell>
