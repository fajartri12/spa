<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CashTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\StaffPosition;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use App\Models\PayrollPeriod;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentSavingsTransaction;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CashExpenseService;
use App\Services\JournalService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use App\Services\SavingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data transaksi contoh untuk pengembangan lokal & demo.
 *
 * Sengaja memakai service aplikasi (billing, pembayaran, kas, tabungan,
 * honorarium) agar saldo tagihan, arus kas, dan jurnal double-entry konsisten
 * seperti transaksi sungguhan (AGENTS.MD bagian 6). Karena itu nilainya
 * idempoten: menjalankan ulang seeder tidak menggandakan transaksi.
 */
class TransactionSeeder extends Seeder
{
    private BillingService $billing;

    private PaymentService $payments;

    private CashExpenseService $expenses;

    private SavingsService $savings;

    private PayrollService $payroll;

    private JournalService $journals;

    private User $treasurer;

    private Unit $unit;

    private AcademicYear $year;

    protected function bootstrapServices(): void
    {
        $this->billing = app(BillingService::class);
        $this->payments = app(PaymentService::class);
        $this->expenses = app(CashExpenseService::class);
        $this->savings = app(SavingsService::class);
        $this->payroll = app(PayrollService::class);
        $this->journals = app(JournalService::class);

        $this->unit = Unit::query()->where('code', 'MAN7')->firstOrFail();

        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->treasurer = User::query()->where('email', 'bendahara@email.com')->firstOrFail();
    }

    public function run(): void
    {
        $this->bootstrapServices();

        $students = Student::query()
            ->whereNotNull('school_class_id')
            ->orderBy('nis')
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('Siswa belum ada, seeder transaksi dilewati.');

            return;
        }

        $categories = PaymentCategory::query()
            ->where('code', '!=', 'PPDB')
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->keyBy('code');

        $periods = $this->periodsUntilNow();

        $this->command?->info('Menyiapkan tagihan...');
        $bills = $this->seedBills($students, $categories, $periods);

        $this->command?->info('Menyiapkan pembayaran dan verifikasi...');
        $this->seedPayments($students, $bills);

        $this->command?->info('Menyiapkan tabungan siswa...');
        $this->seedSavings($students);

        $this->command?->info('Menyiapkan pengeluaran dan honorarium...');
        $this->seedExpenses();
        $this->seedPayroll();

        $this->command?->info('Menyiapkan jurnal penerimaan pembayaran...');
        $this->seedIncomeJournals($periods);

        $this->command?->info(sprintf(
            'Transaksi demo siap: %d tagihan, %d pembayaran, %d kas, %d jurnal, %d tabungan.',
            StudentBill::query()->count(),
            Payment::query()->count(),
            CashTransaction::query()->count(),
            Journal::query()->count(),
            StudentSavingsTransaction::query()->count(),
        ));
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<string, PaymentCategory>  $categories
     * @param  array<int, string>  $periods
     * @return Collection<string, StudentBill>
     */
    private function seedBills(Collection $students, Collection $categories, array $periods): Collection
    {
        foreach ($students as $student) {
            $level = (string) $student->schoolClass?->level;

            foreach ($categories as $code => $category) {
                // Periode non-bulanan memakai label bebas agar tidak dianggap
                // sebagai tagihan bulanan (mis. "UAS" / "2026" untuk uang gedung).
                $studentPeriods = match ($code) {
                    'SPP' => $periods,
                    'KEGIATAN', 'UJIAN' => [$level === 'XII' ? 'UAS' : $periods[count($periods) - 1]],
                    'UANG-GEDUNG', 'SERAGAM' => $level === 'X' ? [substr($periods[0], 0, 4)] : [],
                    default => [],
                };

                foreach ($studentPeriods as $period) {
                    $exists = StudentBill::query()
                        ->where('student_id', $student->id)
                        ->whereHas('bill', fn ($query) => $query
                            ->where('payment_category_id', $category->id)
                            ->where('period', $period))
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $this->billing->create([
                        'student_id' => $student->id,
                        'payment_category_id' => $category->id,
                        'academic_year_id' => $this->year->id,
                        'amount' => (float) $category->default_amount,
                        'period' => $period,
                        'due_date' => $this->dueDate($period, (int) $category->due_day),
                    ]);
                }
            }
        }

        return StudentBill::query()->with('bill')->orderBy('id')->get()
            ->keyBy(static fn (StudentBill $bill): string => $bill->student_id.'|'.$bill->bill->payment_category_id.'|'.$bill->bill->period);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<string, StudentBill>  $bills
     */
    private function seedPayments(Collection $students, Collection $bills): void
    {
        $methods = PaymentMethod::query()->get()->keyBy('code');

        foreach ($students as $index => $student) {
            $own = $bills->filter(static fn (StudentBill $bill): bool => $bill->student_id === $student->id);

            if ($own->isEmpty()) {
                continue;
            }

            $profile = $this->paymentProfile($index);

            // Pola pembayaran: sebagian siswa membayar berurutan sejak awal,
            // sebagian menunggak, satu pembayaran dibuat pending di akhir.
            foreach ($own->values() as $position => $bill) {
                if ($position >= $profile['paid_bills']) {
                    break;
                }

                $amount = $profile['partial_last'] && $position === $profile['paid_bills'] - 1
                    ? round((float) $bill->final_amount * 0.5, 2)
                    : (float) $bill->final_amount;

                $method = $methods[$profile['method']];
                $paidAt = $this->paymentDate($bill);

                $payment = $this->payments->create([
                    'student_id' => $student->id,
                    'payment_category_id' => $bill->bill->payment_category_id,
                    'academic_year_id' => $this->year->id,
                    'paid_at' => $paidAt,
                    'amount' => $amount,
                    'payment_method_id' => $method->id,
                    'notes' => 'Pembayaran '.$bill->bill->title.' periode '.$bill->bill->period,
                    'idempotency_key' => 'demo-'.$student->nis.'-'.$bill->id,
                ], [
                    ['student_bill_id' => $bill->id, 'amount' => $amount],
                ]);

                // Idempotency key membuat seeder aman diulang: pembayaran yang
                // sudah ada dikembalikan apa adanya dan tidak diverifikasi lagi.
                if ($payment->status === PaymentStatus::Pending) {
                    $this->payments->approve($payment);
                }

                $bill->refresh();
                $this->stampPayment($payment, $method, $paidAt);
                $this->stampCash($payment, $paidAt);
            }

            if ($profile['pending'] && $own->isNotEmpty()) {
                $this->seedPendingPayment($student, $own->values(), $methods['transfer']);
            }
        }
    }

    /**
     * Pembayaran yang masih menunggu verifikator (tidak mengurangi saldo).
     *
     * @param  Collection<int, StudentBill>  $own
     */
    private function seedPendingPayment(Student $student, Collection $own, PaymentMethod $method): void
    {
        $target = $own->first(static fn (StudentBill $bill): bool => (float) $bill->remaining_amount > 0);

        if ($target === null) {
            return;
        }

        $amount = (float) $target->remaining_amount;

        $payment = $this->payments->create([
            'student_id' => $student->id,
            'payment_category_id' => $target->bill->payment_category_id,
            'academic_year_id' => $this->year->id,
            'paid_at' => now()->subDays(2)->setTime(9, 30),
            'amount' => $amount,
            'payment_method_id' => $method->id,
            'notes' => 'Bukti transfer belum diverifikasi',
            'idempotency_key' => 'demo-pending-'.$student->nis,
        ], [
            ['student_bill_id' => $target->id, 'amount' => $amount],
        ]);

        $this->stampPayment($payment, $method, now()->subDays(2)->setTime(9, 30));
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedSavings(Collection $students): void
    {
        // Tabungan tidak punya idempotency key seperti pembayaran, jadi dicek
        // manual agar seeder yang diulang tidak menggandakan setoran.
        if (StudentSavingsTransaction::query()->exists()) {
            return;
        }

        foreach ($students->take(8)->values() as $index => $student) {
            $this->deposit($student, 150000 + $index * 25000, 'Setoran tabungan awal');
        }

        // Riwayat setoran tambahan beberapa bulan terakhir.
        foreach ($students->take(8)->values() as $index => $student) {
            if ($index % 3 === 0) {
                $this->deposit($student, 100000, 'Setoran tabungan bulanan');
            }
        }

        $withdrawer = $students->take(8)->values()->get(1);

        if ($withdrawer !== null) {
            $request = $this->savings->requestWithdrawal($withdrawer, 50000, 'Penarikan untuk keperluan alat tulis', $this->treasurer);
            $this->savings->approve($request, $this->treasurer);
        }
    }

    private function deposit(Student $student, float $amount, string $description): void
    {
        $transaction = $this->savings->deposit($student, $amount, $description, $this->treasurer);

        // Seeder berjalan sebelum transaksi lain di hari yang sama; waktu
        // disebar agar query "setoran terakhir" tidak menumpuk di satu jam.
        $transaction->forceFill(['transacted_at' => now()->subDays($transaction->id % 21)])->save();
    }

    private function seedExpenses(): void
    {
        $cash = Account::query()->where('code', '1101')->firstOrFail();
        $operational = Account::query()->where('code', '5101')->firstOrFail();

        $expenses = [
            ['Pembelian ATK dan fotokopi administrasi', 275000, 6],
            ['Konsumsi rapat komite madrasah', 450000, 12],
            ['Servis printer dan penggantian toner', 380000, 18],
            ['Tagihan listrik dan air madrasah', 1250000, 24],
            ['Pembelian bahan praktikum laboratorium', 675000, 30],
        ];

        foreach ($expenses as [$description, $amount, $daysAgo]) {
            $date = now()->subDays($daysAgo)->toDateString();

            $existing = CashTransaction::query()
                ->where('type', CashTransactionType::Expense->value)
                ->where('description', $description)
                ->whereDate('transacted_at', $date)
                ->exists();

            if ($existing) {
                continue;
            }

            $this->expenses->record([
                'account_id' => $cash->id,
                'expense_account_id' => $operational->id,
                'amount' => $amount,
                'transacted_at' => $date,
                'description' => $description,
                'reference_number' => 'NOTA-'.Carbon::parse($date)->format('Ymd'),
            ], $this->treasurer);
        }
    }

    private function seedPayroll(): void
    {
        $period = now()->format('Y-m');

        if (PayrollPeriod::query()->where('period', $period)->exists()) {
            return;
        }

        $cash = Account::query()->where('code', '1102')->firstOrFail();
        $expense = Account::query()->where('code', '5102')->firstOrFail();

        $payroll = $this->payroll->createPeriod([
            'period' => $period,
            'title' => 'Honorarium Komite '.now()->translatedFormat('F Y'),
            'description' => 'Honorarium guru dan karyawan madrasah.',
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->year->id,
            'cash_account_id' => $cash->id,
            'expense_account_id' => $expense->id,
        ], $this->treasurer);

        $staff = StaffProfile::query()->where('unit_id', $this->unit->id)->where('is_active', true)->orderBy('id')->get();

        $honorarium = [
            StaffPosition::Headmaster->value => [1750000, 250000],
            StaffPosition::Treasurer->value => [1250000, 150000],
            StaffPosition::PaymentOfficer->value => [1100000, 100000],
            StaffPosition::Verifier->value => [1100000, 100000],
            StaffPosition::AdmissionOperator->value => [1050000, 75000],
            StaffPosition::Teacher->value => [900000, 50000],
            StaffPosition::AdministrativeStaff->value => [850000, 50000],
        ];

        foreach ($staff as $profile) {
            [$gross, $deduction] = $honorarium[$profile->position->value] ?? [850000, 50000];

            $this->payroll->addItem($payroll, [
                'staff_profile_id' => $profile->id,
                'recipient_name' => $profile->name,
                'recipient_position' => $profile->position->label(),
                'gross_amount' => $gross,
                'deduction_amount' => $deduction,
                'notes' => 'Honorarium bulanan',
            ], $this->treasurer);
        }

        $this->payroll->approve($payroll, $this->treasurer);
    }

    /**
     * Jurnal penerimaan kas per bulan dari pembayaran yang sudah disetujui.
     *
     * `PaymentService` hanya menulis baris kas; jurnal penerimaan dibentuk di
     * sini per bulan agar debit rekening bank dan kredit pendapatan seimbang.
     *
     * @param  array<int, string>  $periods
     */
    private function seedIncomeJournals(array $periods): void
    {
        $bank = Account::query()->where('code', '1102')->firstOrFail();
        $sppRevenue = Account::query()->where('code', '4101')->firstOrFail();
        $buildingRevenue = Account::query()->where('code', '4102')->firstOrFail();

        foreach ($periods as $period) {
            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $sums = Payment::query()
                ->where('status', 'approved')
                ->whereBetween('paid_at', [$start, $end])
                ->selectRaw('payment_category_id, SUM(amount) as total')
                ->groupBy('payment_category_id')
                ->pluck('total', 'payment_category_id');

            $sppCode = PaymentCategory::query()->where('code', 'SPP')->value('id');
            $buildingCode = PaymentCategory::query()->where('code', 'UANG-GEDUNG')->value('id');

            $spp = round((float) ($sums[$sppCode] ?? 0), 2);
            $others = round((float) $sums->sum() - $spp, 2);
            $total = round($spp + $others, 2);

            if ($total <= 0) {
                continue;
            }

            $reference = 'REKAP-'.$period;

            if (Journal::query()->where('reference_number', $reference)->exists()) {
                continue;
            }

            $lines = [
                ['account_id' => $bank->id, 'debit' => $total, 'description' => 'Penerimaan pembayaran siswa'],
            ];

            if ($spp > 0) {
                $lines[] = ['account_id' => $sppRevenue->id, 'credit' => $spp, 'description' => 'Pendapatan SPP'];
            }

            if ($others > 0) {
                $lines[] = [
                    'account_id' => $buildingRevenue->id,
                    'credit' => $others,
                    'description' => $buildingCode === null ? 'Pendapatan lain' : 'Pendapatan uang gedung & lainnya',
                ];
            }

            $this->journals->post(
                $end->toDateString(),
                'Rekap penerimaan pembayaran siswa '.Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y'),
                $reference,
                $lines,
                $this->treasurer,
            );
        }
    }

    /**
     * Tanggal penerimaan disesuaikan agar masuk ke bulan periode tagihan.
     */
    private function paymentDate(StudentBill $bill): Carbon
    {
        $period = (string) $bill->bill->period;
        $base = preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? Carbon::createFromFormat('Y-m', $period)->startOfMonth()
            : Carbon::parse($bill->due_date ?? now())->startOfMonth();

        $day = min(max((int) ($bill->due_date?->format('d') ?? 5), 1), 27);
        $date = $base->copy()->addDays($day - 1)->setTime(8 + ($bill->id % 6), 15);

        return $date->greaterThan(now()) ? now()->subDays(1)->setTime(10, 0) : $date;
    }

    private function stampPayment(Payment $payment, PaymentMethod $method, Carbon $paidAt): void
    {
        $payment->forceFill(['paid_at' => $paidAt])->save();

        $payment->details()->update([
            'payment_method_id' => $method->id,
            'account_id' => Account::query()->where('code', $method->code === 'cash' ? '1101' : '1102')->value('id'),
            'reference_number' => $method->code === 'cash' ? null : 'TRF-'.$payment->transaction_number,
        ]);
    }

    /**
     * Selaraskan waktu transaksi kas dengan tanggal pembayaran aslinya.
     */
    private function stampCash(Payment $payment, Carbon $paidAt): void
    {
        CashTransaction::query()
            ->where('payment_id', $payment->id)
            ->update(['transacted_at' => $paidAt]);
    }

    /**
     * @return array<int, string>
     */
    private function periodsUntilNow(): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $this->year->start_date->format('Y-m-d'))->startOfMonth();
        $end = now()->startOfMonth();

        $periods = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $periods[] = $cursor->format('Y-m');
        }

        return $periods === [] ? [now()->format('Y-m')] : $periods;
    }

    private function dueDate(string $period, int $dueDay): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            return (string) $this->year->end_date->format('Y-m-d');
        }

        $month = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $day = min(max($dueDay, 1), $month->daysInMonth);

        return $month->day($day)->format('Y-m-d');
    }

    /**
     * Profil pembayaran per siswa, ditentukan deterministik dari urutan siswa.
     *
     * @return array{paid_bills:int, partial_last:bool, method:string, pending:bool}
     */
    private function paymentProfile(int $index): array
    {
        return match ($index % 4) {
            // Lancar: semua tagihan bulanan lunas, transfer bank.
            0 => ['paid_bills' => 99, 'partial_last' => false, 'method' => 'transfer', 'pending' => false],
            // Sebagian: bayar sampai bulan lalu, cicilan terakhir separuh.
            1 => ['paid_bills' => 3, 'partial_last' => true, 'method' => 'qris', 'pending' => false],
            // Menunggak: hanya beberapa pembayaran awal, tunai.
            2 => ['paid_bills' => 2, 'partial_last' => false, 'method' => 'cash', 'pending' => true],
            // Sedang berjalan: belum bayar bulan ini.
            3 => ['paid_bills' => 4, 'partial_last' => false, 'method' => 'transfer', 'pending' => false],
        };
    }
}