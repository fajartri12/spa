@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master', 'url' => route('students.index')], ['label' => 'Beasiswa & Keringanan']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Beasiswa, Keringanan, & Subsidi Silang</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola program bantuan biaya pendidikan dan daftar siswa penerima hak beasiswa.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('scholarships.create')
                <x-ui.button variant="primary" wire:click="createProgram">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Program</span>
                </x-ui.button>
            @endcan
            @can('scholarships.award')
                <x-ui.button variant="secondary" wire:click="openAwardModal(null)">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Berikan Beasiswa</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-[#e5eaf0] flex items-center gap-6 text-sm font-medium">
        <button
            type="button"
            wire:click="$set('activeTab', 'programs')"
            class="pb-3 border-b-2 transition {{ $activeTab === 'programs' ? 'border-[#2872c8] text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#1e293b]' }}"
        >
            <i data-lucide="award" class="w-4 h-4 inline-block -mt-0.5 mr-1.5"></i>
            Program Beasiswa
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'awards')"
            class="pb-3 border-b-2 transition {{ $activeTab === 'awards' ? 'border-[#2872c8] text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#1e293b]' }}"
        >
            <i data-lucide="users" class="w-4 h-4 inline-block -mt-0.5 mr-1.5"></i>
            Penerima Active
        </button>
    </div>

    @if($activeTab === 'programs')
        <!-- TAB 1: PROGRAM BEASISWA -->
        <div class="card px-4 py-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Program" placeholder="Kode, nama, sponsor..." icon="search" />
                </div>
                <x-ui.select wire:model.live="fundingFilter" name="fundingFilter" label="Sumber Dana" placeholder="Semua Sumber Dana" :options="['internal' => 'Internal Madrasah', 'government' => 'Pemerintah', 'private' => 'Swasta / Donatur']" />
                <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="['1' => 'Aktif', '0' => 'Nonaktif']" />
            </div>
        </div>

        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table :empty="$programs->isEmpty()" empty-title="Belum Ada Program Beasiswa" empty-description="Buat program beasiswa baru untuk memberikan potongan atau keringanan kepada siswa.">
                <x-slot:head>
                    <tr>
                        <th class="w-28">Kode</th>
                        <th>Nama Program</th>
                        <th>Sumber Dana & Sponsor</th>
                        <th class="w-32">Kategori</th>
                        <th class="w-36 text-right">Potongan</th>
                        <th class="w-28 text-center">Kuota / Penerima</th>
                        <th class="w-28">Status</th>
                        <th class="w-24 text-right">Aksi</th>
                    </tr>
                </x-slot:head>

                @foreach($programs as $program)
                    @php $activeCount = $activeCounts[$program->id] ?? 0; @endphp
                    <tr wire:key="program-{{ $program->id }}">
                        <td class="font-mono text-xs font-semibold text-[#2872c8]">{{ $program->code }}</td>
                        <td>
                            <div class="font-medium text-[#111827]">{{ $program->name }}</div>
                            @if($program->valid_from || $program->valid_to)
                                <div class="text-[12px] text-[#8994a3]">
                                    Berlaku: {{ $program->valid_from ? $program->valid_from->format('d/m/Y') : 'Awal' }}
                                    – {{ $program->valid_to ? $program->valid_to->format('d/m/Y') : 'Selamanya' }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="text-[#334155] font-medium">{{ $program->fundingSourceLabel() }}</div>
                            <div class="text-[12px] text-[#8994a3]">{{ $program->sponsor ?: '—' }}</div>
                        </td>
                        <td class="text-[#475569]">{{ $program->paymentCategory?->name ?? 'Semua Tagihan' }}</td>
                        <td class="text-right tabular font-medium text-[#111827]">
                            @if($program->discount_type === \App\Services\DiscountService::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $program->discount_value, 2, ',', '.'), '0'), ',') }}%
                            @else
                                {{ \App\Support\Formatter::rupiah((float) $program->discount_value) }}
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="font-semibold text-[#1e293b]">{{ $activeCount }}</span>
                            <span class="text-[12px] text-[#8994a3]">
                                / {{ $program->quota ? $program->quota : '∞' }}
                            </span>
                        </td>
                        <td>
                            @if($program->is_active)
                                <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                @can('scholarships.award')
                                    <button type="button" wire:click="openAwardModal({{ $program->id }})" class="btn-icon text-[#2872c8]"
                                        title="Berikan Beasiswa" aria-label="Berikan Beasiswa {{ $program->name }}">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                                    </button>
                                @endcan
                                @can('scholarships.update')
                                    <button type="button" wire:click="editProgram({{ $program->id }})" class="btn-icon"
                                        title="Ubah Program" aria-label="Ubah Program {{ $program->name }}">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </button>
                                @endcan
                                @can('scholarships.delete')
                                    <button type="button" wire:click="confirmDeleteProgram({{ $program->id }})"
                                        class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                        title="Hapus Program" aria-label="Hapus Program {{ $program->name }}">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$programs" label="program" />
        </div>
    @else
        <!-- TAB 2: DAFTAR PENERIMA BEASISWA -->
        <div class="card px-4 py-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Penerima" placeholder="Nama siswa, NIS, atau program..." icon="search" />
                <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status Penerimaan" placeholder="Semua Status" :options="['active' => 'Aktif', 'revoked' => 'Dicabut']" />
            </div>
        </div>

        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table :empty="$awards->isEmpty()" empty-title="Belum Ada Penerima Beasiswa" empty-description="Klik tombol 'Berikan Beasiswa' untuk menetapkan siswa penerima.">
                <x-slot:head>
                    <tr>
                        <th>Siswa</th>
                        <th>Program Beasiswa</th>
                        <th>Periode Berlaku</th>
                        <th>Status</th>
                        <th>Diberikan Oleh</th>
                        <th class="w-24 text-right">Aksi</th>
                    </tr>
                </x-slot:head>

                @foreach($awards as $award)
                    <tr wire:key="award-{{ $award->id }}">
                        <td>
                            <div class="font-medium text-[#111827]">{{ $award->student?->name ?? '-' }}</div>
                            <div class="text-[12px] text-[#8994a3]">
                                {{ $award->student?->nis }} · {{ $award->student?->schoolClass?->name ?? '-' }} ({{ $award->student?->unit?->name ?? '-' }})
                            </div>
                        </td>
                        <td>
                            <div class="font-medium text-[#2872c8]">{{ $award->program?->name }}</div>
                            <div class="text-[12px] text-[#64748b]">{{ $award->program?->fundingSourceLabel() }}</div>
                        </td>
                        <td class="text-[#475569] text-xs">
                            {{ $award->starts_on ? $award->starts_on->format('d/m/Y') : 'Awal' }}
                            – {{ $award->ends_on ? $award->ends_on->format('d/m/Y') : 'Selamanya' }}
                        </td>
                        <td>
                            @if($award->isActive())
                                <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                            @else
                                <x-ui.badge variant="danger" dot>Dicabut</x-ui.badge>
                                @if($award->revocation_reason)
                                    <div class="text-[11px] text-[#c0392b] mt-0.5" title="{{ $award->revocation_reason }}">
                                        {{ Str::limit($award->revocation_reason, 25) }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="text-xs text-[#64748b]">
                            {{ $award->awardedBy?->name ?? 'Sistem' }}
                        </td>
                        <td>
                            <div class="flex items-center justify-end">
                                @if($award->isActive())
                                    @can('scholarships.award')
                                        <button type="button" wire:click="confirmRevokeAward({{ $award->id }})"
                                            class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                            title="Cabut Beasiswa" aria-label="Cabut Beasiswa {{ $award->student?->name }}">
                                            <i data-lucide="user-x" class="w-4 h-4"></i>
                                        </button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$awards" label="penerima" />
        </div>
    @endif

    <!-- Program Form Modal -->
    <x-ui.modal name="showProgramModal" :title="$editingProgramId ? 'Ubah Program Beasiswa' : 'Tambah Program Beasiswa'" subtitle="Atur kuota, diskon, dan sumber dana beasiswa.">
        <form wire:submit="saveProgram" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="code" name="code" label="Kode Program" placeholder="Contoh: BS-PRESTASI-2026" required />
                <x-ui.input wire:model="name" name="name" label="Nama Program Beasiswa" placeholder="Contoh: Beasiswa Prestasi Tahfidz" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model="funding_source" name="funding_source" label="Sumber Dana" :options="['internal' => 'Internal Madrasah', 'government' => 'Pemerintah (KIP/BPOW)', 'private' => 'Swasta / Donatur']" required />
                <x-ui.input wire:model="sponsor" name="sponsor" label="Sponsor / Instansi" placeholder="Contoh: Kemenag, Bank Syariah, Yayasan..." />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model.live="discount_type" name="discount_type" label="Tipe Diskon" :options="['percent' => 'Persen (%)', 'fixed' => 'Nominal Rupiah (Rp)']" required />
                <x-ui.input wire:model="discount_value" name="discount_value" label="Nilai Potongan" :prefix="$discount_type === 'fixed' ? 'Rp' : null" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model="payment_category_id" name="payment_category_id" label="Kategori Tagihan" :options="$categories" placeholder="-- Semua Kategori --" hint="Biarkan kosong jika berlaku untuk seluruh tagihan." />
                <x-ui.select wire:model="academic_year_id" name="academic_year_id" label="Tahun Ajaran" :options="$academicYears" placeholder="-- Semua Tahun Ajaran --" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input wire:model="quota" name="quota" type="number" label="Kuota Penerima" placeholder="Kosong = tidak terbatas" />
                <x-ui.input wire:model="valid_from" name="valid_from" type="date" label="Mulai Berlaku" />
                <x-ui.input wire:model="valid_to" name="valid_to" type="date" label="Berlaku Sampai" />
            </div>

            <x-ui.textarea wire:model="notes" name="notes" label="Catatan / Syarat Kriteria" placeholder="Kriteria penerima, misal hafalan 3 juz..." />

            <label for="is_program_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input id="is_program_active" type="checkbox" wire:model="is_active" class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]">
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Program Aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Program yang tidak aktif tidak dapat diberikan ke siswa baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showProgramModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="saveProgram">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingProgramId ? 'Simpan Perubahan' : 'Buat Program' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Program Delete Confirm Modal -->
    <x-ui.modal name="showProgramDeleteModal" title="Hapus Program Beasiswa" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Program beasiswa ini akan dihapus. Penerima yang sudah aktif tidak akan otomatis terhapus melainkan potongan mereka tetap tersimpan di riwayat.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-5">
            <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showProgramDeleteModal', false)">Batal</x-ui.button>
            <x-ui.button variant="danger" size="sm" type="button" wire:click="deleteProgram" loading="deleteProgram">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Ya, Hapus</span>
            </x-ui.button>
        </div>
    </x-ui.modal>

    <!-- Award Scholarship Modal -->
    <x-ui.modal name="showAwardModal" title="Berikan Beasiswa kepada Siswa" subtitle="Pilih program beasiswa dan penetapan nama siswa penerima.">
        <form wire:submit="awardScholarship" class="space-y-4" novalidate>
            <x-ui.select wire:model="selectedProgramId" name="selectedProgramId" label="Program Beasiswa" placeholder="-- Pilih Program Beasiswa --" required>
                @foreach($activePrograms as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->fundingSourceLabel() }}) — {{ $p->discount_type === 'percent' ? $p->discount_value.'%' : 'Rp '.number_format((float)$p->discount_value, 0, ',', '.') }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model="student_id" name="student_id" label="Siswa Penerima" :options="$students" placeholder="-- Pilih Siswa --" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="starts_on" name="starts_on" type="date" label="Tanggal Mulai Hak Beasiswa" />
                <x-ui.input wire:model="ends_on" name="ends_on" type="date" label="Tanggal Berakhir (Opsional)" />
            </div>

            <x-ui.textarea wire:model="award_notes" name="award_notes" label="Keterangan / Alasan Pemberian" placeholder="Misal: Pemenang Juara 1 KSM Kabupaten..." />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showAwardModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="awardScholarship">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Tetapkan Beasiswa</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Revoke Award Modal -->
    <x-ui.modal name="showRevokeModal" title="Cabut Penerimaan Beasiswa" size="sm">
        <form wire:submit="revokeAward" class="space-y-4" novalidate>
            <p class="text-[13px] text-[#64748b]">
                Pencabutan beasiswa akan menghentikan potongan otomatis untuk tagihan siswa di masa mendatang.
            </p>

            <x-ui.textarea wire:model="revocation_reason" name="revocation_reason" label="Alasan Pencabutan" placeholder="Contoh: Pindah sekolah, pelanggaran tata tertib, nilai di bawah standar..." required />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showRevokeModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" size="sm" type="submit" loading="revokeAward">
                    <i data-lucide="user-x" class="w-4 h-4"></i>
                    <span>Ya, Cabut</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
