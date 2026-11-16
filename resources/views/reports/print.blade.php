<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; margin: 0; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 14px; letter-spacing: .5px; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #475569; }
        .title { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin: 0 0 4px; text-transform: uppercase; }
        .meta { text-align: center; font-size: 9px; color: #475569; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9px; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        .right { text-align: right; }
        .sign { margin-top: 24px; float: right; width: 220px; text-align: center; font-size: 9px; }
        .sign .line { margin-top: 44px; border-top: 1px solid #111827; padding-top: 3px; font-weight: bold; }
        .muted { color: #64748b; font-size: 8px; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $appName }}</h1>
        <p>{{ $appAddress }}</p>
    </div>

    <div class="title">{{ $title }}</div>
    <div class="meta">
        Periode: {{ $period }}
        @if($academicYear) · Tahun Ajaran: {{ $academicYear }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 24px">#</th>
                @foreach($columns as $col)
                    <th @if(in_array($col, ['Nominal', 'Sisa'], true)) class="right" @endif>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @foreach($row as $cellIdx => $cell)
                        <td @if($cellIdx === count($row) - 1 && $total !== null) class="right" @endif>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 16px; color: #64748b;">
                        Tidak ada data untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($total !== null)
            <tfoot>
                <tr>
                    <td colspan="{{ count($columns) }}" class="right"><strong>Total</strong></td>
                    <td class="right"><strong>Rp {{ number_format($total, 0, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="clearfix">
        <div class="sign">
            Jombang, {{ now()->timezone('Asia/Jakarta')->format('d/m/Y') }}<br>
            Kepala Madrasah
            <div class="line">{{ $headmasterName }}</div>
        </div>
    </div>

    <p class="muted" style="margin-top: 16px">
        Dicetak oleh: {{ $printedBy ?? 'Sistem' }} pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
    </p>
</body>
</html>
