<?php

declare(strict_types=1);

use App\Http\Controllers\AlumniCsvController;
use App\Http\Controllers\ApplicantDocumentController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\GuardianCsvController;
use App\Http\Controllers\BillCsvController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PayrollSlipController;
use App\Http\Controllers\PortalReceiptController;
use App\Http\Controllers\PublicGuardianController;
use App\Http\Controllers\PublicGuardianPasswordController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\PublicStudentController;
use App\Http\Controllers\ReportPrintController;
use App\Http\Controllers\SklPdfController;
use App\Http\Controllers\SppRecapPdfController;
use App\Http\Controllers\StudentCsvController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Livewire\Admissions\AdmissionBillIndex;
use App\Livewire\Admissions\AdmissionIndex;
use App\Livewire\Admissions\ApplicantIndex;
use App\Livewire\Admissions\ApplicantShow;
use App\Livewire\Admissions\ContentIndex;
use App\Livewire\Admissions\PeriodIndex;
use App\Livewire\Admissions\PortalAccess;
use App\Livewire\Admissions\SettingIndex as AdmissionSettingIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Bills\BillGenerate;
use App\Livewire\Reports\CashFlowIndex;
use App\Livewire\Reports\JournalIndex;
use App\Livewire\Reports\ReportPrintIndex;
use App\Livewire\Reports\TransactionIndex;
use App\Livewire\Bills\BillIndex;
use App\Livewire\Bills\BillShow;
use App\Livewire\Bills\DiscountIndex;
use App\Livewire\Cash\CashExpenseIndex;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Master\AcademicYearIndex;
use App\Livewire\Master\AccountIndex;
use App\Livewire\Master\AlumniIndex;
use App\Livewire\Master\BankAccountIndex;
use App\Livewire\Master\GuardianIndex;
use App\Livewire\Master\PaymentCategoryIndex;
use App\Livewire\Master\SchoolClassIndex;
use App\Livewire\Master\ScholarshipIndex;
use App\Livewire\Master\StaffIndex;
use App\Livewire\Master\StudentIndex;
use App\Livewire\Students\Clearances\ClearanceIndex;
use App\Livewire\Students\StudentPromotion;
use App\Livewire\Master\UnitIndex;
use App\Livewire\Finance\BudgetIndex;
use App\Livewire\Finance\BankReconciliationIndex;
use App\Livewire\Payroll\PayrollIndex;
use App\Livewire\Payroll\PayrollShow;
use App\Livewire\Payments\Intents\PaymentIntentIndex;
use App\Livewire\Payments\PaymentCreate;
use App\Livewire\Payments\PaymentIndex;
use App\Livewire\Payments\PaymentShow;
use App\Livewire\Payments\PaymentVerification;
use App\Livewire\Reports\AnalyticsIndex;
use App\Livewire\Reports\LedgerIndex;
use App\Livewire\Reports\ReportsIndex;
use App\Livewire\Savings\SavingsIndex;
use App\Livewire\Savings\SavingsTransaction;
use App\Livewire\Savings\SavingsVerification;
use App\Livewire\Profile\ProfileEdit;
use App\Livewire\Settings\RoleIndex;
use App\Livewire\Settings\SettingIndex;
use App\Livewire\Settings\AppearanceIndex;
use App\Livewire\Settings\GatewayIndex;
use App\Livewire\Settings\NotificationTemplateIndex;
use App\Livewire\Settings\SystemIndex;
use App\Livewire\Settings\UserIndex;
use App\Livewire\Tools\ActivityLogIndex;
use App\Livewire\Tools\BackupIndex;
use App\Livewire\Tools\NotificationIndex;
use App\Livewire\Tools\ServerInfoIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/dashboard');
Route::redirect('/beranda', '/admin/dashboard');
Route::redirect('/PPDB', '/ppdb');

Route::redirect('/masuk', '/admin/masuk');

Route::prefix('admin')->middleware('guest')->group(function (): void {
    Route::get('/masuk', Login::class)->name('login');
});

Route::prefix('ppdb')->name('ppdb.')->group(function (): void {
    Route::get('/', [PublicRegistrationController::class, 'portal'])->name('portal');
    Route::get('/daftar', [PublicRegistrationController::class, 'create'])->name('register');
    Route::post('/daftar', [PublicRegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');
    Route::get('/daftar/berhasil/{applicant}', [PublicRegistrationController::class, 'success'])
        ->middleware('signed')
        ->name('register.success');
    Route::get('/ditutup', [PublicRegistrationController::class, 'closed'])->name('register.closed');
});

Route::prefix('portal')->name('public.student.')->group(function (): void {
    Route::get('/login', [PublicStudentController::class, 'login'])->name('login');
    Route::post('/authenticate', [PublicStudentController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('authenticate');
    Route::post('/siswa/keluar', [PublicStudentController::class, 'logout'])->name('logout');
    Route::get('/siswa', \App\Livewire\Public\StudentPortal::class)
        ->middleware('auth.public.student')
        ->name('dashboard');
});

Route::prefix('portal/orang-tua')->name('public.guardian.')->group(function (): void {
    Route::get('/login', [PublicGuardianController::class, 'login'])->name('login');
    Route::post('/authenticate', [PublicGuardianController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('authenticate');
    Route::post('/keluar', [PublicGuardianController::class, 'logout'])->name('logout');
    Route::get('/', \App\Livewire\Public\GuardianPortal::class)
        ->middleware('auth.public.guardian')
        ->name('dashboard');

    Route::get('/lupa-password', [PublicGuardianPasswordController::class, 'showForgot'])->name('forgot');
    Route::post('/lupa-password', [PublicGuardianPasswordController::class, 'sendOtp'])
        ->middleware('throttle:3,1')
        ->name('forgot.send');
    Route::get('/lupa-password/verifikasi', [PublicGuardianPasswordController::class, 'showReset'])
        ->name('forgot.verify');
    Route::post('/lupa-password/verifikasi', [PublicGuardianPasswordController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('forgot.reset');
});

Route::get('/portal/kwitansi/{payment}', PortalReceiptController::class)
    ->middleware('throttle:30,1')
    ->name('public.receipt');

Route::get('/portal/rekap-spp/{student}', SppRecapPdfController::class)
    ->middleware('throttle:30,1')
    ->name('public.spp-recap');

/*
|--------------------------------------------------------------------------
| Webhook Payment Gateway
|--------------------------------------------------------------------------
|
| Dipanggil penyedia pembayaran, sehingga tidak memakai sesi maupun CSRF.
| Keamanan bertumpu pada verifikasi tanda tangan di dalam controller.
| Throttle mencegah penyalahgunaan endpoint publik.
|
*/
Route::post('/webhook/pembayaran/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.payment');

Route::prefix('admin')->middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardIndex::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/profil', ProfileEdit::class)->name('profile.edit');
    Route::post('/keluar', LogoutController::class)->name('logout');

    Route::get('/tabungan', SavingsIndex::class)->middleware(['permission:savings.view', 'feature.student-savings'])->name('savings.index');
    Route::get('/tabungan/transaksi', SavingsTransaction::class)->middleware(['permission:savings.create', 'feature.student-savings'])->name('savings.transaction');
    Route::get('/tabungan/verifikasi', SavingsVerification::class)->middleware(['permission:savings.verify', 'feature.student-savings'])->name('savings.verification');

    Route::get('/pembayaran', PaymentIndex::class)->middleware('permission:payments.view')->name('payments.index');
    Route::get('/pembayaran/catat', PaymentCreate::class)->middleware('permission:payments.create')->name('payments.create');
    Route::get('/pembayaran/verifikasi', PaymentVerification::class)->middleware('permission:payments.verify')->name('payments.verification');
    Route::get('/pembayaran/tagihan-digital', PaymentIntentIndex::class)->middleware('permission:payment-gateways.view')->name('payment-intents.index');
    Route::get('/pembayaran/{payment}', PaymentShow::class)->middleware('permission:payments.view')->name('payments.show');
    Route::get('/pembayaran/{payment}/kwitansi', PaymentReceiptController::class)->middleware('permission:payments.print')->name('payments.receipt');
    Route::get('/bukti-pembayaran/{proof}', PaymentProofController::class)->middleware('permission:payments.view')->name('payment-proofs.download');
    Route::get('/laporan', ReportsIndex::class)->middleware('permission:reports.view')->name('reports.index');
    Route::get('/laporan/transaksi', TransactionIndex::class)->middleware('permission:reports.view')->name('reports.transactions');
    Route::get('/laporan/arus-kas', CashFlowIndex::class)->middleware('permission:reports.view')->name('reports.cash-flow');
    Route::get('/laporan/jurnal', JournalIndex::class)->middleware('permission:reports.view')->name('reports.journals');
    Route::get('/laporan/buku-besar', LedgerIndex::class)->middleware('permission:reports.view')->name('reports.ledger');
    Route::get('/laporan/analitik', AnalyticsIndex::class)->middleware('permission:reports.view')->name('reports.analytics');
    Route::get('/laporan/cetak', ReportPrintIndex::class)->middleware('permission:reports.export')->name('reports.print');
    Route::get('/keuangan/kas-keluar', CashExpenseIndex::class)->middleware('permission:cash-transactions.view')->name('cash.expenses');
    Route::get('/keuangan/rekonsiliasi-bank', BankReconciliationIndex::class)->middleware('permission:bank-reconciliations.view')->name('bank-reconciliations.index');
    Route::get('/keuangan/honorarium', PayrollIndex::class)->middleware('permission:payrolls.view')->name('payrolls.index');
    Route::get('/keuangan/anggaran', BudgetIndex::class)->middleware('permission:budgets.view')->name('budgets.index');
    Route::get('/keuangan/honorarium/{payrollPeriod}', PayrollShow::class)->middleware('permission:payrolls.view')->name('payrolls.show');
    Route::get('/keuangan/honorarium/{payrollPeriod}/slip', PayrollSlipController::class)
        ->middleware('permission:payrolls.view')
        ->name('payrolls.slips');
    Route::get('/laporan/cetak/pdf', ReportPrintController::class)->middleware('permission:reports.export')->name('reports.print.download');
    Route::get('/laporan/export', function (Request $request, \App\Services\FinancialReportService $reports) {
        $filters = $request->validate([
            'type' => ['required', 'in:approved_payments,outstanding_bills'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);
        $type = $filters['type'];
        $rows = $type === 'approved_payments'
            ? $reports->approvedPayments($filters['from'] ?? null, $filters['to'] ?? null, $filters['academic_year_id'] ?? null)
            : $reports->outstandingBills($filters['academic_year_id'] ?? null);
        $headers = $type === 'approved_payments'
            ? ['Nomor Transaksi', 'Tanggal', 'Siswa', 'Nominal', 'Status']
            : ['Nomor Tagihan', 'Siswa', 'Tagihan', 'Jatuh Tempo', 'Sisa', 'Status'];

        return response()->streamDownload(function () use ($rows, $type, $headers): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($output, \App\Support\Csv::safeAll($type === 'approved_payments'
                    ? [$row->transaction_number, $row->paid_at?->toDateTimeString(), $row->student?->name, $row->amount, $row->status->value]
                    : [$row->bill_number, $row->student?->name, $row->bill?->title, $row->due_date?->toDateString(), $row->remaining_amount, $row->status->value]), escape: '');
            }
            fclose($output);
        }, $type . '-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('permission:reports.export')->name('reports.export');

    Route::get('/ppdb/kelola', AdmissionIndex::class)->middleware('permission:admissions.view')->name('admissions.index');
    Route::get('/ppdb/pendaftar', ApplicantIndex::class)->middleware('permission:admissions.view')->name('admissions.applicants');
    Route::get('/ppdb/tagihan', AdmissionBillIndex::class)->middleware('permission:admissions.view')->name('admissions.bills');
    Route::get('/ppdb/periode', PeriodIndex::class)->middleware('permission:admissions.view')->name('admissions.periods');
    Route::get('/ppdb/konten/pengumuman', ContentIndex::class)->middleware('permission:admissions.view')->name('admissions.contents');
    Route::get('/ppdb/pengaturan/{section}', AdmissionSettingIndex::class)->middleware('permission:admissions.view')->name('admissions.settings');
    Route::get('/ppdb/portal', PortalAccess::class)->middleware('permission:admissions.view')->name('admissions.portal');
    Route::get('/ppdb/dokumen/{document}', ApplicantDocumentController::class)->middleware('permission:admissions.view')->name('applicant-documents.download');
    Route::get('/ppdb/{applicant}', ApplicantShow::class)->middleware('permission:admissions.view')->name('admissions.show');
    Route::get('/peralatan/log-aktivitas', ActivityLogIndex::class)
        ->middleware('permission:activity-logs.view')
        ->name('activity-logs.index');

    Route::get('/peralatan/backup', BackupIndex::class)
        ->middleware('permission:backup.create|backup.delete')
        ->name('backups.index');

    Route::get('/peralatan/notifikasi', NotificationIndex::class)
        ->middleware('permission:activity-logs.view')
        ->name('notifications.index');

    Route::get('/peralatan/server', ServerInfoIndex::class)
        ->middleware('permission:settings.view')
        ->name('server-info.index');

    Route::get('/tagihan', BillIndex::class)->middleware('permission:bills.view')->name('bills.index');
    Route::get('/tagihan/export', BillCsvController::class)->middleware('permission:bills.view')->name('bills.export');
    Route::get('/tagihan/generate', BillGenerate::class)->middleware('permission:bills.generate')->name('bills.generate');
    Route::get('/tagihan/potongan', DiscountIndex::class)->middleware('permission:discounts.view')->name('discounts.index');
    Route::get('/tagihan/beasiswa', ScholarshipIndex::class)->middleware('permission:scholarships.view')->name('scholarships.index');
    Route::get('/tagihan/{studentBill}', BillShow::class)->middleware('permission:bills.view')->name('bills.show');
    Route::get('/tagihan/siswa/{student}/rekap-spp', SppRecapPdfController::class)
        ->middleware('permission:bills.view')
        ->name('bills.spp-recap');

    // Pengaturan
    Route::get('/pengaturan', SettingIndex::class)
        ->middleware('permission:settings.view')
        ->name('settings.index');

    Route::get('/pengaturan/sistem', SystemIndex::class)
        ->middleware('permission:settings.view')
        ->name('settings.system');

    Route::get('/pengaturan/notifikasi', NotificationTemplateIndex::class)
        ->middleware('permission:settings.view')
        ->name('settings.notifications');

    Route::get('/pengaturan/pembayaran', GatewayIndex::class)
        ->middleware('permission:settings.view')
        ->name('settings.gateway');

    Route::get('/pengaturan/tampilan', AppearanceIndex::class)
        ->middleware('permission:settings.view')
        ->name('settings.appearance');

    Route::get('/pengaturan/pengguna', UserIndex::class)
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::get('/pengaturan/role', RoleIndex::class)
        ->middleware('permission:roles.view')
        ->name('roles.index');

    // Data Master
    Route::get('/data-master/unit', UnitIndex::class)->middleware('permission:units.view')->name('units.index');
    Route::get('/data-master/tahun-ajaran', AcademicYearIndex::class)->middleware('permission:academic-years.view')->name('academic-years.index');
    Route::get('/data-master/kelas', SchoolClassIndex::class)->middleware('permission:school-classes.view')->name('school-classes.index');
    Route::get('/data-master/petugas', StaffIndex::class)->middleware('permission:staff.view')->name('staff.index');
    Route::get('/data-master/siswa', StudentIndex::class)->middleware('permission:students.view')->name('students.index');
    Route::get('/data-master/siswa/export', [StudentCsvController::class, 'export'])
        ->middleware('permission:students.export')
        ->name('students.export');
    Route::post('/data-master/siswa/import', [StudentCsvController::class, 'import'])
        ->middleware('permission:students.import')
        ->name('students.import');
    Route::get('/data-master/siswa/kenaikan-kelas', StudentPromotion::class)
        ->middleware('permission:students.promote')
        ->name('students.promotion');
    Route::get('/data-master/siswa/bebas-tanggungan', ClearanceIndex::class)
        ->middleware('permission:clearances.view')
        ->name('clearances.index');
    Route::get('/data-master/siswa/{student}/skl', SklPdfController::class)
        ->middleware('permission:clearances.issue')
        ->name('clearances.skl');
    Route::get('/data-master/alumni', AlumniIndex::class)->middleware('permission:alumni.view')->name('alumni.index');
    Route::get('/data-master/alumni/export', AlumniCsvController::class)
        ->middleware('permission:alumni.export')
        ->name('alumni.export');
    Route::get('/data-master/orang-tua', GuardianIndex::class)->middleware('permission:guardians.view')->name('guardians.index');
    Route::get('/data-master/orang-tua/export', GuardianCsvController::class)
        ->middleware('permission:guardians.export')
        ->name('guardians.export');
    Route::get('/data-master/kategori-pembayaran', PaymentCategoryIndex::class)->middleware('permission:payment-categories.view')->name('payment-categories.index');
    Route::get('/data-master/akun', AccountIndex::class)->middleware('permission:accounts.view')->name('accounts.index');
    Route::get('/data-master/rekening', BankAccountIndex::class)->middleware('permission:bank-accounts.view')->name('bank-accounts.index');
});
