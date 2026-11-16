<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDiscount extends Model
{
    use SoftDeletes;
    protected $fillable = ['student_id', 'payment_category_id', 'type', 'value', 'reason', 'is_active'];
    protected function casts(): array { return ['value' => 'decimal:2', 'is_active' => 'boolean']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function paymentCategory(): BelongsTo { return $this->belongsTo(PaymentCategory::class); }
    public function billDiscounts(): HasMany { return $this->hasMany(BillDiscount::class); }
}
