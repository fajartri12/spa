@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Data Siswa', 'url' => route('students.index')], ['label' => 'Kenaikan Kelas']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Kenaikan Kelas</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Pindahkan siswa ke kelas pada tahun ajaran berikutnya. Setiap pemindahan tercatat pada riwayat kelas siswa.
            </p>
        </div>
        <a href="{{ route('students.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="w-4 h-4"></i><span>Data Siswa</span>
        </a>
    </div>

    <form wire:submit="promote" novalidate>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
            <div class="xl:col-span-2 space-y-4">
                <div class="card p-5 space-y-4">
                    <h2 class="text-[15px] font-semibold text-gray-900">1. Pilih Siswa Asal</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.select wire:model.live="source_unit_id" name="source_unit_id" label="Unit" :options="$units" placeholder="Semua Unit" />
                        <x-ui.select wire:model.live="source_year_id" name="source_year_id" label="Tahun Ajaran" :options="$years" placeholder="Semua Tahun" />
                        <x-ui.select wire:model.live="source_class_id" name="source_class_id" label="Kelas Asal" :options="$sourceClasses" placeholder="Semua Kelas" />
                    </div>

                    @if($availableStudents === [])
                        <x-ui.empty-state
                            title="Tidak ada siswa aktif"
                            description="Sesuaikan filter unit, tahun ajaran, atau kelas asal."
                            icon="users"
                            class="py-8"
                        />
                    @else
                        <div class="max-h-80 overflow-y-auto rounded-lg border border-[#e5e9f0]">
                            <table class="w-full text-[13px]">
                                <thead class="sticky top-0 bg-[#f8fafc] text-left text-[#64748b]">
                                    <tr>
                                        <th class="px-3 py-2 w-10"></th>
                                        <th class="px-3 py-2">Siswa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#eef2f7]">
                                    @foreach($availableStudents as $id => $label)
                                        <tr wire:key="candidate-{{ $id }}">
                                            <td class="px-3 py-2">
                                                <input type="checkbox" wire:model.live="selected" value="{{ $id }}"
                                                    class="rounded border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                                                    aria-label="Pilih {{ $label }}">
                                            </td>
                                            <td class="px-3 py-2 text-[#374151]">{{ $label }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[12px] text-[#8994a3]">{{ count($selected) }} dari {{ count($availableStudents) }} siswa dipilih.</p>
                    @endif
                </div>

                <div class="card p-5 space-y-4">
                    <h2 class="text-[15px] font-semibold text-gray-900">2. Tentukan Kelas Tujuan</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.select wire:model.live="target_unit_id" name="target_unit_id" label="Unit Tujuan" :options="$units" placeholder="-- Pilih Unit --" required />
                        <x-ui.select wire:model.live="target_year_id" name="target_year_id" label="Tahun Ajaran Tujuan" :options="$years" placeholder="-- Pilih Tahun --" required />
                        <x-ui.select wire:model.live="target_class_id" name="target_class_id" label="Kelas Tujuan" :options="$targetClasses" placeholder="-- Pilih Kelas --" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select wire:model.live="target_status" name="target_status" label="Status Siswa Setelah Proses" :options="$statuses" required hint="Pilih Lulus untuk rombongan belajar tingkat akhir." />
                        <x-ui.input wire:model.live="note" name="note" label="Catatan" placeholder="Opsional, misal: kenaikan tahun ajaran 2027/2028" />
                    </div>
                </div>
            </div>

            <div class="xl:col-span-1">
                <div class="card p-5 space-y-4 xl:sticky xl:top-4">
                    <h2 class="text-[15px] font-semibold text-gray-900 border-b border-[#e1e6ed] pb-3">Ringkasan</h2>
                    <dl class="space-y-2 text-[13px]">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#8994a3]">Siswa dipilih</dt>
                            <dd class="font-semibold text-[#111827] tabular">{{ count($selected) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#8994a3]">Kelas tujuan</dt>
                            <dd class="font-medium text-[#111827]">{{ $targetClass?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#8994a3]">Status baru</dt>
                            <dd class="font-medium text-[#111827]">{{ $statuses[$target_status] ?? '-' }}</dd>
                        </div>
                    </dl>

                    @if($selectedStudents->isNotEmpty())
                        <div class="rounded-lg bg-[#f8fafc] p-3 max-h-52 overflow-y-auto">
                            <p class="text-[11px] uppercase tracking-wide text-[#8994a3] mb-2">Pratinjau siswa</p>
                            <ul class="space-y-1.5 text-[12px]">
                                @foreach($selectedStudents as $student)
                                    <li wire:key="preview-{{ $student->id }}" class="flex items-center justify-between gap-2">
                                        <span class="truncate text-[#374151]">{{ $student->name }}</span>
                                        <span class="shrink-0 text-[#8994a3]">{{ $student->schoolClass?->name ?? '-' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($clearanceBlockers->isNotEmpty())
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-[12px] font-semibold text-amber-900">Belum bebas tanggungan</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-amber-800">
                                {{ $clearanceBlockers->count() }} siswa masih memiliki sisa tagihan atau belum diperiksa.
                                Kelulusan akan ditolak sampai tagihan diselesaikan atau pengecualian dicatat.
                            </p>
                            <ul class="mt-2 max-h-32 space-y-1 overflow-y-auto text-[11px] text-amber-900">
                                @foreach($clearanceBlockers as $blocker)
                                    <li wire:key="blocker-{{ $blocker['student']->id }}" class="flex items-center justify-between gap-2">
                                        <span class="truncate">{{ $blocker['student']->name }}</span>
                                        <span class="shrink-0 tabular">
                                            {{ $blocker['status'] === null ? 'Belum diperiksa' : 'Rp ' . number_format($blocker['remaining'], 0, ',', '.') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label class="flex items-start gap-2.5 text-[13px] text-[#475569] cursor-pointer border-t border-[#e1e6ed] pt-3">
                        <input type="checkbox" wire:model.live="confirmed" class="mt-0.5 rounded border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]">
                        <span>Saya memastikan kelas tujuan dan daftar siswa sudah benar.</span>
                    </label>
                    @error('confirmed')<p class="form-error">{{ $message }}</p>@enderror

                    <x-ui.button variant="primary" type="submit" icon="arrow-up-right" class="w-full justify-center" loading>
                        Proses Kenaikan Kelas
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>
</div>
