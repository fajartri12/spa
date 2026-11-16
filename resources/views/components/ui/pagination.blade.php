@props([
    'paginator' => null,
    'label' => 'data',
])

@if($paginator)
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-t border-[#e1e6ed] px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5']) }}>
        <p class="text-[12px] text-[#64748b]" aria-live="polite">
            @if($paginator->total() > 0)
                Menampilkan
                <span class="font-medium text-[#334155]">{{ $paginator->firstItem() }}</span>
                &ndash;
                <span class="font-medium text-[#334155]">{{ $paginator->lastItem() }}</span>
                dari
                <span class="font-medium text-[#334155]">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
                {{ $label }}
            @else
                Tidak ada {{ $label }}
            @endif
        </p>

        @if($paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Navigasi halaman">
                @if($paginator->onFirstPage())
                    <span class="btn-icon cursor-not-allowed opacity-40" aria-disabled="true" aria-label="Halaman sebelumnya">
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @else
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="btn-icon" aria-label="Halaman sebelumnya">
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                @endif

                @foreach($paginator->onEachSide(1)->linkCollection() as $link)
                    @php
                        $pageLabel = strip_tags($link['label']);
                        $isEllipsis = $pageLabel === '...';
                    @endphp

                    @if($isEllipsis)
                        <span class="flex h-8 w-8 items-center justify-center text-[13px] text-[#94a3b8]" aria-hidden="true">&hellip;</span>
                    @elseif($link['url'] === null)
                        <span class="flex h-8 min-w-8 items-center justify-center rounded-[5px] bg-[#2872c8] px-2 text-[13px] font-medium text-white" aria-current="page">{{ $pageLabel }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ (int) $pageLabel }})" wire:loading.attr="disabled" class="flex h-8 min-w-8 items-center justify-center rounded-[5px] px-2 text-[13px] text-[#475569] transition hover:bg-[#f1f5f9]" aria-label="Halaman {{ $pageLabel }}">
                            {{ $pageLabel }}
                        </button>
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" class="btn-icon" aria-label="Halaman berikutnya">
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                @else
                    <span class="btn-icon cursor-not-allowed opacity-40" aria-disabled="true" aria-label="Halaman berikutnya">
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
