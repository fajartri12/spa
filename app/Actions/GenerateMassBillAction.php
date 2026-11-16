<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Student;
use App\Services\BillingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GenerateMassBillAction
{
    public function __construct(private readonly BillingService $billing) {}

    /** @return Collection<int, object> */
    public function execute(array $attributes): Collection
    {
        $existing = DB::table('bills')
            ->join('student_bills', 'student_bills.bill_id', '=', 'bills.id')
            ->where('bills.payment_category_id', $attributes['payment_category_id'])
            ->where('bills.period', $attributes['period'])
            ->when($attributes['academic_year_id'] ?? null, fn ($q, $id) => $q->where('bills.academic_year_id', $id))
            ->pluck('student_bills.student_id')
            ->flip();

        $created = collect();

        // Diproses per 100 siswa agar transaksi tidak menahan ribuan baris
        // sekaligus pada sekolah besar.
        Student::query()
            ->active()
            ->when($attributes['unit_id'] ?? null, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($attributes['class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->chunkById(100, function (Collection $chunk) use ($attributes, $existing, $created): void {
                $targets = $chunk->reject(fn (Student $student) => $existing->has($student->id))->values();

                if ($targets->isEmpty()) {
                    return;
                }

                DB::transaction(function () use ($attributes, $targets, $created): void {
                    foreach ($targets as $student) {
                        $created->push($this->billing->create(array_merge($attributes, ['student_id' => $student->id])));
                    }
                });
            });

        if ($created->isEmpty()) {
            throw new \InvalidArgumentException('Tagihan untuk seluruh siswa pada kategori dan periode tersebut sudah ada.');
        }

        return $created->values();
    }
}
