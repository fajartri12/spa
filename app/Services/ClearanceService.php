<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\ClearanceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\GraduationClearance;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bebas tanggungan sebagai prasyarat penerbitan Surat Keterangan Lulus (SKL).
 *
 * Pemeriksaan menghitung sisa seluruh tagihan siswa (kecuali yang dibatalkan),
 * lalu menyimpan snapshot `remaining_amount`, `outstanding_bill_count`, dan
 * ringkasan tagihan pada `outstanding_bills`. Snapshot diperlukan agar
 * keputusan penerbitan SKL dapat diaudit walaupun saldo berubah kemudian.
 *
 * SKL hanya boleh diterbitkan bila status `cleared` (tidak ada sisa) atau
 * `overridden` (bendahara memberi pengecualian dengan alasan tertulis).
 */
final class ClearanceService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Hitung ulang bebas tanggungan seorang siswa.
     *
     * Pengecualian (override) yang sudah ada dipertahankan selama masih ada
     * sisa tagihan; bila tagihan sudah lunas, status kembali menjadi `cleared`.
     */
    public function check(Student $student, ?User $actor = null): GraduationClearance
    {
        return DB::transaction(function () use ($student, $actor): GraduationClearance {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);

            $bills = StudentBill::query()
                ->with('bill:id,title,payment_category_id')
                ->where('student_id', $student->id)
                ->where('status', '!=', BillStatus::Cancelled->value)
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->get();

            $remaining = round((float) $bills->sum('remaining_amount'), 2);

            $clearance = GraduationClearance::query()->firstOrNew(['student_id' => $student->id]);

            $status = $remaining <= 0
                ? ClearanceStatus::Cleared
                : ($clearance->exists && $clearance->status === ClearanceStatus::Overridden
                    ? ClearanceStatus::Overridden
                    : ClearanceStatus::Pending);

            $clearance->fill([
                'academic_year_id' => $student->schoolClass?->academic_year_id ?? AcademicYear::current()?->id,
                'status' => $status->value,
                'remaining_amount' => $remaining,
                'outstanding_bill_count' => $bills->count(),
                'outstanding_bills' => $bills->map(static fn (StudentBill $bill): array => [
                    'bill_number' => $bill->bill_number,
                    'name' => $bill->bill?->title,
                    'due_date' => $bill->due_date?->toDateString(),
                    'final_amount' => (float) $bill->final_amount,
                    'paid_amount' => (float) $bill->paid_amount,
                    'remaining_amount' => (float) $bill->remaining_amount,
                ])->all(),
                'checked_by' => $actor?->id,
                'checked_at' => now(),
            ]);

            // Pengecualian tidak lagi relevan setelah tagihan lunas.
            if ($status === ClearanceStatus::Cleared && $clearance->overridden_at !== null) {
                $clearance->forceFill([
                    'overridden_by' => null,
                    'overridden_at' => null,
                    'override_reason' => null,
                ]);
            }

            $clearance->save();

            $this->logger->log(
                'clearance.checked',
                "Pemeriksaan bebas tanggungan {$student->name}: {$status->label()}",
                $clearance,
                ['remaining' => $remaining, 'bills' => $bills->count()],
                $actor,
            );

            return $clearance->refresh();
        });
    }

    /**
     * Periksa banyak siswa sekaligus (mis. seluruh calon lulus satu unit).
     *
     * @param  array<int, int>  $studentIds
     * @return int jumlah siswa yang diperiksa
     */
    public function checkMany(array $studentIds, ?User $actor = null): int
    {
        $count = 0;

        $students = Student::query()->whereIn('id', array_map('intval', $studentIds))->get();

        foreach ($students as $student) {
            $this->check($student, $actor);
            $count++;
        }

        return $count;
    }

    /**
     * Beri pengecualian walau masih ada sisa tagihan. Alasan wajib diisi
     * (AGENTS.MD bagian 9: audit logging).
     *
     * @throws InvalidArgumentException
     */
    public function override(Student $student, string $reason, User $actor): GraduationClearance
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Alasan pengecualian wajib diisi.');
        }

        $clearance = $this->check($student, $actor);

        if (! $clearance->hasOutstanding()) {
            throw new InvalidArgumentException('Siswa ini sudah bebas tanggungan; pengecualian tidak diperlukan.');
        }

        $clearance->forceFill([
            'status' => ClearanceStatus::Overridden->value,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
            'override_reason' => $reason,
        ])->save();

        $this->logger->log(
            'clearance.overridden',
            "Pengecualian bebas tanggungan untuk {$student->name}: {$reason}",
            $clearance,
            ['reason' => $reason, 'remaining' => (float) $clearance->remaining_amount],
            $actor,
        );

        return $clearance->refresh();
    }

    /**
     * Terbitkan nomor SKL. Hanya boleh bila status mengizinkan penerbitan.
     *
     * @throws InvalidArgumentException
     */
    public function issueSkl(Student $student, User $actor): GraduationClearance
    {
        return DB::transaction(function () use ($student, $actor): GraduationClearance {
            // Pemeriksaan ulang memastikan SKL tidak terbit di atas tagihan
            // yang baru saja muncul setelah pemeriksaan terakhir.
            $clearance = $this->check($student, $actor);

            if (! $clearance->status->allowsIssuance()) {
                throw new InvalidArgumentException(
                    'SKL tidak dapat diterbitkan: siswa masih memiliki tanggungan '
                    .number_format((float) $clearance->remaining_amount, 0, ',', '.')
                );
            }

            if ($student->status !== StudentStatus::Graduated) {
                throw new InvalidArgumentException('SKL hanya dapat diterbitkan untuk siswa berstatus lulus.');
            }

            if ($clearance->skl_number !== null) {
                return $clearance;
            }

            $clearance->forceFill([
                'skl_number' => $this->nextSklNumber(),
                'skl_issued_at' => now(),
                'skl_issued_by' => $actor->id,
            ])->save();

            $this->logger->log(
                'clearance.skl_issued',
                "Menerbitkan SKL {$clearance->skl_number} untuk {$student->name}",
                $clearance,
                ['student_id' => $student->id],
                $actor,
            );

            return $clearance->refresh();
        });
    }

    /**
     * Daftar calon lulus beserta status bebas tanggungan.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Student>
     */
    public function candidates(?int $unitId = null, ?int $classId = null): \Illuminate\Database\Eloquent\Collection
    {
        return Student::query()
            ->with([
                'unit:id,name',
                'schoolClass:id,name,academic_year_id',
                'clearance',
            ])
            ->whereIn('status', [StudentStatus::Active->value, StudentStatus::Graduated->value])
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->when($classId !== null, fn ($query) => $query->where('school_class_id', $classId))
            ->ordered()
            ->get();
    }

    /**
     * Nomor SKL berurutan per tahun: `SKL/{tahun}/0001`.
     */
    private function nextSklNumber(): string
    {
        $prefix = trim((string) AppSetting::get('skl_prefix', 'SKL')).'/'.now()->format('Y').'/';

        $last = GraduationClearance::withTrashed()
            ->where('skl_number', 'like', $prefix.'%')
            ->orderByDesc('skl_number')
            ->value('skl_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
