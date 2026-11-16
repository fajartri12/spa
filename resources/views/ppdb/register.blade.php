@extends('layouts.public', ['title' => 'Pendaftaran Calon Siswa Baru'])

@section('content')
<div class="bg-white rounded-[8px] shadow-sm border border-[#e1e6ed] overflow-hidden">
    {{-- Header Banner Form --}}
    <div class="border-b border-[#e1e6ed] px-6 py-5 bg-[#f8fafc]">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#edf7e5] text-[#2e7d32] border border-[#c8e6c9] mb-2">
            Periode Pendaftaran Aktif
        </span>
        <h1 class="text-xl font-bold text-[#111827]">{{ $period->name }}</h1>
        <p class="text-sm text-[#64748b] mt-1">
            Batas Pendaftaran: {{ $period->starts_at?->format('d/m/Y') }} s/d {{ $period->ends_at?->format('d/m/Y') }}
        </p>
        @if($period->description)
            <p class="text-sm text-[#475569] mt-2 pt-2 border-t border-[#e2e8f0]">
                {{ $period->description }}
            </p>
        @endif
    </div>

    {{-- Informasi & Pengumuman --}}
    @if($contents->isNotEmpty())
        <div class="bg-white rounded-[8px] shadow-sm border border-[#e1e6ed] overflow-hidden">
            <div class="border-b border-[#e1e6ed] px-6 py-4 bg-[#f8fafc]">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[#2872c8] flex items-center gap-2">
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    Informasi &amp; Pengumuman
                </h2>
            </div>
            <div class="divide-y divide-[#eef2f7]">
                @foreach($contents as $content)
                    <div class="px-6 py-4">
                        <h3 class="text-sm font-semibold text-[#111827]">{{ $content->title }}</h3>
                        <p class="mt-1 text-sm text-[#475569] whitespace-pre-line">{{ $content->body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Form Body --}}
    <form action="{{ route('ppdb.register.store') }}" method="POST" class="p-6 space-y-6">
        @csrf

        @if($errors->any())
            <div class="p-4 rounded-[6px] bg-[#fdedec] border border-[#fadbd8] text-[#c0392b] text-sm">
                <p class="font-semibold mb-1">Terdapat kesalahan pada isian formulir:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Section 1: Data Diri Calon Siswa --}}
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-[#2872c8] mb-4 pb-1 border-b border-[#e1e6ed] flex items-center gap-2">
                <i data-lucide="user" class="w-4 h-4"></i>
                Data Pribadi Calon Siswa
            </h2>

            <div class="space-y-4">
                <x-ui.input
                    name="name"
                    label="Nama Lengkap"
                    value="{{ old('name') }}"
                    placeholder="Sesuai Akta Kelahiran / Ijazah"
                    required
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.select
                        name="gender"
                        label="Jenis Kelamin"
                        required
                        placeholder="-- Pilih Jenis Kelamin --"
                        :options="['male' => 'Laki-laki', 'female' => 'Perempuan']"
                        :selected="old('gender')"
                    />

                    <x-ui.input
                        name="phone"
                        label="Nomor WhatsApp / HP Siswa"
                        value="{{ old('phone') }}"
                        placeholder="Contoh: 08123456789"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input
                        name="birth_place"
                        label="Tempat Lahir"
                        value="{{ old('birth_place') }}"
                        placeholder="Kota / Kabupaten"
                    />

                    <x-ui.input
                        name="birth_date"
                        type="date"
                        label="Tanggal Lahir"
                        value="{{ old('birth_date') }}"
                    />
                </div>

                <x-ui.input
                    name="email"
                    type="email"
                    label="Alamat Email (Opsional)"
                    value="{{ old('email') }}"
                    placeholder="siswa@contoh.com"
                />

                <x-ui.textarea
                    name="address"
                    label="Alamat Tempat Tinggal Lengkap"
                    rows="3"
                    required
                    placeholder="Jalan, RT/RW, Dusun, Desa/Kelurahan, Kecamatan, Kabupaten/Kota"
                >{{ old('address') }}</x-ui.textarea>
            </div>
        </div>

        {{-- Section 2: Data Orang Tua / Wali --}}
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-[#2872c8] mb-4 pb-1 border-b border-[#e1e6ed] flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4"></i>
                Data Orang Tua / Wali
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui.input
                    name="parent_name"
                    label="Nama Orang Tua / Wali"
                    value="{{ old('parent_name') }}"
                    placeholder="Nama lengkap ayah/ibu/wali"
                    required
                />

                <x-ui.input
                    name="parent_phone"
                    label="Nomor WhatsApp / HP Orang Tua"
                    value="{{ old('parent_phone') }}"
                    placeholder="Contoh: 08129876543"
                    required
                />
            </div>
        </div>

        <div class="pt-4 border-t border-[#e1e6ed] flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-[#8994a3]">
                Pastikan data yang Anda isi telah benar dan valid sebelum mengirimkan formulir.
            </p>
            <button
                type="submit"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-[6px] text-sm font-medium text-white bg-[#2872c8] hover:bg-[#20599f] transition shadow-sm"
            >
                <i data-lucide="send" class="w-4 h-4"></i>
                Kirim Pendaftaran
            </button>
        </div>
    </form>
</div>
@endsection
