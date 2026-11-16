<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = ['admission_period_id', 'student_id', 'registration_number', 'name', 'email', 'phone', 'gender', 'birth_place', 'birth_date', 'address', 'parent_name', 'parent_phone', 'status', 'converted_at', 'notes'];

    protected function casts(): array { return ['birth_date' => 'date', 'converted_at' => 'datetime']; }

    public function admissionPeriod(): BelongsTo { return $this->belongsTo(AdmissionPeriod::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function documents(): HasMany { return $this->hasMany(ApplicantDocument::class); }
    public function bills(): HasMany { return $this->hasMany(AdmissionBill::class); }

    public function isConverted(): bool
    {
        return $this->student_id !== null;
    }
}
