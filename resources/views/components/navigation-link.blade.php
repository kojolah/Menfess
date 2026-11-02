@props(['href', 'active' => false, 'mobile' => false])

<a href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    @if ($mobile) @click="open = false" @endif
    {{ $attributes->class([
        'inline-flex items-center gap-2 rounded-xl font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffc629]',
        'bg-[#0b2d59] px-4 py-2.5 text-white shadow-sm' => $active && ! $mobile,
        'px-4 py-2.5 text-[#315276] hover:bg-[#dceefa] hover:text-[#0b2d59]' => ! $active && ! $mobile,
        'min-h-12 w-full px-4 py-3 text-base' => $mobile,
        'bg-[#0b2d59] text-white' => $active && $mobile,
        'text-[#102e55] hover:bg-[#dceefa]' => ! $active && $mobile,
    ]) }}>
    {{ $slot }}
</a>
