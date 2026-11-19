@props([
    'paginator' => null,
    'label' => 'data',
])

@php
    $showPagination = $paginator && $paginator->hasPages();
    $pageName = $paginator?->getPageName() ?? 'page';
    $total = $paginator?->total() ?? 0;

    if ($showPagination) {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = $last > 2
            ? collect(range(max(2, $current - 1), min($last - 1, $current + 1)))
            : collect();

        $pageItems = collect([1])
            ->merge($window->isNotEmpty() && $window->first() > 2 ? ['gap-start'] : [])
            ->merge($window)
            ->merge($window->isNotEmpty() && $window->last() < $last - 1 ? ['gap-end'] : [])
            ->merge($last > 1 ? [$last] : []);
    }
@endphp

@if($paginator)
    <div {{ $attributes->merge(['class' => 'pagination-bar']) }}>
        <p class="pagination-summary" aria-live="polite" aria-atomic="true">
            @if($total > 0)
                <span class="hidden sm:inline">Menampilkan </span>
                <span class="font-semibold text-[#334155] tabular">{{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }}</span>
                <span> dari </span>
                <span class="font-semibold text-[#334155] tabular">{{ number_format($total, 0, ',', '.') }}</span>
                <span>{{ $label }}</span>
            @else
                Tidak ada {{ $label }}
            @endif
        </p>

        @if($showPagination)
            <nav class="pagination-nav" aria-label="Navigasi halaman {{ $label }}">
                @if($paginator->onFirstPage())
                    <span class="pagination-control" aria-disabled="true" aria-label="Halaman sebelumnya" title="Halaman sebelumnya">
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="previousPage('{{ $pageName }}')"
                        wire:loading.attr="disabled"
                        wire:target="previousPage('{{ $pageName }}')"
                        class="pagination-control"
                        aria-label="Halaman sebelumnya"
                        title="Halaman sebelumnya"
                    >
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                @endif

                <div class="pagination-pages" wire:loading.class="opacity-60" wire:target="previousPage('{{ $pageName }}'), nextPage('{{ $pageName }}'), gotoPage">
                    @foreach($pageItems as $item)
                        @if(is_string($item))
                            <span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>
                        @elseif($item === $current)
                            <span class="pagination-page is-active" aria-current="page" aria-label="Halaman {{ $item }}, halaman aktif">
                                {{ $item }}
                            </span>
                        @else
                            <button
                                type="button"
                                wire:click="gotoPage({{ $item }}, '{{ $pageName }}')"
                                wire:loading.attr="disabled"
                                wire:target="gotoPage({{ $item }}, '{{ $pageName }}')"
                                class="pagination-page"
                                aria-label="Ke halaman {{ $item }}"
                            >
                                {{ $item }}
                            </button>
                        @endif
                    @endforeach
                </div>

                @if($paginator->hasMorePages())
                    <button
                        type="button"
                        wire:click="nextPage('{{ $pageName }}')"
                        wire:loading.attr="disabled"
                        wire:target="nextPage('{{ $pageName }}')"
                        class="pagination-control"
                        aria-label="Halaman berikutnya"
                        title="Halaman berikutnya"
                    >
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                @else
                    <span class="pagination-control" aria-disabled="true" aria-label="Halaman berikutnya" title="Halaman berikutnya">
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif