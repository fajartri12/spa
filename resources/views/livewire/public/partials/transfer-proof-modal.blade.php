{{--
    Modal unggah bukti transfer manual.

    Dipakai bersama oleh portal siswa dan portal wali (trait
    App\Livewire\Public\Concerns\UploadsTransferProof).
--}}
<div
    x-data="{ open: @entangle('showProofModal') }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="proof-modal-title"
>
    <div class="absolute inset-0 bg-[#0F172A]/50" @click="open = false" aria-hidden="true"></div>

    <div class="relative w-full max-w-md max-h-[92vh] overflow-y-auto rounded-t-[20px] bg-white p-5 shadow-xl sm:rounded-[20px]">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="proof-modal-title" class="text-base font-bold text-[#0F172A]">Unggah Bukti Transfer</h2>
                <p class="mt-0.5 text-xs text-[#64748B]">Bukti akan diverifikasi petugas sebelum dianggap lunas.</p>
            </div>
            <button type="button" wire:click="cancelProof" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-[#64748B] hover:bg-[#F1F5F9] focus:outline-none focus:ring-2 focus:ring-[#2563EB]" aria-label="Tutup dialog">
                <i data-lucide="x" aria-hidden="true" class="h-4 w-4"></i>
            </button>
        </div>

        <form wire:submit="submitProof" class="mt-4 space-y-3" novalidate>
            <div>
                <label for="proofAmount" class="mb-1 block text-xs font-semibold text-[#334155]">Nominal Transfer (Rp)</label>
                <input type="number" inputmode="numeric" min="1" id="proofAmount" wire:model="proofAmount" required
                       class="portal-field" placeholder="Contoh: 350000">
                @error('proofAmount') <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="proofNote" class="mb-1 block text-xs font-semibold text-[#334155]">Catatan <span class="font-normal text-[#94A3B8]">(opsional)</span></label>
                <input type="text" id="proofNote" wire:model="proofNote" maxlength="255"
                       class="portal-field" placeholder="Contoh: Transfer dari BCA an. Bapak Wali">
                @error('proofNote') <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="proofFile" class="mb-1 block text-xs font-semibold text-[#334155]">Berkas Bukti (JPG/PNG/PDF, maks 5 MB)</label>
                <input type="file" id="proofFile" wire:model="proofFile" accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-xs text-[#334155] file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-[#EFF6FF] file:px-3 file:text-xs file:font-semibold file:text-[#2563EB]">
                <div wire:loading wire:target="proofFile" class="mt-1 text-[11px] text-[#64748B]">Mengunggah berkas...</div>
                @error('proofFile') <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-[#E2E8F0] pt-3">
                <button type="button" wire:click="cancelProof"
                        class="inline-flex min-h-11 items-center rounded-xl border border-[#E2E8F0] px-4 text-sm font-semibold text-[#334155] hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                    Batal
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="submitProof"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#2563EB] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] disabled:opacity-60">
                    <i data-lucide="upload" aria-hidden="true" class="h-4 w-4"></i>
                    Kirim Bukti
                </button>
            </div>
        </form>
    </div>
</div>