<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\PayrollPeriod;
use App\Support\Formatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak slip honorarium satu periode sebagai PDF.
 *
 * Satu halaman per penerima agar dapat dipotong dan dibagikan. Slip hanya
 * memuat data yang sudah tersimpan; periode draf tetap boleh dicetak sebagai
 * pratinjau sebelum disetujui.
 */
class PayrollSlipController extends Controller
{
    public function __invoke(Request $request, PayrollPeriod $payrollPeriod): Response
    {
        abort_unless($request->user()?->can('payrolls.view'), 403);

        $payrollPeriod->load([
            'items',
            'unit',
            'academicYear',
            'cashAccount',
            'expenseAccount',
            'approver',
        ]);

        abort_if($payrollPeriod->items->isEmpty(), 404, 'Periode ini belum memiliki slip.');

        $pdf = Pdf::loadView('payroll.slips', [
            'period' => $payrollPeriod,
            'items' => $payrollPeriod->items,
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'treasurerName' => AppSetting::get('treasurer_name', '..........................'),
            'headmasterName' => AppSetting::get('headmaster_name', '..........................'),
            'totalNet' => (float) $payrollPeriod->items->sum('net_amount'),
            'totalInWords' => Formatter::terbilang((float) $payrollPeriod->items->sum('net_amount')),
        ])->setPaper('a4');

        return $pdf->download('slip-honorarium-'.$payrollPeriod->period.'.pdf');
    }
}
