@php use App\Support\Formatter; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Keuangan'], ['label' => 'Rekonsiliasi Bank']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Rekonsiliasi Bank</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Impor mutasi rekening koran lalu cocokkan dengan pembayaran siswa secara otomatis berdasarkan referensi atau nominal.
            </p>
        </div>
        @can('bank-reconciliations.import')
            <x-ui.button variant="primary" wire:click="openImport">
                <i data-lucide="upload" class="w-4 h-4"></i>
                <span>Impor Mutasi</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-1" title="Berkas Mutasi" subtitle="Pilih berkas untuk melihat barisnya.">
            @if($imports->isEmpty())
                <p class="text-[13px] text-[#8994a3]">Belum ada berkas mutasi yang diimpor.</p>
            @else
                <div class="space-y-2 max-h-[420px] overflow-y-auto scrollbar-thin">
                    @foreach($imports as $import)
                        <button
                            type="button"
                            wire:key="import-{{ $import->id }}"
                            wire:click="$set('activeImportId', {{ $import->id }})"
                            class="w-full text-left rounded-[6px] border px-3 py-2 transition {{ $activeImportId === $import->id ? 'border-[#2872c8] bg-[#eef4fc]' : 'border-[#e1e6ed] hover:bg-[#f8fafc]' }}"
                        >
                            <div class="text-[13px] font-medium text-[#111827]">{{ $import->file_name }}</div>
                            <div class="text-[12px] text-[#64748b]">
                                {{ $import->profile?->name ?? 'Tanpa profil' }} &middot; {{ $import->created_at?->format('d/m/Y H:i') }}
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-[12px]">
                                <x-ui.badge variant="success">{{ $import->matched_rows }} cocok</x-ui.badge>
                                <x-ui.badge variant="warning">{{ $import->unmatched_rows }} belum</x-ui.badge>
                                <span class="text-[#8994a3]">{{ $import->total_rows }} baris</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <div class="lg:col-span-2 space-y-4">
            @if($activeImport === null)
                <x-ui.card>
                    <x-ui.empty-state title="Belum Ada Berkas Dipilih" description="Impor mutasi rekening koran untuk mulai mencocokkan pembayaran." />
                </x-ui.card>
            @else
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.stat-card label="Total Kredit" :value="Formatter::rupiah((float) $activeImport->total_credit)" icon="arrow-down-left" tone="success" />
                    <x-ui.stat-card label="Tercocokkan" :value="$activeImport->matched_rows . ' baris'" icon="check-check" tone="primary" />
                    <x-ui.stat-card label="Belum Cocok" :value="$activeImport->unmatched_rows . ' baris'" icon="alert-circle" tone="warning" />
                </div>

                <div class="card px-4 py-3">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Keterangan atau referensi mutasi..." icon="search" />
                        </div>
                        <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$matchStatusOptions" />
                        <div class="flex items-end">
                            @can('bank-reconciliations.match')
                                <button type="button" wire:click="runAutoMatch" class="btn btn-secondary">
                                    <i data-lucide="wand-2" class="w-4 h-4"></i>
                                    <span>Cocokkan Otomatis</span>
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
                    <x-ui.table :empty="$lines->isEmpty()" empty-title="Tidak Ada Baris Mutasi" empty-description="Ubah filter status atau kata kunci pencarian.">
                        <x-slot:head>
                            <tr>
                                <th class="w-28">Tanggal</th>
                                <th>Keterangan</th>
                                <th class="w-32 text-right">Masuk</th>
                                <th class="w-32 text-right">Keluar</th>
                                <th class="w-56">Pembayaran</th>
                                <th class="w-28">Status</th>
                                <th class="w-32 text-right">Aksi</th>
                            </tr>
                        </x-slot:head>

                        @foreach($lines as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td class="text-[#475569]">{{ $line->transacted_at?->format('d/m/Y') }}</td>
                                <td>
                                    <div class="text-[#111827]">{{ $line->description ?? '-' }}</div>
                                    @if($line->reference && $line->reference !== $line->description)
                                        <div class="text-[12px] text-[#8994a3]">Ref: {{ $line->reference }}</div>
                                    @endif
                                </td>
                                <td class="text-right tabular {{ (float) $line->credit > 0 ? 'font-medium text-[#438a21]' : 'text-[#8994a3]' }}">
                                    {{ (float) $line->credit > 0 ? Formatter::rupiah((float) $line->credit) : '-' }}
                                </td>
                                <td class="text-right tabular {{ (float) $line->debit > 0 ? 'text-[#c0392b]' : 'text-[#8994a3]' }}">
                                    {{ (float) $line->debit > 0 ? Formatter::rupiah((float) $line->debit) : '-' }}
                                </td>
                                <td>
                                    @if($line->payment)
                                        <a href="{{ route('payments.show', $line->payment) }}" class="text-[13px] text-[#2872c8] hover:underline">
                                            {{ $line->payment->transaction_number }}
                                        </a>
                                        <div class="text-[12px] text-[#8994a3]">{{ $line->payment->student?->name }}</div>
                                    @else
                                        <span class="text-[#8994a3]">-</span>
                                    @endif
                                    @if($line->match_rule)
                                        <div class="text-[11px] uppercase tracking-wide text-[#8994a3]">{{ $line->match_rule }}</div>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.badge :variant="$line->match_status->badgeVariant()">{{ $line->match_status->label() }}</x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('bank-reconciliations.match')
                                            @if($line->match_status === \App\Enums\ReconciliationMatchStatus::Unmatched && $line->isCredit())
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="startMatch({{ $line->id }})">
                                                    <i data-lucide="link" class="w-4 h-4"></i>
                                                    <span>Cocokkan</span>
                                                </button>
                                                <button type="button" class="btn btn-ghost btn-sm" wire:click="ignoreLine({{ $line->id }})" title="Abaikan baris">
                                                    <i data-lucide="eye-off" class="w-4 h-4"></i>
                                                </button>
                                            @elseif($line->match_status === \App\Enums\ReconciliationMatchStatus::Matched)
                                                <button type="button" class="btn btn-ghost btn-sm" wire:click="unmatch({{ $line->id }})" title="Lepas pencocokan">
                                                    <i data-lucide="unlink" class="w-4 h-4"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                    <x-ui.pagination :paginator="$lines" label="baris mutasi" />
                </div>
            @endif
        </div>
    </div>

    <x-ui.modal name="showImportModal" title="Impor Mutasi Rekening" subtitle="Berkas CSV atau Excel hasil ekspor internet banking." size="lg">
        <form wire:submit="import" class="space-y-4" novalidate>
            <x-ui.select wire:model="profileId" name="profileId" label="Profil Bank" :options="$profiles->pluck('name', 'id')->all()" required />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.select wire:model="bankAccountId" name="bankAccountId" label="Rekening" placeholder="Tidak ditentukan" :options="$bankAccounts" />
                <x-ui.select wire:model="accountId" name="accountId" label="Akun Kas/Bank" placeholder="Tidak ditentukan" :options="$cashAccounts" />
            </div>
            <div>
                <label for="statement_file" class="form-label">Berkas Mutasi <span class="text-[#c0392b]" aria-hidden="true">*</span></label>
                <input id="statement_file" type="file" wire:model="file" accept=".csv,.txt,.xlsx,.xls" class="block w-full text-[13px] file:mr-3 file:rounded-md file:border-0 file:bg-[#f1f5f9] file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-[#334155]">
                <p class="form-help">Format CSV, XLSX, atau XLS. Maksimal 10 MB.</p>
                @error('file') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-ui.textarea wire:model="notes" name="notes" label="Catatan" rows="2" placeholder="Opsional, mis. mutasi bulan November" />

            <div class="flex items-center gap-2">
                <input type="checkbox" id="autoMatch" wire:model="autoMatch" class="rounded border-[#cbd5e1]">
                <label for="autoMatch" class="text-[13px] text-[#475569]">Jalankan pencocokan otomatis setelah impor</label>
            </div>

            <details class="rounded-[6px] border border-[#e1e6ed] px-3 py-2">
                <summary class="cursor-pointer text-[13px] font-medium text-[#111827]">Timpa nama kolom (opsional)</summary>
                <p class="mt-1 text-[12px] text-[#64748b]">Isi hanya bila format berkas berbeda dari template profil bank.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <x-ui.input wire:model="columnDate" name="columnDate" label="Kolom Tanggal" placeholder="Tanggal" />
                    <x-ui.input wire:model="columnDescription" name="columnDescription" label="Kolom Keterangan" placeholder="Keterangan" />
                    <x-ui.input wire:model="columnReference" name="columnReference" label="Kolom Referensi" placeholder="No. Referensi" />
                    <x-ui.input wire:model="columnDebit" name="columnDebit" label="Kolom Debit" placeholder="Debit" />
                    <x-ui.input wire:model="columnCredit" name="columnCredit" label="Kolom Kredit" placeholder="Kredit" />
                    <x-ui.input wire:model="columnBalance" name="columnBalance" label="Kolom Saldo" placeholder="Saldo" />
                </div>
            </details>

            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showImportModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="import">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    <span>Impor &amp; Cocokkan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showMatchModal" title="Cocokkan Mutasi dengan Pembayaran" subtitle="Pilih pembayaran yang sesuai. Hanya pembayaran yang belum pernah dicocokkan yang ditampilkan.">
        <form wire:submit="match" class="space-y-4" novalidate>
            <x-ui.input wire:model.live.debounce.300ms="paymentSearch" name="paymentSearch" label="Cari Pembayaran" placeholder="Nomor transaksi atau nama siswa..." icon="search" />
            <x-ui.select wire:model="selectedPaymentId" name="selectedPaymentId" label="Pembayaran" placeholder="Pilih pembayaran" :options="$paymentCandidates" required />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showMatchModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" loading="match">
                    <i data-lucide="link" class="w-4 h-4"></i>
                    <span>Cocokkan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
