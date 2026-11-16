<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mitigasi CSV/Excel formula injection (OWASP: CSV Injection).
 */
final class Csv
{
    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function safeAll(array $row): array
    {
        return array_map(self::safe(...), $row);
    }
}
