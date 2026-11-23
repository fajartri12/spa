<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $student->name }}</title>
    <style>
        @page { margin: 24px 28px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; margin: 0; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 13px; font-weight: bold; }
        .header p { margin: 2px 0 0; font-size: 8px; color: #475569; }
        .title { text-align: center; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 10px; text-transform: uppercase; }
        
        .student-info { width: 100%; margin-bottom: 12px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; background: #f8fafc; }
        .student-info table { width: 100%; border: none; }
        .student-info td { border: none; padding: 2px 4px; font-size: 9px; }
        .student-info td.label { font-weight: bold; color: #475569; width: 15%; }
        
        table.matrix { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.matrix th, table.matrix td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 8.5px; text-align: left; }
        table.matrix th { background: #f1f5f9; font-weight: bold; color: #0f172a; text-transform: uppercase; font-size: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; text-align: center; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-unpaid { background: #fef3c7; color: #b45309; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; }
        .badge-partial { background: #e0f2fe; color: #0369a1; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .badge-none { color: #94a3b8; }
        
        .summary-box { margin-top: 10px; width: 100%; }
        .summary-box table { width: 60%; float: right; border-collapse: collapse; }
        .summary-box td { padding: 3px 6px; font-size: 9px; }
        .summary-box td.total-label { font-weight: bold; text-align: right; }
        
        .sign { margin-top: 24px; float: right; width: 200px; text-align: center; font-size: 8.5px; }
        .sign .line { margin-top: 40px; border-top: 1px solid #0f172a; padding-top: 2px; font-weight: bold; }
        .footer { clear: both; margin-top: 20px; font-size: 7.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $appName }}</h1>
        <p>{{ $appAddress }}</p>
    </div>

    <div class="title">{{ $title }}</div>

    <div class="student-info">
        <table>
            <tr>
                <td class="label">Nama Siswa</td>
                <td>: <strong>{{ $student->name }}</strong></td>
                <td class="label">Tahun Ajaran</td>
                <td>: {{ $academicYear }}</td>
            </tr>
            <tr>
                <td class="label">NIS / NISN</td>
                <td>: {{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</td>
                <td class="label">Kelas / Unit</td>
                <td>: {{ $student->schoolClass?->name ?? '-' }} ({{ $student->unit?->name ?? '-' }})</td>
            </tr>
        </table>
    </div>

    <table class="matrix">
        <thead>
            <tr>
                <th class="center" style="width: 24px">No</th>
                <th>Bulan / Periode</th>
                <th class="center">No. Tagihan</th>
                <th class="center">Jatuh Tempo</th>
                <th class="right">Nominal Tagihan</th>
                <th class="right">Telah Dibayar</th>
                <th class="right">Sisa Tagihan</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($months as $m)
                <tr>
                    <td class="center">{{ $m['month_number'] }}</td>
                    <td><strong>{{ $m['month_name'] }}</strong></td>
                    <td class="center">{{ $m['bill']?->bill_number ?? '-' }}</td>
                    <td class="center">{{ $m['due_date']?->format('d/m/Y') ?? '-' }}</td>
                    <td class="right">Rp {{ number_format($m['amount'], 0, ',', '.') }}</td>
                    <td class="right" style="color: #15803d">Rp {{ number_format($m['paid_amount'], 0, ',', '.') }}</td>
                    <td class="right" style="color: #b91c1c">Rp {{ number_format($m['remaining_amount'], 0, ',', '.') }}</td>
                    <td class="center">
                        @switch($m['status'])
                            @case('paid')
                                <span class="badge badge-paid">LUNAS</span>
                                @break
                            @case('partial')
                                <span class="badge badge-partial">SEBAGIAN</span>
                                @break
                            @case('overdue')
                                <span class="badge badge-overdue">JATUH TEMPO</span>
                                @break
                            @case('unpaid')
                                <span class="badge badge-unpaid">BELUM BAYAR</span>
                                @break
                            @case('cancelled')
                                <span class="badge badge-cancelled">BATAL</span>
                                @break
                            @default
                                <span class="badge badge-none">-</span>
                        @endswitch
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: bold;">
                <td colspan="4" class="right">TOTAL ({{ $summary['bill_count'] }} Tagihan)</td>
                <td class="right">Rp {{ number_format($summary['total_target'], 0, ',', '.') }}</td>
                <td class="right" style="color: #15803d">Rp {{ number_format($summary['total_paid'], 0, ',', '.') }}</td>
                <td class="right" style="color: #b91c1c">Rp {{ number_format($summary['total_remaining'], 0, ',', '.') }}</td>
                <td class="center">{{ $summary['paid_count'] }} Lunas / {{ $summary['unpaid_count'] }} Belum</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 15px; clear: both;">
        <div class="sign">
            Jombang, {{ now()->timezone('Asia/Jakarta')->format('d M Y') }}<br>
            Bendahara / Kepala Madrasah
            <div class="line">{{ $headmasterName }}</div>
        </div>
    </div>

    <div class="footer">
        Dokumen ini dicetak otomatis oleh {{ $printedBy }} pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB.
    </div>
</body>
</html>
