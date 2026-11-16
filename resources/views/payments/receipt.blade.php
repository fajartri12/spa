<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kwitansi {{ $payment->transaction_number }}</title>
    <style>
        @page { margin: 24px 28px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 0; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 15px; letter-spacing: .5px; }
        .header p { margin: 2px 0 0; font-size: 10px; color: #475569; }
        .title { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 2px; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 0; vertical-align: top; }
        .label { width: 34%; color: #475569; }
        .sep { color: #475569; width: 3%; }
        .amount { margin-top: 12px; border: 1px solid #111827; padding: 8px 10px; font-size: 13px; font-weight: bold; }
        .grid { width: 100%; margin-top: 14px; }
        .grid td { width: 50%; }
        .muted { color: #475569; font-size: 10px; }
        .sign { margin-top: 40px; text-align: right; }
        .sign .line { margin-top: 46px; border-top: 1px solid #111827; display: inline-block; min-width: 170px; padding-top: 4px; }
        table.items { margin-top: 16px; }
        table.items th, table.items td { border-bottom: 1px solid #cbd5e1; padding: 5px 6px; font-size: 10px; text-align: left; }
        table.items th { background: #f1f5f9; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $appName }}</h1>
        <p>{{ $appAddress }}</p>
    </div>

    <p class="title">KWITANSI PEMBAYARAN</p>

    <table>
        <tr><td class="label">Nomor Transaksi</td><td class="sep">:</td><td>{{ $payment->transaction_number }}</td></tr>
        <tr><td class="label">Tanggal</td><td class="sep">:</td><td>{{ $payment->paid_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</td></tr>
        <tr><td class="label">Nama Siswa</td><td class="sep">:</td><td>{{ $payment->student?->name }}</td></tr>
        <tr><td class="label">NIS</td><td class="sep">:</td><td>{{ $payment->student?->nis ?? '-' }}</td></tr>
        <tr><td class="label">Kategori</td><td class="sep">:</td><td>{{ $payment->paymentCategory?->name ?? '-' }}</td></tr>
        <tr><td class="label">Metode Pembayaran</td><td class="sep">:</td><td>{{ $payment->details->first()?->paymentMethod?->name ?? '-' }}</td></tr>
        <tr><td class="label">Status</td><td class="sep">:</td><td>{{ $statusLabel }}</td></tr>
    </table>

    <div class="amount">
        Jumlah: {{ $amountInWords }}
    </div>

    <table class="items">
        <thead>
            <tr><th>No. Tagihan</th><th>Kategori / Periode</th><th class="right">Alokasi</th></tr>
        </thead>
        <tbody>
            @foreach($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->studentBill?->bill_number }}</td>
                    <td>{{ $allocation->studentBill?->bill?->paymentCategory?->name }} / {{ $allocation->studentBill?->bill?->period ?? '-' }}</td>
                    <td class="right">{{ \App\Support\Formatter::rupiah((float) $allocation->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="2" class="right"><strong>Total</strong></td><td class="right"><strong>{{ \App\Support\Formatter::rupiah((float) $payment->amount) }}</strong></td></tr>
        </tfoot>
    </table>

    @if($payment->notes)
        <p class="muted" style="margin-top:12px">Catatan: {{ $payment->notes }}</p>
    @endif

    <div class="sign">
        Jombang, {{ now()->timezone('Asia/Jakarta')->format('d/m/Y') }}<br>
        {{ $treasurerTitle }}
        <div class="line">{{ $treasurerName }}</div>
    </div>

    <p class="muted" style="margin-top:24px">Dicetak pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $payment->transaction_number }}</p>
</body>
</html>
