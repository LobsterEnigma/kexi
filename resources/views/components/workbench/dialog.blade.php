@props([
    'name',
    'size' => 'lg',
])

<div
    class="wb-modal-layer"
    data-workbench-dialog="{{ $name }}"
    x-cloak
    x-show="modal === '{{ $name }}'"
    x-on:keydown.escape.window="modal === '{{ $name }}' && closeDialog()"
    role="dialog"
    aria-modal="true"
    {{ $attributes }}
>
    <div
        class="wb-modal-backdrop"
        x-on:click="closeDialog()"
        x-show="modal === '{{ $name }}'"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    <section
        class="wb-modal-panel {{ $size === 'sm' ? 'wb-modal-panel--sm' : '' }}"
        x-show="modal === '{{ $name }}'"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-5 opacity-0 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave-end="translate-y-5 opacity-0 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </section>
</div>
