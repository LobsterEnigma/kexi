@props(['compact' => false])
<fieldset {{ $attributes->class(['theme-switcher', 'theme-switcher--compact' => $compact]) }} x-data>
    <legend class="sr-only">外观主题</legend>
    @foreach (['light' => ['浅色', 'sun'], 'dark' => ['深色', 'moon'], 'system' => ['跟随系统', 'monitor']] as $value => [$label, $icon])
        <button type="button" class="theme-switcher__option" x-on:click="$store.theme.set('{{ $value }}')"
                :aria-pressed="$store.theme.mode === '{{ $value }}'" aria-label="{{ $label }}主题" title="{{ $label }}"
                data-theme-option="{{ $value }}">
            <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
            <span @class(['sr-only' => $compact])>{{ $label }}</span>
        </button>
    @endforeach
</fieldset>
