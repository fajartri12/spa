<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tagihan digital (Virtual Account / QRIS dinamis) untuk satu tagihan siswa.
 *
 * Satu tagihan dapat memiliki beberapa intent bila yang sebelumnya sudah
 * kedaluwarsa, tetapi hanya satu yang boleh berstatus `pending`. Status hanya
 * berubah melalui `PaymentGatewayService` atau webhook tervalidasi.
 */
class PaymentIntent extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_bill_id',
        'student_id',
        'provider',
        'channel',
        'reference',
        'pay_code',
        'qr_url',
        'qr_string',
        'amount',
        'fee',
        'status',
        'expires_at',
        'paid_at',
        'provider_reference',
        'provider_payload',
        'payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GatewayProvider::class,
            'status' => PaymentIntentStatus::class,
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StudentBill, $this>
     */
    public function studentBill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @param  Builder<PaymentIntent>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PaymentIntentStatus::Pending->value);
    }

    /**
     * Intent yang masih dapat dibayar: pending dan belum lewat jatuh tempo.
     *
     * @param  Builder<PaymentIntent>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->pending()->where(function (Builder $inner): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @param  Builder<PaymentIntent>  $query
     */
    public function scopeForStudent(Builder $query, int $studentId): void
    {
        $query->where('student_id', $studentId);
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentIntentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Label kanal untuk tampilan, mis. "BRIVA".
     */
    public function channelLabel(): string
    {
        return (string) ($this->channel ?: '-');
    }
}
