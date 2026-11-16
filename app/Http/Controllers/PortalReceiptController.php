<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Formatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unduh kwitansi dari portal siswa maupun portal orang tua.
 *
 * Berbeda dari `PaymentReceiptController` yang khusus petugas, endpoint ini
 * tidak memakai permission karena pemakainya bukan pengguna aplikasi internal.
 * Otorisasi dilakukan dengan memastikan pembayaran memang milik siswa yang
 * sedang masuk, atau milik anak dari wali yang sedang masuk.
 */
class PortalReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        $this->authorizeAccess($request, $payment);

        abort_unless(
            $payment->status === PaymentStatus::Approved,
            403,
            'Kwitansi hanya tersedia untuk pembayaran yang sudah disetujui.',
        );

        $payment->load([
            'student.unit',
            'paymentCategory',
            'allocations.studentBill.bill.paymentCategory',
            'details.paymentMethod',
        ]);

        $pdf = Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'statusLabel' => 'DISETUJUI',
            'amountInWords' => Formatter::terbilang($payment->amount),
            'treasurerTitle' => 'Bendahara',
            'treasurerName' => AppSetting::get('treasurer_name', '..........................'),
        ])->setPaper('a5');

        return $pdf->download("kwitansi-{$payment->transaction_number}.pdf");
    }

    private function authorizeAccess(Request $request, Payment $payment): void
    {
        $studentId = session(PublicStudentController::SESSION_KEY);
        $guardianId = session(PublicGuardianController::SESSION_KEY);

        if ($studentId !== null) {
            $owns = Student::query()
                ->whereKey($studentId)
                ->whereKey($payment->student_id)
                ->exists();

            abort_unless($owns, 403);

            return;
        }

        if ($guardianId !== null) {
            $isGuardian = Guardian::query()
                ->active()
                ->whereKey($guardianId)
                ->whereHas('students', fn ($query) => $query->whereKey($payment->student_id))
                ->exists();

            abort_unless($isGuardian, 403);

            return;
        }

        abort(403, 'Sesi portal tidak ditemukan.');
    }
}
