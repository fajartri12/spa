@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Role & Hak Akses']]" />
@endsection

<div class="space-y-4">
    <div>
        <h1 class="page-title">Role & Hak Akses</h1>
        <p class="text-[13px] text-[#64748b] mt-1">
            Atur permission setiap role. Perubahan berlaku untuk seluruh pengguna pada role tersebut.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card !p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e1e6ed]">
                <h3 class="text-[15px] font-semibold text-gray-900">Daftar Role</h3>
                <p class="text-[12px] text-[#8994a3] mt-0.5">{{ $roles->count() }} role · {{ $totalPermissions }} permission</p>
            </div>

            <ul class="divide-y divide-[#eef2f7]">
                @foreach($roles as $role)
                    <li>
                        <button
                            type="button"
                            wire:click="edit({{ $role->id }})"
                            @disabled(! in_array($role->name, $editableRoleNames, true))
                            title="{{ in_array($role->name, $editableRoleNames, true) ? 'Atur hak akses' : 'Role Anda sendiri tidak dapat diubah' }}"
                            class="w-full text-left px-4 py-3 hover:bg-[#f8fafc] transition disabled:opacity-50 disabled:cursor-not-allowed {{ $editingId === $role->id ? 'bg-[#eaf2fc]' : '' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-[13px] text-[#111827]">{{ $roleLabels[$role->name] ?? $role->name }}</span>
                                <span class="text-[12px] text-[#8994a3] tabular">{{ $role->permissions_count }}</span>
                            </div>
                            <div class="text-[12px] text-[#8994a3] mt-0.5">{{ $role->users_count }} pengguna</div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="lg:col-span-2 space-y-4">
            @if($editingId === null)
                <x-ui.card>
                    <x-ui.empty-state
                        title="Pilih Role"
                        description="Pilih salah satu role pada daftar di samping untuk mengatur hak aksesnya."
                    />
                </x-ui.card>
            @else
                <x-ui.card
                    :title="'Hak Akses — ' . ($roleLabels[$roleName] ?? $roleName)"
                    :subtitle="count($selected) . ' dari ' . $totalPermissions . ' permission dipilih'"
                >
                    <x-slot:action>
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="selectAll">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                            <span>Pilih Semua</span>
                        </x-ui.button>
                        <x-ui.button variant="secondary" size="sm" type="button" wire:click="clearAll">
                            <i data-lucide="x" class="w-4 h-4"></i>
                            <span>Kosongkan</span>
                        </x-ui.button>
                    </x-slot:action>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($groups as $prefix => $group)
                            @php
                                $names = array_column($group['permissions'], 'name');
                                $allSelected = $names !== [] && array_diff($names, $selected) === [];
                            @endphp

                            <div class="border border-[#e1e6ed] rounded-[5px] overflow-hidden">
                                <button
                                    type="button"
                                    wire:click="toggleGroup('{{ $prefix }}')"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-[#f8fafc] hover:bg-[#eef2f7] transition text-left"
                                >
                                    <span class="text-[13px] font-medium text-[#111827]">{{ $group['label'] }}</span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-[11px] text-[#8994a3] tabular">{{ count(array_intersect($names, $selected)) }}/{{ count($names) }}</span>
                                        @if($allSelected)
                                            <i data-lucide="check-circle-2" class="w-4 h-4 text-[#438a21]"></i>
                                        @endif
                                    </span>
                                </button>

                                <div class="px-3 py-2 space-y-1.5">
                                    @foreach($group['permissions'] as $permission)
                                        <label class="flex items-center gap-2 cursor-pointer select-none">
                                            <input
                                                type="checkbox"
                                                value="{{ $permission['name'] }}"
                                                @checked(in_array($permission['name'], $selected, true))
                                                wire:click="toggle('{{ $permission['name'] }}')"
                                                class="w-4 h-4 rounded-[3px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                                            >
                                            <span class="text-[12px] text-[#475569]">{{ $permission['action'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @can('roles.update')
                        <div class="flex items-center justify-end gap-2 pt-4 mt-4 border-t border-[#eef2f7]">
                            <x-ui.button variant="primary" type="button" wire:click="save" loading="save">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                <span>Simpan Hak Akses</span>
                            </x-ui.button>
                        </div>
                    @endcan
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
