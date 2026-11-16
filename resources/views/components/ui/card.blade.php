@props([
    'title' => null,
    'subtitle' => null,
    'action' => null,
    'footer' => null,
    'noPadding' => false,
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $subtitle || isset($header) || $action)
        <div class="px-5 py-4 border-b border-[#e1e6ed] flex items-center justify-between gap-4">
            <div>
                @if($title)
                    <h3 class="text-[15px] font-semibold text-gray-900 leading-tight">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-[12px] text-[#8994a3] mt-0.5">{{ $subtitle }}</p>
                @endif
                {{ $header ?? '' }}
            </div>
            @if($action)
                <div class="flex items-center gap-2">
                    {{ $action }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? '' : 'p-5' }}">
        {{ $slot }}
    </div>

    @if($footer || isset($footerSlot))
        <div class="px-5 py-3 bg-[#f8fafc] border-t border-[#e1e6ed] rounded-b-[6px]">
            {{ $footer ?? $footerSlot }}
        </div>
    @endif
</div>
