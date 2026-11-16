@props([
    'items' => [], // [['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Data Siswa']]
])

<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'text-[12px] text-[#8994a3]']) }}>
    <ol class="flex flex-wrap items-center gap-1.5">
        <li>
            <a href="{{ route('dashboard') }}" class="hover:text-[#2872c8] transition inline-flex items-center gap-1">
                <i data-lucide="house" class="w-3.5 h-3.5"></i>
                <span>Beranda</span>
            </a>
        </li>

        @foreach($items as $item)
            <li aria-hidden="true" class="text-[#cbd5e1]">
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </li>
            <li>
                @if(! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-[#2872c8] transition">{{ $item['label'] }}</a>
                @else
                    <span class="text-[#475569] font-medium" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
