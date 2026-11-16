<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\BillDiscount;
use App\Models\BillItem;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BillingService
{
    public function __construct(
        private readonly BillNumberGenerator $numbers,
        private readonly DiscountService $discounts,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Buat tagihan untuk satu siswa.
     *
     * Potongan dihitung dari aturan `student_discounts` yang aktif kecuali
     * `discount_amount` dikirim eksplisit (dipakai untuk koreksi manual).
     * Rincian potongan disimpan di `bill_discounts` agar dapat diaudit.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function create(array $attributes): object
    {
        return DB::transaction(function () use ($attributes): object {
            $student = Student::query()->lockForUpdate()->findOrFail($attributes['student_id']);
            $category = PaymentCategory::query()->lockForUpdate()->findOrFail($attributes['payment_category_id']);
            $period = (string) ($attributes['period'] ?? now()->format('Y-m'));
            $academicYearId = $attributes['academic_year_id'] ?? null;

            $duplicate = DB::table('bills')->join('student_bills', 'student_bills.bill_id', '=', 'bills.id')
                ->where('student_bills.student_id', $student->id)->where('bills.payment_category_id', $category->id)->where('bills.period', $period)
                ->when($academicYearId !== null, fn ($q) => $q->where('bills.academic_year_id', $academicYearId))->lockForUpdate()->exists();
            if ($duplicate) throw new InvalidArgumentException('Tagihan untuk siswa, kategori, dan periode tersebut sudah ada.');
            $amount = round((float) ($attributes['amount'] ?? $category->default_amount), 2);
            if ($amount < 0) throw new InvalidArgumentException('Nominal tagihan tidak valid.');

            $manualDiscount = array_key_exists('discount_amount', $attributes) && $attributes['discount_amount'] !== null;
            $discountLines = [];

            if ($manualDiscount) {
                $discount = round((float) $attributes['discount_amount'], 2);
            } else {
                $calculated = $this->discounts->calculate($student, $category, $amount);
                $discount = $calculated['amount'];
                $discountLines = $calculated['lines'];
            }

            if ($discount < 0 || $discount > $amount) throw new InvalidArgumentException('Nominal potongan tidak valid.');

            $items = $this->normaliseItems($attributes['items'] ?? [], $amount);
            $number = $this->numbers->generate();
            $bill = new Bill();
            $bill->fill([
                'bill_number' => $number,
                'payment_category_id' => $category->id,
                'academic_year_id' => $academicYearId ?? throw new InvalidArgumentException('Tahun ajaran wajib diisi.'),
                'title' => $attributes['title'] ?? $category->name,
                'description' => $attributes['description'] ?? null,
                'period' => $period,
                'due_date' => $attributes['due_date'] ?? $this->dueDate($period, $category->due_day),
                'amount' => $amount,
                'original_amount' => $amount,
                'discount_amount' => $discount,
                'total_amount' => round($amount - $discount, 2),
                'status' => 'unpaid',
            ]);
            $bill->save();

            if ($items !== []) {
                foreach ($items as $index => $item) {
                    BillItem::query()->create([
                        'bill_id' => $bill->id,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'sort_order' => $index,
                    ]);
                }
            }

            $studentBill = new StudentBill();
            $studentBill->fill([
                'bill_id' => $bill->id,
                'student_id' => $student->id,
                'bill_number' => $number,
                'original_amount' => $amount,
                'discount_amount' => $discount,
                'final_amount' => round($amount - $discount, 2),
                'paid_amount' => 0,
                'remaining_amount' => round($amount - $discount, 2),
                'due_date' => $bill->due_date,
                'status' => 'unpaid',
            ]);
            $studentBill->save();

            foreach ($discountLines as $line) {
                BillDiscount::query()->create([
                    'student_bill_id' => $studentBill->id,
                    'student_discount_id' => $line['student_discount_id'],
                    'type' => $line['type'],
                    'value' => $line['value'],
                    'amount' => $line['amount'],
                    'reason' => $line['reason'],
                ]);
            }

            if ($discount > 0) {
                $this->logger->log(
                    'bills.discount_applied',
                    "Potongan {$discount} diterapkan pada tagihan {$number}",
                    $studentBill,
                    ['discount' => $discount, 'lines' => $discountLines],
                );
            }

            return $studentBill;
        });
    }

    /**
     * Batalkan tagihan. Ditolak bila sudah ada pembayaran yang dialokasikan.
     */
    public function cancel(StudentBill $studentBill): StudentBill
    {
        return DB::transaction(function () use ($studentBill): StudentBill {
            $fresh = StudentBill::query()->lockForUpdate()->findOrFail($studentBill->getKey());

            if ($fresh->status === BillStatus::Cancelled) {
                throw new InvalidArgumentException('Tagihan sudah dibatalkan.');
            }

            $hasPayment = DB::table('payment_allocations')
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->where('payment_allocations.student_bill_id', $fresh->getKey())
                ->whereNull('payments.deleted_at')
                ->whereIn('payments.status', ['pending', 'approved'])
                ->exists();

            if ($hasPayment || (float) $fresh->paid_amount > 0) {
                throw new InvalidArgumentException('Tagihan yang sudah memiliki pembayaran tidak dapat dibatalkan.');
            }

            $fresh->forceFill(['status' => BillStatus::Cancelled])->save();

            return $fresh;
        });
    }

    /**
     * Validasi rincian item tagihan: jumlah item dibatasi pengaturan sistem dan
     * total item (bila diisi) harus sama dengan nominal tagihan.
     *
     * @param  mixed  $items
     * @return array<int, array{description: string, amount: float}>
     */
    private function normaliseItems(mixed $items, float $amount): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        $maxItems = max(1, (int) AppSetting::get('max_bill_items', 6));

        if (count($items) > $maxItems) {
            throw new InvalidArgumentException("Jumlah item tagihan melebihi batas {$maxItems} item.");
        }

        $normalised = [];
        $total = 0.0;

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $value = round((float) ($item['amount'] ?? 0), 2);

            if ($description === '') {
                throw new InvalidArgumentException('Deskripsi item tagihan wajib diisi.');
            }

            if ($value <= 0) {
                throw new InvalidArgumentException('Nominal item tagihan harus lebih dari nol.');
            }

            $normalised[] = ['description' => mb_substr($description, 0, 150), 'amount' => $value];
            $total += $value;
        }

        if (round($total, 2) !== round($amount, 2)) {
            throw new InvalidArgumentException('Total item tagihan harus sama dengan nominal tagihan.');
        }

        return $normalised;
    }

    private function dueDate(string $period, mixed $day): string
    {
        $date = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $targetDay = (int) ($day ?: AppSetting::get('bill_due_day', 10));
        return $date->day(min(max($targetDay, 1), $date->daysInMonth))->format('Y-m-d');
    }
}
