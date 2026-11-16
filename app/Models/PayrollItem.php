<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu slip honorarium dalam sebuah periode penggajian.
 *
 * `net_amount` adalah nilai yang dibayarkan ke penerima dan menjadi dasar
 * total kas keluar periode. `gross_amount - deduction_amount` harus sama
 * dengan `net_amount`; aturan ini ditegakkan di `PayrollService`.
 */
class PayrollItem extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_period_id',
        'staff_profile_id',
        'recipient_name',
        'recipient_position',
        'recipient_account',
        'recipient_bank',
        'gross_amount',
        'deduction_amount',
        'net_amount',
        'notes',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
