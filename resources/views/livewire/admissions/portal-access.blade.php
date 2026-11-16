@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Akses Portal']]" />
@endsection

<div class="space-y-4" x-data="{
    copied: false,
    copy(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        });
    },
}">
    {{-- Header --}}
    <div>
        <h1 class="page-title">Akses Portal PPDB</h1>
        <p class="text-[13px] text-[#64748b] mt-1">
            Bagikan tautan pendaftaran mandiri kepada calon siswa dan orang tua.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Status Portal --}}
        <div class="card p-5 lg:col-span-2 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-[15px] font-semibold text-[#111827]">Status Pendaftaran</h2>
                    <p class="text-[12px] text-[#8994a3] mt-0.5">Ditentukan oleh periode PPDB yang berstatus dibuka.</p>
                </div>
                <x-ui.badge :variant="$period ? 'success' : 'danger'" dot>
                    {{ $period ? 'Dibuka' : 'Ditutup' }}
                </x-ui.badge>
            </div>

            @if($period)
                <div class="rounded-[6px] bg-[#f8fafc] border border-[#e1e6ed] p-4 space-y-1">
                    <p class="text-[13px] font-semibold text-[#111827]">{{ $period->name }}</p>
                    <p class="text-[12px] text-[#64748b] tabular">
                        {{ $period->starts_at->format('d/m/Y') }} &ndash; {{ $period->ends_at->format('d/m/Y') }}
                    </p>
                    @if($period->description)
                        <p class="text-[12px] text-[#64748b]">{{ $period->description }}</p>
                    @endif
                </div>
            @else
                <div class="rounded-[6px] bg-[#fef2f2] border border-[#fecaca] p-4">
                    <p class="text-[13px] text-[#c0392b]">
                        Belum ada periode aktif. Portal menampilkan halaman "pendaftaran ditutup" sampai periode dibuka.
                    </p>
                </div>
            @endif

            <div>
                <label class="form-label">Tautan Pendaftaran Publik</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $registerUrl }}"
                        class="form-input tabular text-[13px]"
                        aria-label="Tautan pendaftaran publik"
                    >
                    <button type="button" @click="copy('{{ $registerUrl }}')" class="btn btn-secondary whitespace-nowrap">
                        <i data-lucide="arrow-right" class="w-4 h-4" x-show="!copied"></i>
                        <i data-lucide="check" class="w-4 h-4" x-show="copied" x-cloak></i>
                        <span x-text="copied ? 'Tersalin' : 'Salin Tautan'">Salin Tautan</span>
                    </button>
                    <a href="{{ $registerUrl }}" target="_blank" rel="noopener" class="btn btn-primary whitespace-nowrap">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        <span>Buka Portal</span>
                    </a>
                </div>
                <p class="form-help">Portal hanya menampilkan formulir pendaftaran, bukan data internal.</p>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="card p-5 space-y-4">
            <h2 class="text-[15px] font-semibold text-[#111827]">Ringkasan</h2>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[13px] text-[#64748b]">Total Pendaftar</span>
                    <span class="text-[14px] font-semibold text-[#111827] tabular">{{ number_format($totalApplicants, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[13px] text-[#64748b]">Pengumuman Terbit</span>
                    <span class="text-[14px] font-semibold text-[#111827] tabular">{{ $publishedContents->count() }}</span>
                </div>
            </div>

            <div class="pt-3 border-t border-[#e1e6ed] space-y-2">
                <a href="{{ route('admissions.applicants') }}" class="btn btn-secondary w-full" wire:navigate>
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Data Pendaftar</span>
                </a>
                <a href="{{ route('admissions.contents') }}" class="btn btn-secondary w-full" wire:navigate>
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    <span>Kelola Pengumuman</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Pengumuman Terbit --}}
    <div class="card !p-0 overflow-hidden">
        <div class="px-5 py-3 border-b border-[#e1e6ed] flex items-center justify-between">
            <h2 class="text-[15px] font-semibold text-[#111827]">Pratinjau Pengumuman Terbit</h2>
            <span class="text-[12px] text-[#8994a3]">5 terbaru</span>
        </div>

        <x-ui.table
            :empty="$publishedContents->isEmpty()"
            empty-title="Belum Ada Pengumuman Terbit"
            empty-description="Publikasikan pengumuman agar tampil pada portal pendaftaran."
        >
            <x-slot:head>
                <tr>
                    <th>Judul</th>
                    <th class="w-56">Periode</th>
                    <th class="w-40">Diperbarui</th>
                </tr>
            </x-slot:head>

            @foreach($publishedContents as $content)
                <tr wire:key="published-{{ $content->id }}">
                    <td class="font-medium text-[#111827]">{{ $content->title }}</td>
                    <td class="text-[#475569]">{{ $content->admissionPeriod?->name ?? 'Umum' }}</td>
                    <td class="text-[#475569] text-[12px]">{{ $content->updated_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>
</div>
