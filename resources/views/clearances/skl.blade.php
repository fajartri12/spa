<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Keterangan Lulus — {{ $student->name }}</title>
    <style>
        @page { margin: 40px 48px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 0; line-height: 1.55; }
        .header { text-align: center; border-bottom: 3px double #111827; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 16px; letter-spacing: .5px; text-transform: uppercase; }
        .header p { margin: 3px 0 0; font-size: 11px; color: #475569; }
        .title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1.5px; text-decoration: underline; margin: 22px 0 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #475569; margin: 0 0 20px; }
        table.identity { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.identity td { padding: 3px 0; vertical-align: top; }
        table.identity .label { width: 26%; color: #475569; }
        table.identity .sep { width: 2%; color: #475569; }
        .paragraph { text-align: justify; margin: 14px 0; }
        .clearance { margin: 16px 0; border: 1px solid #111827; padding: 10px 12px; }
        .clearance table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .clearance td { padding: 2px 0; }
        .clearance .label { width: 55%; color: #475569; }
        .note { font-size: 10px; color: #475569; margin-top: 10px; }
        .sign { margin-top: 36px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; font-size: 11px; }
        .sign .line { margin-top: 52px; border-top: 1px solid #111827; display: inline-block; min-width: 180px; padding-top: 4px; }
        .stamp { font-size: 10px; color: #475569; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $appName }}</h1>
        @if($appAddress !== '')
            <p>{{ $appAddress }}</p>
        @endif
    </div>

    <p class="title">SURAT KETERANGAN LULUS</p>
    <p class="subtitle">Nomor: {{ $clearance->skl_number }}</p>

    <p class="paragraph">
        Yang bertanda tangan di bawah ini, {{ $headmasterTitle }} {{ $appName }},
        dengan ini menerangkan bahwa:
    </p>

    <table class="identity">
        <tr><td class="label">Nama Lengkap</td><td class="sep">:</td><td><strong>{{ $student->name }}</strong></td></tr>
        <tr><td class="label">NIS</td><td class="sep">:</td><td>{{ $student->nis }}</td></tr>
        <tr><td class="label">NISN</td><td class="sep">:</td><td>{{ $student->nisn ?? '-' }}</td></tr>
        <tr><td class="label">Tempat / Tanggal Lahir</td><td class="sep">:</td><td>{{ $student->birth_place ?? '-' }}, {{ $birthDate?->timezone('Asia/Jakarta')->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><td class="label">Unit Pendidikan</td><td class="sep">:</td><td>{{ $student->unit?->name ?? '-' }}</td></tr>
        <tr><td class="label">Kelas Terakhir</td><td class="sep">:</td><td>{{ $student->schoolClass?->name ?? '-' }}</td></tr>
        <tr><td class="label">Tahun Ajaran</td><td class="sep">:</td><td>{{ $student->schoolClass?->academicYear?->name ?? $clearance->academicYear?->name ?? '-' }}</td></tr>
        <tr><td class="label">Nama Orang Tua / Wali</td><td class="sep">:</td><td>{{ $parentName ?? '-' }}</td></tr>
    </table>

    <p class="paragraph">
        Berdasarkan hasil penilaian akhir dan pemeriksaan administrasi, siswa tersebut
        dinyatakan <strong>LULUS</strong> dari {{ $appName }} dan berhak menerima
        Surat Keterangan Lulus ini.
    </p>

    <div class="clearance">
        <table>
            <tr>
                <td class="label">Status Bebas Tanggungan</td>
                <td class="right"><strong>{{ $clearance->status->label() }}</strong></td>
            </tr>
            <tr>
                <td class="label">Tanggal Pemeriksaan</td>
                <td class="right">{{ $clearance->checked_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} WIB</td>
            </tr>
            @if($hasOutstanding)
                <tr>
                    <td class="label">Pengecualian diberikan oleh</td>
                    <td class="right">{{ $clearance->overrider?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Alasan pengecualian</td>
                    <td class="right">{{ $clearance->override_reason ?? '-' }}</td>
                </tr>
            @endif
        </table>
    </div>

    <p class="paragraph">
        Surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
    </p>

    <table class="sign">
        <tr>
            <td>
                <span class="stamp">Mengetahui,<br>Wali Kelas</span>
                <div class="line">&nbsp;</div>
            </td>
            <td class="right">
                Jombang, {{ $issuedDate->format('d/m/Y') }}<br>
                {{ $headmasterTitle }}
                <div class="line">{{ $headmasterName }}</div>
            </td>
        </tr>
    </table>

    <p class="note">
        Dicetak pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB &middot;
        Nomor SKL: {{ $clearance->skl_number }} &middot;
        Sisa tanggungan saat pemeriksaan: {{ \App\Support\Formatter::rupiah((float) $clearance->remaining_amount) }}
    </p>
</body>
</html>
