<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Support\Collection;

/**
 * Perhitungan potongan tagihan siswa.
 *
 * Potongan bersifat aditif: seluruh potongan aktif yang cocok (kategori sama
 * atau berlaku untuk semua kategori) dijumlahkan. Tipe `percent` dihitung dari
 * nominal tagihan, tipe `fixed` memakai nilai rupiah langsung. Total potongan
 * tidak boleh melebihi nominal tagihan.
 */
final class DiscountService
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_PERCENT => 'Persen (%)',
            self::TYPE_FIXED => 'Nominal (Rp)',
        ];
    }

    /**
     * Potongan aktif yang berlaku untuk siswa + kategori tertentu.
     *
     * @return Collection<int, StudentDiscount>
     */
    public function applicable(Student $student, PaymentCategory $category): Collection
    {
        return StudentDiscount::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->where(function ($query) use ($category): void {
                $query->whereNull('payment_category_id')
                    ->orWhere('payment_category_id', $category->id);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Hitung total potongan beserta rincian per aturan.
     *
     * @return array{amount: float, lines: array<int, array{student_discount_id: int, type: string, value: float, amount: float, reason: ?string}>}
     */
    public function calculate(Student $student, PaymentCategory $category, float $amount): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($this->applicable($student, $category) as $discount) {
            $value = (float) $discount->value;

            $lineAmount = $discount->type === self::TYPE_PERCENT
                ? round($amount * $value / 100, 2)
                : round($value, 2);

            if ($lineAmount <= 0) {
                continue;
            }

            $lines[] = [
                'student_discount_id' => $discount->id,
                'type' => (string) $discount->type,
                'value' => $value,
                'amount' => $lineAmount,
                'reason' => $discount->reason,
            ];

            $total += $lineAmount;
        }

        return [
            'amount' => min(round($total, 2), round($amount, 2)),
            'lines' => $lines,
        ];
    }
}
