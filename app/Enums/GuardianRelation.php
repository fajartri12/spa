<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Hubungan orang tua/wali dengan siswa.
 *
 * Dipakai untuk menentukan label pada portal orang tua dan formulir data wali.
 */
enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Pemetaan label bebas pada `students.parent_relation` ke enum.
     */
    public static function fromLabel(?string $label): ?self
    {
        $normalized = strtolower(trim((string) $label));

        return match (true) {
            $normalized === '' => null,
            str_contains($normalized, 'ayah'), str_contains($normalized, 'bapak') => self::Father,
            str_contains($normalized, 'ibu'), str_contains($normalized, 'mama') => self::Mother,
            default => self::Guardian,
        };
    }
}
