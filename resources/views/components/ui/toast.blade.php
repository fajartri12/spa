@props([
    'position' => 'top-right',
])

@php
    $positionClasses = match ($position) {
        'top-left' => 'top-4 left-4',
        'top-center' => 'top-4 left-1/2 -translate-x-1/2',
        'bottom-right' => 'bottom-4 right-4',
        'bottom-left' => 'bottom-4 left-4',
        default => 'top-4 right-4',
    };
@endphp

<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: detail.type ?? 'info', message: detail.message ?? '', title: detail.title ?? null });
            setTimeout(() => this.remove(id), detail.duration ?? 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }"
    x-on:toast.window="add($event.detail)"
    class="fixed {{ $positionClasses }} z-[80] flex flex-col gap-2 w-[calc(100%-2rem)] max-w-sm pointer-events-none"
    aria-live="polite"
    aria-atomic="true"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0 translate-x-4"
            @class([
                'pointer-events-auto flex items-start gap-2.5 p-3.5 rounded-[6px] border shadow-[0_4px_16px_-2px_rgb(16_24_40/0.12)] text-[13px] bg-white',
            ])
        >
            <template x-if="toast.type === 'success'">
                <i data-lucide="circle-check" class="w-5 h-5 shrink-0 text-[#438a21]"></i>
            </template>
            <template x-if="toast.type === 'error'">
                <i data-lucide="circle-alert" class="w-5 h-5 shrink-0 text-[#c0392b]"></i>
            </template>
            <template x-if="toast.type === 'warning'">
                <i data-lucide="triangle-alert" class="w-5 h-5 shrink-0 text-[#b9770e]"></i>
            </template>
            <template x-if="toast.type === 'info'">
                <i data-lucide="info" class="w-5 h-5 shrink-0 text-[#2872c8]"></i>
            </template>

            <div class="flex-1">
                <template x-if="toast.title">
                    <p class="font-semibold text-[#111827]" x-text="toast.title"></p>
                </template>
                <p class="text-[#475569]" x-text="toast.message"></p>
            </div>

            <button type="button" @click="remove(toast.id)" class="text-[#94a3b8] hover:text-[#475569] transition" aria-label="Tutup notifikasi">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    </template>
</div>
