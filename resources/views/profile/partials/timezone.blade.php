<header><h2 class="text-lg font-semibold text-slate-900">时区与课表</h2><p class="mt-1 text-sm leading-6 text-slate-600">首次使用时识别设备时区。保存自己的选择后，换设备或旅行不会自动覆盖。</p></header>
@if(session('timezone_status'))<p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('timezone_status') }}</p>@endif
<form class="mt-5 space-y-4" method="POST" action="{{ route('profile.timezone') }}">
    @csrf @method('PATCH')
    <x-timezone-picker :value="old('timezone', $user->timezone)" :detect="!old('timezone', $user->timezone)" label="我的默认时区" help="用于新建课表、个人安排与没有注明时区的日历时间。已有课表不会被自动修改。" />
    <button class="wb-btn wb-btn--primary" type="submit">保存默认时区</button>
</form>
<div class="mt-6 border-t border-slate-200 pt-5">
    <h3 class="text-sm font-semibold text-slate-900">已有课表的时区</h3>
    <p class="mt-1 text-xs leading-6 text-slate-500">如果之前误用了 Asia/Shanghai，请点「调整」修改对应课表。课程原有星期和钟点保持不变；已保存的任务按真实时间换算显示，必要时再检查任务时间。</p>
    @foreach($user->timetables()->orderByDesc('is_default')->get() as $plan)
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3"><div class="min-w-0"><p class="break-words text-sm text-slate-800">{{ $plan->name }}</p><p class="break-all text-xs text-slate-500 mt-1">{{ $plan->timezone }}</p></div><a class="wb-btn shrink-0" href="{{ route('timetables.show',[$plan,'dialog'=>'timetable-settings']) }}">调整</a></div>
    @endforeach
</div>
