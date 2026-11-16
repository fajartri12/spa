<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Payment;
use App\Support\Formatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        abort_unless($request->user()?->can('payments.print'), 403);

        $payment->load(['student', 'paymentCategory', 'allocations.studentBill.bill.paymentCategory', 'details.paymentMethod']);

        $pdf = Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'statusLabel' => match ($payment->status) {
                PaymentStatus::Approved => 'DISETUJUI',
                PaymentStatus::Rejected => 'DITOLAK',
                PaymentStatus::Cancelled => 'DIBATALKAN',
                default => 'MENUNGGU VERIFIKASI',
            },
            'amountInWords' => Formatter::terbilang($payment->amount),
            'treasurerTitle' => 'Bendahara',
            'treasurerName' => AppSetting::get('treasurer_name', '..........................'),
        ])->setPaper('a5');

        return $pdf->download("kwitansi-{$payment->transaction_number}.pdf");
    }
}
