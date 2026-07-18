@props(['iconClass' => 'h-5 w-5'])

<span {{ $attributes->class(['wb-brand__mark']) }} aria-hidden="true">
    <i data-lucide="book-open" class="{{ $iconClass }}"></i>
</span>
