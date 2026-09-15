@props(['iconClass' => 'h-5 w-5'])

<span {{ $attributes->class(['wb-brand__mark']) }} aria-hidden="true"
      @if(config('kexi.site_logo')) x-data="{ failed: false }" :class="{ 'wb-brand__mark--custom': !failed }"
      x-init="$nextTick(() => { if ($refs.image.complete && !$refs.image.naturalWidth) failed = true; })" @endif>
    @if(config('kexi.site_logo'))
        <img src="{{ route('site-logo', ['name' => config('kexi.site_logo')], false) }}" alt="" class="wb-brand__image"
             x-ref="image" x-show="!failed" x-on:error="failed = true">
        <span x-cloak x-show="failed"><i data-lucide="book-open" class="{{ $iconClass }}"></i></span>
    @else
        <i data-lucide="book-open" class="{{ $iconClass }}"></i>
    @endif
</span>
