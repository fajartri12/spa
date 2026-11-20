<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ScholarshipAward;
use App\Models\ScholarshipProgram;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pengelolaan program beasiswa, keringanan, dan penerimaan beasiswa siswa.
 */
final class ScholarshipService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly DiscountService $discountService,
    ) {}

    /**
     * Berikan beasiswa / keringanan kepada siswa.
     */
    public function award(
        ScholarshipProgram $program,
        Student $student,
        ?Carbon $startsOn,
        ?Carbon $endsOn,
        ?string $notes,
        User $actor,
    ): ScholarshipAward {
        return DB::transaction(function () use ($program, $student, $startsOn, $endsOn, $notes, $actor): ScholarshipAward {
            $program = ScholarshipProgram::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if (! $program->is_active) {
                throw new InvalidArgumentException('Program beasiswa tidak aktif.');
            }

            if ($program->quota !== null) {
                $currentCount = ScholarshipAward::query()
                    ->where('scholarship_program_id', $program->id)
                    ->where('status', ScholarshipAward::STATUS_ACTIVE)
                    ->count();

                if ($currentCount >= $program->quota) {
                    throw new InvalidArgumentException("Kuota penerima beasiswa {$program->name} sudah penuh.");
                }
            }

            $existing = ScholarshipAward::query()
                ->where('scholarship_program_id', $program->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing !== null && $existing->isActive()) {
                throw new InvalidArgumentException('Siswa sudah menjadi penerima beasiswa ini.');
            }

            // Buat atau perbarui StudentDiscount
            $discount = StudentDiscount::query()->create([
                'student_id' => $student->id,
                'payment_category_id' => $program->payment_category_id,
                'type' => $program->discount_type,
                'value' => $program->discount_value,
                'reason' => 'Beasiswa: '.$program->name.($notes ? ' — '.$notes : ''),
                'is_active' => true,
                'scholarship_award_id' => null,
            ]);

            if ($existing !== null) {
                $existing->update([
                    'student_discount_id' => $discount->id,
                    'status' => ScholarshipAward::STATUS_ACTIVE,
                    'starts_on' => $startsOn ?? $program->valid_from,
                    'ends_on' => $endsOn ?? $program->valid_to,
                    'notes' => $notes,
                    'awarded_by' => $actor->id,
                    'revoked_at' => null,
                    'revocation_reason' => null,
                ]);

                $award = $existing;
            } else {
                $award = ScholarshipAward::query()->create([
                    'scholarship_program_id' => $program->id,
                    'student_id' => $student->id,
                    'student_discount_id' => $discount->id,
                    'status' => ScholarshipAward::STATUS_ACTIVE,
                    'starts_on' => $startsOn ?? $program->valid_from,
                    'ends_on' => $endsOn ?? $program->valid_to,
                    'notes' => $notes,
                    'awarded_by' => $actor->id,
                ]);
            }

            $discount->update(['scholarship_award_id' => $award->id]);

            $this->logger->log(
                'scholarship.awarded',
                "Pemberian beasiswa {$program->name} kepada {$student->name}",
                $award,
                ['program_id' => $program->id, 'student_id' => $student->id],
                $actor,
            );

            return $award;
        });
    }

    /**
     * Cabut / batalkan penerimaan beasiswa.
     */
    public function revoke(ScholarshipAward $award, string $reason, User $actor): ScholarshipAward
    {
        if (! $award->isActive()) {
            throw new InvalidArgumentException('Penerimaan beasiswa ini sudah tidak aktif.');
        }

        return DB::transaction(function () use ($award, $reason, $actor): ScholarshipAward {
            $award->update([
                'status' => ScholarshipAward::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);

            if ($award->student_discount_id !== null) {
                StudentDiscount::query()->whereKey($award->student_discount_id)->update([
                    'is_active' => false,
                ]);
            }

            $this->logger->log(
                'scholarship.revoked',
                "Pencabutan beasiswa ID {$award->id} dengan alasan: {$reason}",
                $award,
                ['reason' => $reason],
                $actor,
            );

            return $award;
        });
    }

    /**
     * Hitung realisasi potongan yang telah digunakan oleh penerima beasiswa ini.
     */
    public function realization(ScholarshipAward $award): float
    {
        if ($award->student_discount_id === null) {
            return 0.0;
        }

        return (float) DB::table('student_bill_discounts')
            ->where('student_discount_id', $award->student_discount_id)
            ->sum('amount');
    }

    /**
     * Jumlah penerima aktif per program.
     *
     * @return array<int, int> [program_id => count]
     */
    public function activeCounts(): array
    {
        return DB::table('scholarship_awards')
            ->where('status', ScholarshipAward::STATUS_ACTIVE)
            ->groupBy('scholarship_program_id')
            ->selectRaw('scholarship_program_id, COUNT(*) as count')
            ->pluck('count', 'scholarship_program_id')
            ->toArray();
    }
}
