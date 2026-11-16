@props([
    'name' => 'confirm',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
    'confirmLabel' => 'Ya, Lanjutkan',
    'cancelLabel' => 'Batal',
    'variant' => 'danger',
])

<div
    x-data="{ open: false, action: null }"
    x-on:open-confirm.window="
        if ($event.detail.name === '{{ $name }}') {
            action = $event.detail.action ?? null;
            open = true;
        }
    "
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    role="alertdialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-[#0f172a]/50"></div>

    <div
        x-show="open"
        x-transition
        class="relative w-full max-w-[400px] bg-white rounded-[8px] shadow-[0_12px_32px_-8px_rgb(16_24_40/0.24)] p-5"
    >
        <div class="flex items-start gap-3">
            <div @class([
                'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                'bg-[#fdedec]' => $variant === 'danger',
                'bg-[#fff5e5]' => $variant === 'warning',
                'bg-[#eaf2fc]' => $variant !== 'danger' && $variant !== 'warning',
            ])>
                <i data-lucide="{{ $variant === 'danger' ? 'triangle-alert' : 'circle-alert' }}"
                   @class([
                       'w-5 h-5',
                       'text-[#c0392b]' => $variant === 'danger',
                       'text-[#b9770e]' => $variant === 'warning',
                       'text-[#2872c8]' => $variant !== 'danger' && $variant !== 'warning',
                   ])></i>
            </div>
            <div class="flex-1">
                <h3 class="text-[15px] font-semibold text-gray-900">{{ $title }}</h3>
                <p class="text-[13px] text-[#64748b] mt-1 leading-relaxed">{{ $message }}</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-5">
            <x-ui.button variant="secondary" size="sm" @click="open = false">{{ $cancelLabel }}</x-ui.button>
            <x-ui.button
                :variant="$variant"
                size="sm"
                @click="open = false; if (action) action()"
            >{{ $confirmLabel }}</x-ui.button>
        </div>
    </div>
</div>
