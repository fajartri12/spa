<?php

declare(strict_types=1);

namespace App\Services\Reconciliation;

use App\Models\BankStatementProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Pembaca berkas mutasi rekening (CSV / XLSX).
 *
 * Kelas ini tidak menyentuh basis data. Ia hanya mengubah berkas menjadi baris
 * ternormalisasi sehingga logika pencocokan dapat diuji terpisah dari unggahan.
 *
 * Pemetaan kolom diambil dari profil bank, tetapi dapat ditimpa per import
 * (`$overrides`) untuk berkas yang formatnya berbeda dari template.
 */
final class StatementParser
{
    /**
     * @param  array<string, string|null>  $overrides
     * @return array<int, array{row:int, transacted_at:string, description:?string, reference:?string, debit:float, credit:float, balance:?float}>
     */
    public function parse(string $path, BankStatementProfile $profile, array $overrides = []): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Berkas mutasi tidak ditemukan.');
        }

        $matrix = $this->readMatrix($path);

        if ($matrix === []) {
            throw new InvalidArgumentException('Berkas mutasi kosong.');
        }

        $map = $this->resolveMap($profile, $overrides);
        $headerIndex = max(0, (int) $profile->header_row);

        if (! isset($matrix[$headerIndex])) {
            throw new InvalidArgumentException('Baris header tidak ditemukan pada berkas mutasi.');
        }

        $headers = $this->normaliseHeaders($matrix[$headerIndex]);
        $positions = $this->positions($headers, $map);

        if (! isset($positions['transacted_at'])) {
            throw new InvalidArgumentException("Kolom tanggal '{$map['transacted_at']}' tidak ditemukan pada berkas.");
        }

        $rows = [];
        $seen = [];

        foreach ($matrix as $index => $cells) {
            if ($index <= $headerIndex || $this->isBlankRow($cells)) {
                continue;
            }

            $row = $this->buildRow($cells, $positions, $profile);

            if ($row === null) {
                continue;
            }

            // Sebagian bank mengulang baris saldo akhir di kaki berkas.
            $signature = implode('|', [
                $row['transacted_at'],
                $row['description'],
                $row['reference'],
                $row['debit'],
                $row['credit'],
            ]);

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $rows[] = ['row' => $index + 1] + $row;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Tidak ada baris mutasi yang dapat dibaca dari berkas.');
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readMatrix(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            try {
                $sheet = IOFactory::load($path)->getActiveSheet();
            } catch (ReaderException $exception) {
                throw new InvalidArgumentException('Berkas Excel tidak dapat dibaca.', 0, $exception);
            }

            /** @var array<int, array<int, mixed>> $data */
            $data = $sheet->toArray(null, true, false, false);

            return $data;
        }

        return $this->readCsv($path);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Berkas CSV tidak dapat dibuka.');
        }

        $rows = [];
        $first = true;

        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            if ($cells === [null] || $cells === []) {
                $rows[] = [];

                continue;
            }

            if ($first) {
                // Buang BOM UTF-8 pada sel pertama.
                $cells[0] = str_replace("\u{FEFF}", '', (string) ($cells[0] ?? ''));
                $first = false;
            }

            $rows[] = $cells;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    private function resolveMap(BankStatementProfile $profile, array $overrides): array
    {
        $map = $profile->columnMap();

        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $map) && filled($value)) {
                $map[$key] = (string) $value;
            }
        }

        if (blank($map['transacted_at'])) {
            throw new InvalidArgumentException('Kolom tanggal pada profil mutasi belum diatur.');
        }

        $hasDebitCredit = filled($map['debit'] ?? null) || filled($map['credit'] ?? null);

        if (! $hasDebitCredit && blank($map['amount'] ?? null)) {
            throw new InvalidArgumentException('Profil mutasi harus memiliki kolom debit/kredit atau kolom nominal.');
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, string>
     */
    private function normaliseHeaders(array $cells): array
    {
        $headers = [];

        foreach ($cells as $index => $value) {
            $headers[$index] = Str::lower(trim(str_replace("\u{FEFF}", '', (string) $value)));
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, string|null>  $map
     * @return array<string, int>
     */
    private function positions(array $headers, array $map): array
    {
        $positions = [];
        $lookup = [];
        $compacted = [];

        foreach ($headers as $index => $header) {
            $lookup[$header] = $index;
            $compacted[$this->compact($header)] = $index;
        }

        foreach ($map as $key => $column) {
            if (blank($column)) {
                continue;
            }

            $needle = Str::lower(trim((string) $column));

            if (isset($lookup[$needle])) {
                $positions[$key] = (int) $lookup[$needle];

                continue;
            }

            // Nama kolom bank kadang memuat spasi/tanda baca tambahan.
            $compactNeedle = $this->compact($needle);

            if (isset($compacted[$compactNeedle])) {
                $positions[$key] = (int) $compacted[$compactNeedle];
            }
        }

        return $positions;
    }

    private function compact(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower($value));
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $positions
     * @return array{transacted_at:string, description:?string, reference:?string, debit:float, credit:float, balance:?float}|null
     */
    private function buildRow(array $cells, array $positions, BankStatementProfile $profile): ?array
    {
        $date = $this->parseDate($cells[$positions['transacted_at']] ?? null, (string) ($profile->date_format ?: 'd/m/Y'));

        if ($date === null) {
            return null;
        }

        $description = isset($positions['description'])
            ? $this->text($cells[$positions['description']] ?? null)
            : null;

        $reference = isset($positions['reference'])
            ? $this->text($cells[$positions['reference']] ?? null)
            : null;

        // Nomor referensi sering hanya tertulis di kolom berita.
        if (blank($reference) && $description !== null) {
            $reference = $description;
        }

        $debit = 0.0;
        $credit = 0.0;

        if (isset($positions['debit']) || isset($positions['credit'])) {
            $debit = abs($this->parseNumber($cells[$positions['debit']] ?? null, $profile));
            $credit = abs($this->parseNumber($cells[$positions['credit']] ?? null, $profile));
        } elseif (isset($positions['amount'])) {
            $amount = $this->parseNumber($cells[$positions['amount']] ?? null, $profile);

            if ($amount < 0) {
                $debit = abs($amount);
            } else {
                $credit = $amount;
            }
        }

        $balance = isset($positions['balance'])
            ? $this->parseNumber($cells[$positions['balance']] ?? null, $profile)
            : null;

        if ($debit <= 0 && $credit <= 0) {
            return null;
        }

        return [
            'transacted_at' => $date,
            'description' => $description,
            'reference' => $reference,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'balance' => $balance,
        ];
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 500);
    }

    private function parseDate(mixed $value, string $format): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        // Excel menyimpan tanggal sebagai angka serial.
        if (is_numeric($text) && (float) $text > 20000 && (float) $text < 80000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $text)->format('Y-m-d');
            } catch (Throwable) {
                // lanjut ke parsing teks
            }
        }

        $formats = array_values(array_unique([$format, 'd/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'Y/m/d']));

        foreach ($formats as $candidate) {
            try {
                $parsed = Carbon::createFromFormat('!'.$candidate, $text);
            } catch (Throwable) {
                continue;
            }

            if ($parsed !== false && $parsed->format($candidate) === $text) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseNumber(mixed $value, BankStatementProfile $profile): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $text = trim($text, '()');

        $thousands = (string) ($profile->thousands_separator ?: '.');
        $decimal = (string) ($profile->decimal_separator ?: ',');

        $text = (string) preg_replace('/[^\d\-.,]/', '', $text);

        if ($thousands !== '') {
            $text = str_replace($thousands, '', $text);
        }

        if ($decimal !== '.') {
            $text = str_replace($decimal, '.', $text);
        }

        if (! is_numeric($text)) {
            return 0.0;
        }

        $number = (float) $text;

        return $negative ? -abs($number) : $number;
    }
}
