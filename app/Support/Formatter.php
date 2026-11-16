<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Helper format angka, mata uang, dan tanggal sesuai DESIGN.MD bagian 3.2 & 18.
 */
final class Formatter
{
    /**
     * Format nominal Rupiah: Rp 1.136.625.000
     */
    public static function rupiah(int|float|string|null $value, bool $withSymbol = true): string
    {
        $number = is_string($value) ? (float) str_replace(['.', ','], ['', '.'], $value) : (float) $value;
        $formatted = number_format($number, 0, ',', '.');
        $negative = $number < 0;

        $result = ($withSymbol ? 'Rp ' : '') . $formatted;

        return $negative ? '(' . str_replace('-', '', $result) . ')' : $result;
    }

    /**
     * Format tanggal Indonesia: 05 November 2026
     */
    public static function date(Carbon|string|null $value, string $format = 'd F Y'): string
    {
        if ($value === null) {
            return '-';
        }

        return Carbon::parse($value)->locale('id')->translatedFormat($format);
    }

    /**
     * Format tanggal dan waktu: 05 November 2026, 09:30 WIB
     */
    public static function dateTime(Carbon|string|null $value): string
    {
        if ($value === null) {
            return '-';
        }

        return Carbon::parse($value)->locale('id')->translatedFormat('d F Y, H:i') . ' WIB';
    }

    /**
     * Format jam: 09:30 WIB
     */
    public static function time(Carbon|string|null $value): string
    {
        if ($value === null) {
            return '-';
        }

        return Carbon::parse($value)->locale('id')->translatedFormat('H:i') . ' WIB';
    }

    /**
     * Format angka ringkas untuk card statistik: 1,2 Jt / 12,5 Rb
     */
    public static function compact(int|float $value): string
    {
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000_000 => number_format($value / 1_000_000_000, 1, ',', '.') . ' M',
            $abs >= 1_000_000 => number_format($value / 1_000_000, 1, ',', '.') . ' Jt',
            $abs >= 1_000 => number_format($value / 1_000, 0, ',', '.') . ' Rb',
            default => number_format($value, 0, ',', '.'),
        };
    }

    /**
     * Parsing nominal rupiah (misal "1.500.000" atau "Rp 1.500.000,00") menjadi angka desimal murni string.
     */
    public static function parseRupiah(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $cleaned = trim($value);

        // Hapus "Rp" dan spasi
        $cleaned = (string) preg_replace('/[^\d.,\-]/', '', $cleaned);

        if ($cleaned === '' || $cleaned === '-') {
            return '0';
        }

        // Format Indonesia lazim: 1.500.000 atau 1.500.000,50
        // Jika ada koma, anggap sebagai pemisah desimal
        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, '.')) {
            // Jika ada titik lebih dari 1 atau 3 digit setelah titik terakhir, anggap titik pemisah ribuan
            $parts = explode('.', $cleaned);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return is_numeric($cleaned) ? $cleaned : '0';
    }

    /**
     * Bilangan bulat menjadi kata: 1500000 → "Satu Juta Lima Ratus Ribu Rupiah".
     */
    public static function terbilang(int|float|string|null $value): string
    {
        $number = (int) round((float) static::parseRupiah($value));

        if ($number === 0) {
            return 'Nol Rupiah';
        }

        $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        $spell = function (int $n) use (&$spell, $words): string {
            if ($n < 12) {
                return $words[$n];
            }

            if ($n < 20) {
                return $spell($n - 10) . ' Belas';
            }

            if ($n < 100) {
                return $spell(intdiv($n, 10)) . ' Puluh ' . $spell($n % 10);
            }

            if ($n < 200) {
                return 'Seratus ' . $spell($n - 100);
            }

            if ($n < 1000) {
                return $spell(intdiv($n, 100)) . ' Ratus ' . $spell($n % 100);
            }

            if ($n < 2000) {
                return 'Seribu ' . $spell($n - 1000);
            }

            foreach ([1_000_000_000_000 => 'Triliun', 1_000_000_000 => 'Miliar', 1_000_000 => 'Juta', 1000 => 'Ribu'] as $divisor => $unit) {
                if ($n >= $divisor) {
                    return $spell(intdiv($n, $divisor)) . ' ' . $unit . ' ' . $spell($n % $divisor);
                }
            }

            return '';
        };

        return trim((string) preg_replace('/\s+/', ' ', $spell($number))) . ' Rupiah';
    }
}
