@props(['value' => null, 'detect' => false, 'label' => '时区', 'help' => null])
@php($zoneListId = 'timezones-'.\Illuminate\Support\Str::random(8))
<div class="wb-field-group wb-field-group--full" x-data="timezonePicker({ value: @js($value), detect: @js($detect) })">
    <label><span class="wb-label">{{ $label }}</span><input class="wb-field" name="timezone" type="text" list="{{ $zoneListId }}" x-model="value" value="{{ $value ?: 'UTC' }}" maxlength="64" required autocomplete="off" aria-label="{{ $label }}"></label>
    <datalist id="{{ $zoneListId }}">@foreach(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) as $zone)<option value="{{ $zone }}"></option>@endforeach</datalist>
    <div class="flex flex-wrap items-center gap-2 mt-2"><button class="wb-btn !text-xs" type="button" x-on:click="useDevice()"><i data-lucide="monitor"></i>使用设备时区</button><span class="text-xs text-slate-500" x-text="device ? '当前设备：' + device : '未识别设备时区'"></span></div>
    <span class="academic-field-help" x-show="notice" x-text="notice"></span>
    @if($help)<span class="academic-field-help">{{ $help }}</span>@endif
    @error('timezone')<span class="text-xs text-red-700" role="alert">{{ $message }}</span>@enderror
</div>
