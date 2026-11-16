<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReconciliationMatchStatus;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\BankStatementProfile;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Reconciliation\StatementParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Import mutasi rekening koran dan pencocokan otomatis dengan pembayaran.
 *
 * Aturan pencocokan diurutkan dari bukti terkuat ke terlemah:
 * 1. `reference` — nomor transaksi pembayaran atau referensi tagihan digital
 *    tertulis pada berita/keterangan mutasi (keyakinan 100%).
 * 2. `unique_amount` — nominal mutasi sama dengan tepat satu pembayaran yang
 *    belum tercocokkan (keyakinan 90%). Hanya aktif bila
 *    `payment_gateway.reconciliation.unique_amount_enabled` dinyalakan, karena
 *    tagihan bulat berpotensi tertukar antar siswa.
 * 3. `amount_date` — nominal sama dan tanggal transaksi berdekatan dengan tepat
 *    satu pembayaran (keyakinan 75%).
 *
 * Baris yang tidak lolos ketiga aturan dibiarkan `unmatched` untuk dicocokkan
 * manual oleh bendahara. Sistem tidak pernah menebak bila kandidat lebih dari
 * satu (AGENTS.MD bagian 6.4: validasi sebelum menyimpan).
 */
final class BankReconciliationService
{
    private const INSERT_BATCH = 200;

    public function __construct(
        private readonly StatementParser $parser,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Impor berkas mutasi dan jalankan pencocokan otomatis.
     *
     * @param  array{bank_account_id?:?int, account_id?:?int, notes?:?string, overrides?:array<string,string|null>, auto_match?:bool}  $options
     *
     * @throws InvalidArgumentException
     */
    public function import(BankStatementProfile $profile, UploadedFile $file, array $options, User $actor): BankStatementImport
    {
        $hash = hash_file('sha256', (string) $file->getRealPath());

        $duplicate = BankStatementImport::query()
            ->where('file_hash', $hash)
            ->where('bank_statement_profile_id', $profile->id)
            ->first();

        if ($duplicate !== null) {
            throw new InvalidArgumentException(
                "Berkas ini sudah pernah diimpor pada {$duplicate->created_at?->format('d/m/Y H:i')}."
            );
        }

        $rows = $this->parser->parse(
            (string) $file->getRealPath(),
            $profile,
            $options['overrides'] ?? [],
        );

        $path = $file->store('bank-statements', 'local');

        $import = DB::transaction(function () use ($profile, $file, $options, $actor, $rows, $path, $hash): BankStatementImport {
            $import = BankStatementImport::query()->create([
                'bank_statement_profile_id' => $profile->id,
                'bank_account_id' => $options['bank_account_id'] ?? null,
                'account_id' => $options['account_id'] ?? null,
                'user_id' => $actor->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_hash' => $hash,
                'status' => 'completed',
                'notes' => $options['notes'] ?? null,
            ]);

            foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
                $payload = [];

                foreach ($chunk as $row) {
                    $payload[] = [
                        'bank_statement_import_id' => $import->id,
                        'transacted_at' => $row['transacted_at'],
                        'description' => $row['description'],
                        'reference' => $row['reference'],
                        'debit' => $row['debit'],
                        'credit' => $row['credit'],
                        'balance' => $row['balance'],
                        'row_number' => $row['row'],
                        'match_status' => ReconciliationMatchStatus::Unmatched->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                BankStatementLine::query()->insert($payload);
            }

            return $import;
        });

        $matched = ($options['auto_match'] ?? true) ? $this->autoMatch($import, $actor) : 0;

        $import->refreshSummary();

        $this->logger->log(
            'bank_reconciliation.imported',
            "Impor mutasi {$profile->name}: ".count($rows).' baris, '.$matched.' tercocokkan otomatis',
            $import,
            ['rows' => count($rows), 'matched' => $matched],
            $actor,
        );

        return $import->refresh();
    }

    /**
     * Jalankan pencocokan otomatis untuk seluruh baris kredit yang belum cocok.
     *
     * @return int jumlah baris yang berhasil dicocokkan
     */
    public function autoMatch(BankStatementImport $import, ?User $actor = null): int
    {
        $tolerance = (int) config('payment_gateway.reconciliation.date_tolerance_days', 7);
        $uniqueAmount = (bool) config('payment_gateway.reconciliation.unique_amount_enabled', false);
        $matched = 0;

        $lines = BankStatementLine::query()
            ->where('bank_statement_import_id', $import->id)
            ->where('match_status', ReconciliationMatchStatus::Unmatched->value)
            ->credits()
            ->orderBy('transacted_at')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $payment = $this->findByReference($line)
                ?? ($uniqueAmount ? $this->findByUniqueAmount($line) : null)
                ?? $this->findByAmountAndDate($line, $tolerance);

            if ($payment === null) {
                continue;
            }

            try {
                $this->match($line, $payment, $actor, $this->resolveRule($line, $payment), auto: true);
                $matched++;
            } catch (InvalidArgumentException) {
                // Kandidat sudah dipakai baris lain; biarkan untuk pencocokan
                // manual berikutnya.
            }
        }

        return $matched;
    }

    /**
     * Cocokkan satu baris mutasi dengan satu pembayaran secara manual.
     *
     * @throws InvalidArgumentException
     */
    public function match(BankStatementLine $line, Payment $payment, ?User $actor = null, string $rule = 'manual', bool $auto = false): BankStatementLine
    {
        if (! $line->isCredit()) {
            throw new InvalidArgumentException('Hanya mutasi dana masuk yang dapat dicocokkan dengan pembayaran.');
        }

        if ($line->match_status === ReconciliationMatchStatus::Matched) {
            throw new InvalidArgumentException('Baris mutasi ini sudah tercocokkan.');
        }

        return DB::transaction(function () use ($line, $payment, $actor, $rule): BankStatementLine {
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($line->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Cancelled) {
                throw new InvalidArgumentException('Pembayaran yang dibatalkan tidak dapat direkonsiliasi.');
            }

            $taken = BankStatementLine::query()
                ->where('payment_id', $payment->id)
                ->where('match_status', ReconciliationMatchStatus::Matched->value)
                ->exists();

            if ($taken) {
                throw new InvalidArgumentException('Pembayaran ini sudah tercocokkan dengan baris mutasi lain.');
            }

            $confidence = match ($rule) {
                'reference' => 100.0,
                'unique_amount' => 90.0,
                'amount_date' => 75.0,
                default => null,
            };

            $line->forceFill([
                'match_status' => ReconciliationMatchStatus::Matched->value,
                'match_rule' => $rule,
                'match_confidence' => $confidence,
                'payment_id' => $payment->id,
                'matched_by' => $actor?->id,
                'matched_at' => now(),
            ])->save();

            $this->logger->log(
                'bank_reconciliation.matched',
                "Mutasi {$line->transacted_at?->format('d/m/Y')} senilai {$line->credit} dicocokkan dengan pembayaran {$payment->transaction_number} ({$rule})",
                $line,
                ['payment_id' => $payment->id, 'rule' => $rule],
                $actor,
            );

            return $line;
        });
    }

    /**
     * Lepas pencocokan agar baris dapat dicocokkan ulang.
     */
    public function unmatch(BankStatementLine $line, ?User $actor = null): BankStatementLine
    {
        $line->forceFill([
            'match_status' => ReconciliationMatchStatus::Unmatched->value,
            'match_rule' => null,
            'match_confidence' => null,
            'payment_id' => null,
            'matched_by' => null,
            'matched_at' => null,
        ])->save();

        $this->logger->log('bank_reconciliation.unmatched', 'Pencocokan mutasi dilepas', $line, [], $actor);

        return $line;
    }

    /**
     * Tandai baris yang memang bukan penerimaan siswa (bunga, biaya admin,
     * transfer antar rekening sendiri).
     */
    public function ignore(BankStatementLine $line, ?User $actor = null, ?string $reason = null): BankStatementLine
    {
        $line->forceFill([
            'match_status' => ReconciliationMatchStatus::Ignored->value,
            'match_rule' => 'ignored',
            'match_confidence' => null,
            'payment_id' => null,
            'matched_by' => $actor?->id,
            'matched_at' => now(),
        ])->save();

        $this->logger->log(
            'bank_reconciliation.ignored',
            $reason === null ? 'Baris mutasi diabaikan' : "Baris mutasi diabaikan: {$reason}",
            $line,
            ['reason' => $reason],
            $actor,
        );

        return $line;
    }

    private function resolveRule(BankStatementLine $line, Payment $payment): string
    {
        $text = $this->haystack($line);

        if ($text !== '' && str_contains($text, Str::upper((string) $payment->transaction_number))) {
            return 'reference';
        }

        return $this->hasSingleAmountCandidate($line) ? 'unique_amount' : 'amount_date';
    }

    /**
     * Cari pembayaran lewat nomor transaksi / referensi tagihan digital yang
     * tertulis pada keterangan mutasi.
     */
    private function findByReference(BankStatementLine $line): ?Payment
    {
        $text = $this->haystack($line);

        if ($text === '') {
            return null;
        }

        $tokens = $this->extractTokens($text);

        if ($tokens === []) {
            return null;
        }

        $payment = Payment::query()
            ->whereIn('transaction_number', $tokens)
            ->where('status', '!=', PaymentStatus::Cancelled->value)
            ->whereDoesntHave('reconciliationLines')
            ->first();

        if ($payment !== null) {
            return $payment;
        }

        $intent = PaymentIntent::query()
            ->whereIn('reference', $tokens)
            ->whereNotNull('payment_id')
            ->whereDoesntHave('payment.reconciliationLines')
            ->first();

        return $intent?->payment;
    }

    private function findByUniqueAmount(BankStatementLine $line): ?Payment
    {
        $candidates = $this->amountCandidates($line);

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function hasSingleAmountCandidate(BankStatementLine $line): bool
    {
        return $this->amountCandidates($line)->count() === 1;
    }

    private function findByAmountAndDate(BankStatementLine $line, int $toleranceDays): ?Payment
    {
        $postedAt = Carbon::parse($line->transacted_at)->startOfDay();

        $candidates = $this->amountCandidates($line)->filter(function (Payment $payment) use ($postedAt, $toleranceDays): bool {
            if ($payment->paid_at === null) {
                return false;
            }

            $distance = (int) abs($payment->paid_at->copy()->startOfDay()->diffInDays($postedAt));

            return $distance <= $toleranceDays;
        });

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * Pembayaran bernominal sama yang belum pernah dicocokkan.
     *
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    private function amountCandidates(BankStatementLine $line): \Illuminate\Support\Collection
    {
        $amount = (float) $line->credit;

        if ($amount <= 0) {
            return collect();
        }

        return Payment::query()
            ->where('amount', $amount)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Approved->value])
            ->whereDoesntHave('reconciliationLines')
            ->orderBy('paid_at')
            ->limit(2)
            ->get();
    }

    private function haystack(BankStatementLine $line): string
    {
        return Str::upper(trim(($line->description ?? '').' '.($line->reference ?? '')));
    }

    /**
     * @return array<int, string>
     */
    private function extractTokens(string $text): array
    {
        preg_match_all('/[A-Z0-9][A-Z0-9\-]{6,}[A-Z0-9]/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * Hapus berkas fisik hasil impor (baris tetap disimpan untuk audit).
     */
    public function forgetFile(BankStatementImport $import): void
    {
        if ($import->file_path !== null && Storage::disk('local')->exists($import->file_path)) {
            Storage::disk('local')->delete($import->file_path);
        }
    }
}
