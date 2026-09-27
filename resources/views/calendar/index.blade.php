<x-academic-shell :timetable="$timetable" title="日历连接" subtitle="把已有安排带进课隙，也把课隙带到你常用的日历。">
    <x-slot name="actions"><a class="wb-btn" href="{{ route('timetables.show',$timetable) }}"><i data-lucide="chevron-left"></i>返回课表</a></x-slot>
    <div class="calendar-connect">
        <nav class="calendar-connect-tabs" aria-label="日历连接方式">
            @foreach(['import'=>['导入日历','已有安排带进来','calendar-range'],'export'=>['导出文件','保存当前安排快照','download'],'subscribe'=>['日历订阅','让其他日历持续更新','refresh-cw']] as $key=>$item)
                <a href="{{ route('calendar.index',[$timetable,'tab'=>$key]) }}" @if($tab===$key) aria-current="page" @endif><i data-lucide="{{ $item[2] }}"></i><span><strong>{{ $item[0] }}</strong><small>{{ $item[1] }}</small></span></a>
            @endforeach
        </nav>
        @if($tab==='import')
            <form class="calendar-connect-panel calendar-upload-panel" x-data="calendarUpload" method="POST" enctype="multipart/form-data" action="{{ route('calendar.upload',$timetable) }}">
                @csrf
                <div class="calendar-section-heading"><div><h2>把日程带进来</h2><p>选择日历文件，我们会自动识别时间、时区和重复安排。</p></div><span class="calendar-format-badge">.ics</span></div>
                <label class="calendar-file" :class="{ 'is-dragging': dragging, 'has-file': fileName, 'has-error': error }" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="drop($event)">
                    <input class="calendar-file-input" type="file" name="file" x-ref="file" accept=".ics,text/calendar" required aria-label="选择日历文件" x-on:change="select($event.target.files[0])">
                    <span class="calendar-file-symbol"><i data-lucide="file-up"></i><span>ICS</span></span>
                    <span class="calendar-file-title" x-text="fileName || '选择日历文件，或拖放到这里'">选择日历文件，或拖放到这里</span>
                    <span class="calendar-file-description" x-text="fileName ? fileSize + ' · 已就绪，下一步查看日程' : '支持学校系统、Apple 日历、Google 日历和 Outlook'">支持学校系统、Apple 日历、Google 日历和 Outlook</span>
                    <span class="calendar-file-button"><i data-lucide="plus"></i><span x-text="fileName ? '重新选择' : '选择文件'">选择文件</span></span>
                    <span class="calendar-file-limit">ICS 格式 · 最大 1 MB</span>
                </label>
                <p class="calendar-upload-error" role="alert" x-show="error" x-cloak x-text="error"></p>
                <div class="calendar-auto-hint"><i data-lucide="sparkles"></i><div><strong>自动识别，无需填写</strong><p>优先使用文件中的时区和日期。遇到未注明时区或没有结束日期的重复安排，会在预览中说明处理方式。</p></div></div>
                <input type="hidden" name="fallback_timezone" value="{{ auth()->user()->timezone ?: $timetable->timezone }}" x-ref="fallbackTimezone">
                <details class="calendar-advanced" @if(old('from') || old('to') || old('timezone') || $errors->any()) open @endif>
                    <summary><span><i data-lucide="sliders-horizontal"></i>更多选项 <small>仅在需要时调整</small></span><i data-lucide="chevron-down"></i></summary>
                    <div class="calendar-advanced-body">
                        <p>留空即可自动识别。只想导入一部分日程时，再填写日期范围。</p>
                        <div class="calendar-date-fields">
                            <label class="wb-field-group"><span class="wb-label">开始日期（可选）</span><input class="wb-field" type="date" name="from" value="{{ old('from') }}"></label>
                            <label class="wb-field-group"><span class="wb-label">结束日期（可选，含当天）</span><input class="wb-field" type="date" name="to" value="{{ old('to') }}"></label>
                        </div>
                        <label class="wb-field-group"><span class="wb-label">未注明时区的时间，手动按此时区解释（可选）</span><input class="wb-field" name="timezone" list="calendar-timezones" value="{{ old('timezone') }}" placeholder="自动识别"><datalist id="calendar-timezones">@foreach(['America/Toronto','Asia/Shanghai','UTC','Europe/London','America/New_York','America/Los_Angeles'] as $zone)<option value="{{ $zone }}"></option>@endforeach</datalist><span class="academic-field-help">仅影响没有单独时区的时间；文件已标注的时区始终保留。预览统一显示为课表时区 {{ $timetable->timezone }}。</span></label>
                    </div>
                </details>
                <div class="calendar-panel-footer"><p><i data-lucide="eye"></i>下一步先预览，你确认后才会添加。</p><button class="wb-btn wb-btn--primary" type="submit">预览日历<i data-lucide="chevron-right"></i></button></div>
            </form>
        @else
            @if($newSubscriptionUrl)
                <section class="calendar-link-result" x-data="{ message: '' }" aria-label="新建订阅链接">
                    <h2><i data-lucide="circle-check-big"></i>订阅链接已准备好</h2>
                    <p>在 Apple 日历选择「新建日历订阅」；Google 日历选择「其他日历 → 通过网址」；Outlook 选择「从 Web 订阅」。粘贴下方完整链接。</p>
                    <div class="calendar-copy-row"><input class="wb-field" type="text" readonly value="{{ $newSubscriptionUrl }}" x-ref="subscriptionUrl" aria-label="订阅链接" x-on:click="$el.select()"><button class="wb-btn" type="button" x-on:click="if(navigator.clipboard) { navigator.clipboard.writeText($refs.subscriptionUrl.value).then(() => message = '已复制').catch(() => { $refs.subscriptionUrl.select(); message = '请手动复制选中的链接'; }) } else { $refs.subscriptionUrl.select(); message = '请手动复制选中的链接' }"><i data-lucide="copy"></i>复制</button></div><p role="status" x-text="message"></p>
                </section>
            @endif
            <form class="calendar-connect-panel" method="{{ $tab==='subscribe'?'POST':'GET' }}" action="{{ route($tab==='subscribe'?'calendar.subscribe':'calendar.download',$timetable) }}">
                @if($tab==='subscribe') @csrf @endif
                <div class="calendar-section-heading"><i data-lucide="{{ $tab==='subscribe'?'refresh-cw':'download' }}"></i><div><h2>{{ $tab==='subscribe'?'创建一条日历订阅':'保存为日历文件' }}</h2><p>{{ $tab==='subscribe'?'外部日历会定期获取此日期范围内的最新安排，不需要你重复导出。':'下载的文件是当前快照，之后修改课隙不会更新已导入的文件。' }}</p></div></div>
                @if($tab==='subscribe')<label class="wb-field-group"><span class="wb-label">给订阅起个名字</span><input class="wb-field" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="例如：我的 Apple 日历"></label>@endif
                <fieldset class="calendar-content-choices"><legend>选择包含的内容</legend>
                    @foreach(\App\Services\CalendarExport::CONTENTS as $key=>$label)
                    <label><input type="checkbox" name="contents[]" value="{{ $key }}" @checked(in_array($key,old('contents',['courses'])))><span><strong>{{ $label }}</strong><small>{{ ['courses'=>'当前课表的课程、地点和单次取消状态','tasks'=>'开放时间、提交截止、考试 / 活动时间','study'=>'你安排的学习时段，以及项目阶段截止','personal'=>'账户中的个人活动；勾选后会对链接持有者可见'][$key] }}</small></span>@if($key==='personal')<em>默认不包含</em>@endif</label>
                    @endforeach
                </fieldset>
                <div class="calendar-date-fields"><label class="wb-field-group"><span class="wb-label">开始日期</span><input class="wb-field" type="date" name="from" value="{{ old('from',$from) }}" required></label><label class="wb-field-group"><span class="wb-label">结束日期（含当天）</span><input class="wb-field" type="date" name="to" value="{{ old('to',$to) }}" required></label></div>
                <p class="calendar-note"><i data-lucide="{{ $tab==='subscribe'?'link-2':'circle-help' }}"></i><span>{{ $tab==='subscribe'?'持有链接的人可以查看所选内容，包括以后在此日期范围内新增的安排。备注与请假原因不会包含。刷新时间由外部日历决定，可能有延迟；学期结束后可创建新的订阅。':'隐藏课程不包含；已取消课程带取消标记；已完成任务会注明已完成。没有任何时间的任务无法放入日历，请使用「导出任务」保存完整清单。' }}</span></p>
                @php($subscriptionAllowed = auth()->user()->canShare() && app(\App\Services\SiteSettings::class)->bool('sharing_enabled'))
                <div class="calendar-panel-footer"><p>{{ $tab==='subscribe'?($subscriptionAllowed?'可随时撤销，不需要配置定时任务。':'管理员已停用站点或账户分享，订阅暂不可用；仍可下载文件。'):'支持常见日历应用，时间会按日历应用的时区显示。' }}</p><button class="wb-btn wb-btn--primary" type="submit" @disabled($tab==='subscribe' && !$subscriptionAllowed)><i data-lucide="{{ $tab==='subscribe'?'link-2':'download' }}"></i>{{ $tab==='subscribe'?'创建订阅链接':'下载 .ics 文件' }}</button></div>
            </form>
            @if($tab==='subscribe')
            <section class="calendar-subscriptions"><div class="calendar-section-heading"><div><h2>正在使用的订阅 <span>{{ $subscriptions->count() }}</span></h2><p>每条链接独立控制，可以随时重新复制。需要更换内容或范围时，请撤销后创建新链接。</p></div></div>
                @forelse($subscriptions as $subscription)
                <article class="calendar-subscription"><div class="min-w-0 flex-1"><h3>{{ $subscription->name }}</h3><p>{{ $subscription->date_from->format('Y/m/d') }} — {{ $subscription->date_to->format('Y/m/d') }}</p><div class="calendar-tags">@foreach($subscription->contents as $content)<span>{{ \App\Services\CalendarExport::CONTENTS[$content] }}</span>@endforeach</div><details class="calendar-saved-link"><summary>查看并复制链接</summary><input class="wb-field" type="text" readonly value="{{ $links[$subscription->id] }}" aria-label="{{ $subscription->name }}的订阅链接" x-on:click="$el.select()"><p>点击选中完整链接后复制，请勿公开转发。</p></details></div><form method="POST" action="{{ route('calendar.revoke',[$timetable,$subscription]) }}">@csrf @method('DELETE')<button class="wb-btn" type="submit">撤销订阅</button></form></article>
                @empty <p class="calendar-empty">还没有订阅。创建一条链接，就能在常用日历中查看课隙安排。</p>@endforelse
            </section>
            @endif
        @endif
    </div>
</x-academic-shell>
