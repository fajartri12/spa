@props([
    'loading' => false,
    'empty' => true,
    'emptyTitle' => 'Belum Ada Data',
    'emptyDescription' => 'Data yang Anda cari tidak ditemukan.',
    'emptyAction' => null,
])

<div class="table-wrapper scrollbar-thin" @if($loading) wire:loading.class="opacity-60" @endif>
    <table {{ $attributes->merge(['class' => 'data-table']) }}>
        @if(isset($head))
            <thead>
                {{ $head }}
            </thead>
        @endif

        <tbody>
            @if($empty)
                <tr>
                    <td colspan="99" class="!py-12">
                        <x-ui.empty-state
                            :title="$emptyTitle"
                            :description="$emptyDescription"
                            :action="$emptyAction"
                        />
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
