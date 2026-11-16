@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB', 'url' => route('admissions.index')], ['label' => $applicant->registration_number]]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">{{ $applicant->name }}</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">
                {{ $applicant->registration_number }} &middot; {{ $applicant->admissionPeriod?->name ?? '-' }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($applicant->isConverted())
                <x-ui.badge variant="success" dot>Sudah Menjadi Siswa</x-ui.badge>
            @endif
            <x-ui.badge :variant="match($applicant->status) { 'verified', 'passed' => 'success', 'rejected' => 'danger', 'withdrawn' => 'neutral', default => 'info' }" dot>
                {{ ucfirst($applicant->status) }}
            </x-ui.badge>
            @can('admissions.update')
                @unless($applicant->isConverted())
                    <x-ui.button variant="primary" wire:click="openConvert">
                        <i data-lucide="user-plus" class="h-4 w-4"></i><span>Jadikan Siswa</span>
                    </x-ui.button>
                @endunless
            @endcan
            <a href="{{ route('admissions.index') }}" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="h-4 w-4"></i><span>Kembali</span>
            </a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Data Calon Siswa">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-[#64748b]">Email</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->email ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Telepon</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->phone ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Jenis Kelamin</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->gender === 'male' ? 'Laki-laki' : ($applicant->gender === 'female' ? 'Perempuan' : '-') }}</dd></div>
                <div><dt class="text-[#64748b]">Tempat, Tanggal Lahir</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->birth_place ?? '-' }}{{ $applicant->birth_date ? ', '.$applicant->birth_date->format('d/m/Y') : '' }}</dd></div>
                <div><dt class="text-[#64748b]">Orang Tua / Wali</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->parent_name ?? '-' }}</dd></div>
                <div><dt class="text-[#64748b]">Telepon Orang Tua</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->parent_phone ?? '-' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[#64748b]">Alamat</dt><dd class="mt-1 font-medium text-[#111827]">{{ $applicant->address ?? '-' }}</dd></div>
                @if($applicant->notes)
                    <div class="sm:col-span-2"><dt class="text-[#64748b]">Catatan</dt><dd class="mt-1 text-[#111827]">{{ $applicant->notes }}</dd></div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card title="Status Pendaftaran">
            <form wire:submit="updateStatus" class="space-y-3">
                <x-ui.select
                    wire:model="applicant_status"
                    name="applicant_status"
                    label="Status"
                    :options="['registered' => 'Terdaftar', 'verified' => 'Terverifikasi', 'passed' => 'Lulus', 'rejected' => 'Ditolak', 'withdrawn' => 'Mengundurkan Diri']"
                />
                @can('admissions.update')
                    <x-ui.button type="submit" variant="primary" class="w-full" loading="updateStatus">
                        <i data-lucide="save" class="w-4 h-4"></i><span>Simpan Status</span>
                    </x-ui.button>
                @endcan
            </form>
        </x-ui.card>
    </div>

    {{-- Dokumen --}}
    <x-ui.card title="Dokumen Persyaratan" subtitle="Disimpan pada private storage dan hanya dapat diunduh pengguna berizin.">
        @if($applicant->documents->isEmpty())
            <x-ui.empty-state title="Belum Ada Dokumen" description="Unggah dokumen persyaratan calon siswa." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-[#64748b]">
                            <th class="pb-3">Jenis</th>
                            <th class="pb-3">Berkas</th>
                            <th class="pb-3">Ukuran</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applicant->documents as $document)
                            <tr class="border-b last:border-0" wire:key="doc-{{ $document->id }}">
                                <td class="py-3">{{ str_replace('_', ' ', ucfirst($document->document_type)) }}</td>
                                <td class="py-3">
                                    <a href="{{ route('applicant-documents.download', $document) }}" class="text-[#2872c8] hover:underline">
                                        {{ $document->original_name }}
                                    </a>
                                </td>
                                <td class="py-3 tabular">{{ number_format($document->size / 1024, 0, ',', '.') }} KB</td>
                                <td class="py-3">
                                    <x-ui.badge :variant="match($document->status) { 'verified' => 'success', 'rejected' => 'danger', default => 'warning' }" dot>
                                        {{ ucfirst($document->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('admissions.verify')
                                            @if($document->status !== 'verified')
                                                <button type="button" wire:click="verifyDocument({{ $document->id }}, 'verified')" class="btn-icon" title="Verifikasi" aria-label="Verifikasi dokumen">
                                                    <i data-lucide="check" class="w-4 h-4 text-[#438a21]"></i>
                                                </button>
                                            @endif
                                            @if($document->status !== 'rejected')
                                                <button type="button" wire:click="verifyDocument({{ $document->id }}, 'rejected')" class="btn-icon" title="Tolak" aria-label="Tolak dokumen">
                                                    <i data-lucide="x" class="w-4 h-4 text-[#c0392b]"></i>
                                                </button>
                                            @endif
                                        @endcan
                                        @can('admissions.delete')
                                            <button type="button" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Hapus dokumen ini?" class="btn-icon" title="Hapus" aria-label="Hapus dokumen">
                                                <i data-lucide="trash-2" class="w-4 h-4 text-[#c0392b]"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('admissions.create')
            <form wire:submit="uploadDocument" class="mt-4 border-t border-[#eef2f7] pt-4 space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.select
                        wire:model="document_type"
                        name="document_type"
                        label="Jenis Dokumen"
                        :options="[
                            'birth_certificate' => 'Akta Kelahiran',
                            'family_card' => 'Kartu Keluarga',
                            'graduation_certificate' => 'Ijazah / SKL',
                            'report_card' => 'Rapor',
                            'photo' => 'Pas Foto',
                            'other' => 'Dokumen Lain',
                        ]"
                    />
                    <div>
                        <label for="document_file" class="form-label">Berkas (JPG, PNG, PDF — maks 5 MB)</label>
                        <input id="document_file" type="file" wire:model="document_file" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-[13px] file:mr-3 file:rounded-md file:border-0 file:bg-[#f1f5f9] file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-[#334155]">
                        @error('document_file') <p class="form-error">{{ $message }}</p> @enderror
                        @error('document_type') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" variant="primary" loading="uploadDocument">Unggah Dokumen</x-ui.button>
                    <span wire:loading wire:target="document_file" class="text-[12px] text-[#64748b]">Memproses…</span>
                </div>
            </form>
        @endcan
    </x-ui.card>

    {{-- Tagihan PPDB --}}
    <x-ui.card title="Tagihan PPDB">
        @if($applicant->bills->isEmpty())
            <x-ui.empty-state title="Belum Ada Tagihan" description="Buat tagihan biaya pendaftaran atau daftar ulang." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-[#64748b]">
                            <th class="pb-3">Nomor</th>
                            <th class="pb-3">Judul</th>
                            <th class="pb-3 text-right">Nominal</th>
                            <th class="pb-3">Jatuh Tempo</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applicant->bills as $bill)
                            <tr class="border-b last:border-0" wire:key="bill-{{ $bill->id }}">
                                <td class="py-3 font-medium tabular text-[#111827]">{{ $bill->bill_number }}</td>
                                <td class="py-3">{{ $bill->title }}</td>
                                <td class="py-3 text-right tabular">{{ \App\Support\Formatter::rupiah((float) $bill->amount) }}</td>
                                <td class="py-3 tabular">{{ $bill->due_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="py-3">
                                    <x-ui.badge :variant="match($bill->status) { 'paid' => 'success', 'cancelled' => 'danger', default => 'warning' }" dot>
                                        {{ ucfirst($bill->status) }}
                                    </x-ui.badge>
                                    @if($bill->paid_at)
                                        <div class="text-[11px] text-[#8994a3] mt-1">{{ $bill->paid_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('admissions.verify')
                                            @if($bill->status === 'unpaid')
                                                <button type="button" wire:click="updateBillStatus({{ $bill->id }}, 'paid')" class="btn-icon" title="Tandai lunas &amp; catat kas masuk" aria-label="Tandai lunas">
                                                    <i data-lucide="check" class="w-4 h-4 text-[#438a21]"></i>
                                                </button>
                                            @endif
                                            @if($bill->status === 'paid')
                                                <button type="button" wire:click="updateBillStatus({{ $bill->id }}, 'unpaid')" wire:confirm="Batalkan pembayaran tagihan ini? Kas keluar pembalik akan dicatat." class="btn-icon" title="Batalkan pembayaran" aria-label="Batalkan pembayaran">
                                                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-[#b45309]"></i>
                                                </button>
                                            @endif
                                            @if($bill->status !== 'cancelled')
                                                <button type="button" wire:click="updateBillStatus({{ $bill->id }}, 'cancelled')" wire:confirm="Batalkan tagihan ini?" class="btn-icon" title="Batalkan tagihan" aria-label="Batalkan tagihan">
                                                    <i data-lucide="ban" class="w-4 h-4 text-[#c0392b]"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('admissions.create')
            <form wire:submit="createBill" class="mt-4 border-t border-[#eef2f7] pt-4 space-y-3">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.input wire:model="bill_title" name="bill_title" label="Judul Tagihan" placeholder="Biaya Pendaftaran" />
                    <x-ui.input wire:model="bill_amount" name="bill_amount" label="Nominal" placeholder="150000" hint="Angka tanpa titik, mis. 150000" />
                    <x-ui.input wire:model="bill_due_date" name="bill_due_date" type="date" label="Jatuh Tempo" />
                </div>
                <x-ui.button type="submit" variant="primary" loading="createBill">Buat Tagihan</x-ui.button>
            </form>
        @endcan
    </x-ui.card>

    <x-ui.modal name="showConvertModal" title="Jadikan Siswa" subtitle="Data pendaftar disalin ke data induk siswa beserta penempatan kelas awal.">
        <form wire:submit="convert" class="space-y-4" novalidate>
            <div class="rounded-lg border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2 text-[12px] text-[#64748b]">
                Nama: <span class="font-medium text-[#111827]">{{ $applicant->name }}</span>
                &middot; No. Pendaftaran: <span class="tabular">{{ $applicant->registration_number }}</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model.live="convert_unit_id" name="convert_unit_id" label="Unit Pendidikan" :options="$units" placeholder="-- Pilih Unit --" required />
                <x-ui.select wire:model="convert_class_id" name="convert_class_id" label="Kelas" :options="$classes" placeholder="-- Belum Ditempatkan --" hint="Kosongkan bila penempatan kelas dilakukan nanti." />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="convert_nis" name="convert_nis" label="NIS" placeholder="Nomor induk siswa" required />
                <x-ui.input wire:model="convert_entry_year" name="convert_entry_year" type="number" label="Tahun Masuk" required />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showConvertModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="convert">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Konversi Sekarang</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
