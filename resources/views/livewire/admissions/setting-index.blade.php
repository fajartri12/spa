@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Pengaturan'], ['label' => $definition['label']]]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">{{ $definition['label'] }}</h1>
            <p class="text-[13px] text-[#64748b] mt-1">{{ $definition['description'] }}</p>
        </div>
    </div>

    {{-- Navigasi Bagian --}}
    <div class="card px-2 py-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            @foreach($sectionList as $key => $item)
                <a
                    href="{{ route('admissions.settings', ['section' => $key]) }}"
                    @class([
                        'flex items-center gap-2 px-3 py-2 rounded-[5px] text-[13px] transition',
                        'bg-[#eff6ff] text-[#2872c8] font-semibold' => $key === $section,
                        'text-[#475569] hover:bg-[#f8fafc]' => $key !== $section,
                    ])
                    wire:navigate
                >
                    <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Formulir Pengaturan --}}
    <form wire:submit="save" class="card p-5 space-y-4" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($definition['fields'] as $field)
                @if($field['type'] === 'checkbox')
                    <label class="flex items-center gap-2 text-sm text-[#475569] sm:col-span-1">
                        <input type="checkbox" wire:model="values.{{ $field['key'] }}" class="rounded border-[#cbd5e1]">
                        <span>{{ $field['label'] }}</span>
                    </label>
                @elseif($field['type'] === 'textarea')
                    <div class="sm:col-span-2">
                        <x-ui.textarea
                            wire:model="values.{{ $field['key'] }}"
                            name="values.{{ $field['key'] }}"
                            :label="$field['label']"
                            :hint="$field['hint'] ?? null"
                            rows="4"
                        />
                    </div>
                @else
                    <x-ui.input
                        wire:model="values.{{ $field['key'] }}"
                        name="values.{{ $field['key'] }}"
                        :type="$field['type']"
                        :label="$field['label']"
                        :hint="$field['hint'] ?? null"
                    />
                @endif
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e1e6ed]">
            @can('admissions.update')
                <x-ui.button type="submit" variant="primary" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pengaturan</span>
                </x-ui.button>
            @else
                <p class="text-[12px] text-[#8994a3]">Anda tidak memiliki akses untuk mengubah pengaturan PPDB.</p>
            @endcan
        </div>
    </form>
</div>
