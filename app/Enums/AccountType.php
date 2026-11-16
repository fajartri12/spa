<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis akun pada bagan akun (chart of accounts) sederhana.
 */
enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Kas',
            self::Bank => 'Bank',
            self::Receivable => 'Piutang',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Bank => 'primary',
            self::Receivable => 'warning',
            self::Revenue => 'info',
            self::Expense => 'danger',
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
}
