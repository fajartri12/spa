@props([
    'title' => 'Belum Ada Data',
    'description' => 'Data yang Anda cari tidak ditemukan.',
    'icon' => 'inbox',
    'action' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 text-center px-4']) }}>
    <div class="w-12 h-12 rounded-full bg-[#f1f5f9] flex items-center justify-center">
        <i data-lucide="{{ $icon }}" class="w-6 h-6 text-[#94a3b8]"></i>
    </div>
    <h3 class="text-[14px] font-semibold text-[#334155] mt-1">{{ $title }}</h3>
    <p class="text-[13px] text-[#8994a3] max-w-sm">{{ $description }}</p>
    @if($action)
        <div class="mt-2">{{ $action }}</div>
    @endif
</div>
