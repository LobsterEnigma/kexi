@props(['timetable'])
<div class="academic-reminders" x-data="academicReminders({ list: @js(route('academic-tasks.reminders',$timetable)), acknowledge: @js(route('academic-tasks.acknowledge',$timetable)) })" x-on:keydown.escape.window="open = false">
    <button class="wb-utility-btn" type="button" x-on:click="open = !open; if(open) refresh()" x-bind:aria-expanded="open" aria-label="站内提醒" title="查看学业任务提醒"><i data-lucide="bell"></i><span>提醒</span><b x-show="items.length" x-cloak x-text="items.length" aria-label="待处理提醒数"></b></button>
    <section class="academic-reminders__panel" x-show="open" x-cloak x-on:click.outside="open=false" aria-label="站内提醒列表">
        <div class="academic-reminders__heading"><strong>站内提醒</strong><button class="wb-icon-btn" type="button" x-on:click="open=false" aria-label="关闭提醒"><i data-lucide="x"></i></button></div>
        <p class="academic-hint">打开页面时自动检查；关闭网站后不会发送推送或邮件。</p>
        <p class="academic-hint" x-show="loading && !loaded">正在检查提醒…</p>
        <p class="academic-error" role="status" x-show="error" x-text="error"></p>
        <p class="academic-reminders__empty" x-show="loaded && !items.length && !error">暂时没有需要处理的提醒。</p>
        <div class="academic-reminders__list"><template x-for="item in items" :key="item.key"><article class="academic-reminder">
            <a :href="item.url"><small x-text="(item.overdue ? '时间已到 · ' : '') + item.label + ' · ' + item.time"></small><strong x-text="item.title"></strong><span x-show="item.title !== item.task_title" x-text="item.task_title"></span></a>
            <button class="wb-btn" type="button" :disabled="busy === item.key" x-on:click="acknowledge(item.key)">知道了</button>
        </article></template></div>
    </section>
</div>
