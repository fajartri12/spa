<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Honorarium {{ $period->period }}</title>
    <style>
        @page { margin: 20px 24px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; margin: 0; }
        .slip { border: 1px solid #111827; padding: 12px 14px; margin-bottom: 14px; page-break-inside: avoid; }
        .head { border-bottom: 1px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
        .head h1 { margin: 0; font-size: 12px; letter-spacing: .4px; }
        .head p { margin: 2px 0 0; font-size: 9px; color: #475569; }
        .title { text-align: center; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .label { width: 30%; color: #475569; }
        .sep { width: 3%; color: #475569; }
        .amount { margin-top: 8px; border: 1px solid #111827; padding: 6px 8px; font-size: 11px; font-weight: bold; }
        .muted { color: #475569; font-size: 9px; }
        .sign { width: 100%; margin-top: 18px; }
        .sign td { width: 50%; text-align: center; font-size: 9px; }
        .sign .line { margin-top: 34px; border-top: 1px solid #111827; padding-top: 3px; }
        .right { text-align: right; }
        .summary { border: 1px solid #111827; padding: 10px 12px; }
    </style>
</head>
<body>
    @foreach($items as $item)
        <div class="slip">
            <div class="head">
                <h1>{{ $appName }}</h1>
                <p>{{ $appAddress }}</p>
            </div>

            <p class="title">SLIP HONORARIUM</p>

            <table>
                <tr><td class="label">Periode</td><td class="sep">:</td><td>{{ $period->period }} &mdash; {{ $period->title }}</td></tr>
                <tr><td class="label">Unit</td><td class="sep">:</td><td>{{ $period->unit?->name ?? 'Semua Unit' }}</td></tr>
                <tr><td class="label">Tahun Ajaran</td><td class="sep">:</td><td>{{ $period->academicYear?->name ?? '-' }}</td></tr>
                <tr><td class="label">Nama Penerima</td><td class="sep">:</td><td>{{ $item->recipient_name }}</td></tr>
                <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $item->recipient_position ?: '-' }}</td></tr>
                <tr><td class="label">Rekening</td><td class="sep">:</td><td>{{ $item->recipient_bank ?: '-' }} {{ $item->recipient_account }}</td></tr>
            </table>

            <table style="margin-top:8px">
                <tr><td class="label">Honorarium Bruto</td><td class="sep">:</td><td class="right">{{ \App\Support\Formatter::rupiah((float) $item->gross_amount) }}</td></tr>
                <tr><td class="label">Potongan</td><td class="sep">:</td><td class="right">{{ \App\Support\Formatter::rupiah((float) $item->deduction_amount) }}</td></tr>
            </table>

            <div class="amount">
                Diterima: {{ \App\Support\Formatter::rupiah((float) $item->net_amount) }}
            </div>

            @if($item->notes)
                <p class="muted" style="margin-top:6px">Catatan: {{ $item->notes }}</p>
            @endif

            <table class="sign">
                <tr>
                    <td>
                        Penerima
                        <div class="line">{{ $item->recipient_name }}</div>
                    </td>
                    <td>
                        Jombang, {{ ($period->approved_at ?? now())->timezone('Asia/Jakarta')->format('d/m/Y') }}<br>
                        Bendahara
                        <div class="line">{{ $treasurerName }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="summary">
        <table>
            <tr>
                <td class="label">Jumlah Penerima</td>
                <td class="sep">:</td>
                <td>{{ $items->count() }} orang</td>
            </tr>
            <tr>
                <td class="label">Total Dibayarkan</td>
                <td class="sep">:</td>
                <td><strong>{{ \App\Support\Formatter::rupiah($totalNet) }}</strong></td>
            </tr>
            <tr>
                <td class="label">Terbilang</td>
                <td class="sep">:</td>
                <td>{{ $totalInWords }}</td>
            </tr>
            <tr>
                <td class="label">Status Periode</td>
                <td class="sep">:</td>
                <td>{{ $period->status->label() }}</td>
            </tr>
        </table>

        <table class="sign">
            <tr>
                <td>
                    Mengetahui,<br>
                    Kepala Madrasah
                    <div class="line">{{ $headmasterName }}</div>
                </td>
                <td>
                    Bendahara
                    <div class="line">{{ $treasurerName }}</div>
                </td>
            </tr>
        </table>
    </div>

    <p class="muted" style="margin-top:12px">Dicetak pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
</body>
</html>
