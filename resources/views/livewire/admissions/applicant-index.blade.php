@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Data Pendaftar']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Data Pendaftar PPDB</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola berkas, status verifikasi, dan data calon siswa baru.
            </p>
        </div>

        @can('admissions.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Daftar Calon Siswa</span>
            </x-ui.button>
        @endcan
    </div>

    {{-- Ringkasan Ringkas --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-medium text-[#64748b]">Total Pendaftar</p>
                    <p class="text-[20px] font-semibold text-[#111827] mt-1 tabular">{{ number_format($totalApplicants, 0, ',', '.') }}</p>
                </div>
                <div class="w-9 h-9 rounded-full bg-[#f1f5f9] text-[#2872c8] flex items-center justify-center">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-medium text-[#64748b]">Terverifikasi</p>
                    <p class="text-[20px] font-semibold text-[#438a21] mt-1 tabular">{{ number_format($totalVerified, 0, ',', '.') }}</p>
                </div>
                <div class="w-9 h-9 rounded-full bg-[#ecfdf5] text-[#438a21] flex items-center justify-center">
                    <i data-lucide="badge-check" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-medium text-[#64748b]">Diterima / Lulus</p>
                    <p class="text-[20px] font-semibold text-[#2872c8] mt-1 tabular">{{ number_format($totalPassed, 0, ',', '.') }}</p>
                </div>
                <div class="w-9 h-9 rounded-full bg-[#eff6ff] text-[#2872c8] flex items-center justify-center">
                    <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Pendaftar"
                    placeholder="Nama, nomor pendaftaran, telepon..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="periodFilter"
                name="periodFilter"
                label="Periode"
                :options="$periodOptions"
                placeholder="Semua Periode"
            />

            <x-ui.select
                wire:model.live="statusFilter"
                name="statusFilter"
                label="Status"
                placeholder="Semua Status"
                :options="[
                    'registered' => 'Terdaftar',
                    'verified' => 'Terverifikasi',
                    'passed' => 'Diterima',
                    'rejected' => 'Ditolak',
                    'withdrawn' => 'Mengundurkan Diri',
                ]"
            />
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table
            :empty="$applicants->isEmpty()"
            empty-title="Belum Ada Calon Siswa"
            empty-description="Daftarkan calon siswa baru atau buka pendaftaran melalui portal PPDB."
        >
            <x-slot:head>
                <tr>
                    <th class="w-48">Nomor Pendaftaran</th>
                    <th>Nama Calon Siswa</th>
                    <th class="w-56">Periode</th>
                    <th class="w-32">Kontak</th>
                    <th class="w-24 text-center">Dokumen</th>
                    <th class="w-24 text-center">Tagihan</th>
                    <th class="w-32">Status</th>
                    <th class="w-20 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($applicants as $applicant)
                <tr wire:key="applicant-{{ $applicant->id }}">
                    <td class="font-medium tabular text-[#111827]">{{ $applicant->registration_number }}</td>
                    <td>
                        <a href="{{ route('admissions.show', $applicant) }}" class="font-medium text-[#2872c8] hover:underline" wire:navigate>
                            {{ $applicant->name }}
                        </a>
                        <p class="text-[12px] text-[#8994a3] mt-0.5">
                            {{ $applicant->gender === 'male' ? 'Laki-laki' : ($applicant->gender === 'female' ? 'Perempuan' : '-') }}
                            @if($applicant->birth_date)
                                &bull; {{ $applicant->birth_date->format('d/m/Y') }}
                            @endif
                        </p>
                    </td>
                    <td class="text-[#475569]">{{ $applicant->admissionPeriod->name }}</td>
                    <td class="text-[#475569] text-[12px]">
                        @if($applicant->phone)
                            <p class="tabular">{{ $applicant->phone }}</p>
                        @endif
                        @if($applicant->email)
                            <p class="text-[#8994a3] truncate max-w-[180px]">{{ $applicant->email }}</p>
                        @endif
                        @if(! $applicant->phone && ! $applicant->email)
                            <span class="text-[#8994a3]">-</span>
                        @endif
                    </td>
                    <td class="text-center tabular text-[#475569]">{{ $applicant->documents_count }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $applicant->bills_count }}</td>
                    <td>
                        @php
                            $variant = match ($applicant->status) {
                                'verified' => 'info',
                                'passed' => 'success',
                                'rejected' => 'danger',
                                'withdrawn' => 'neutral',
                                default => 'warning',
                            };
                            $label = match ($applicant->status) {
                                'verified' => 'Terverifikasi',
                                'passed' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'withdrawn' => 'Mundur',
                                default => 'Terdaftar',
                            };
                        @endphp
                        <x-ui.badge :variant="$variant" dot>{{ $label }}</x-ui.badge>
                    </td>
                    <td class="text-right">
                        <a href="{{ route('admissions.show', $applicant) }}" class="btn-icon" title="Detail &amp; Berkas" wire:navigate>
                            <i data-lucide="eye" class="w-4 h-4 text-[#2872c8]"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$applicants" label="calon siswa" />
    </div>

    {{-- Modal Pendaftaran Calon Siswa --}}
    <x-ui.modal
        name="showModal"
        title="Daftar Calon Siswa Baru"
        subtitle="Lengkapi formulir pendaftaran calon siswa."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.select
                wire:model="admission_period_id"
                name="admission_period_id"
                label="Periode PPDB"
                :options="$periodOptions"
                placeholder="Pilih periode"
                required
            />

            <x-ui.input wire:model="applicantName" name="applicantName" label="Nama Lengkap" placeholder="Nama calon siswa" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="email" name="email" type="email" label="Email" placeholder="nama@example.com" />
                <x-ui.input wire:model="phone" name="phone" label="Nomor Telepon / WhatsApp" placeholder="08xxxxxxxxxx" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select
                    wire:model="gender"
                    name="gender"
                    label="Jenis Kelamin"
                    :options="['male' => 'Laki-laki', 'female' => 'Perempuan']"
                    placeholder="Pilih jenis kelamin"
                />
                <x-ui.input wire:model="birth_place" name="birth_place" label="Tempat Lahir" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="birth_date" name="birth_date" type="date" label="Tanggal Lahir" />
                <x-ui.input wire:model="parent_name" name="parent_name" label="Nama Orang Tua / Wali" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="parent_phone" name="parent_phone" label="Nomor Telepon Orang Tua" />
                <x-ui.input wire:model="address" name="address" label="Alamat Singkat" />
            </div>

            <x-ui.textarea wire:model="notes" name="notes" label="Catatan Tambahan" rows="3" hint="Opsional." />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#e1e6ed]">
                <button type="button" @click="$dispatch('close-modal', 'showModal')" class="btn btn-secondary">
                    Batal
                </button>
                <x-ui.button type="submit" variant="primary" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pendaftar</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
