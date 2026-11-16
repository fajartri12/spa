<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembuatan jurnal double-entry.
 *
 * Satu-satunya tempat yang boleh menulis `journals`/`journal_details` agar
 * aturan debit = kredit selalu berlaku dan penomoran jurnal konsisten.
 */
final class JournalService
{
    /**
     * @param  array<int, array{account_id:int, debit?:float|int|string, credit?:float|int|string, description?:?string}>  $lines
     */
    public function post(
        string $date,
        string $description,
        ?string $reference,
        array $lines,
        ?User $actor = null,
    ): Journal {
        if ($lines === []) {
            throw new InvalidArgumentException('Jurnal harus memiliki minimal dua baris.');
        }

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += round((float) ($line['debit'] ?? 0), 2);
            $credit += round((float) ($line['credit'] ?? 0), 2);
        }

        if (round($debit, 2) !== round($credit, 2)) {
            throw new InvalidArgumentException('Jurnal tidak seimbang: total debit dan kredit harus sama.');
        }

        if ($debit <= 0) {
            throw new InvalidArgumentException('Jurnal harus memiliki nominal lebih dari nol.');
        }

        return DB::transaction(function () use ($date, $description, $reference, $lines, $actor): Journal {
            $journal = Journal::query()->create([
                'user_id' => $actor?->id,
                'journal_number' => $this->nextNumber($date),
                'journal_date' => $date,
                'description' => $description,
                'reference_number' => $reference,
                'status' => JournalStatus::Posted->value,
            ]);

            foreach ($lines as $line) {
                JournalDetail::query()->create([
                    'journal_id' => $journal->id,
                    'account_id' => (int) $line['account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'description' => $line['description'] ?? $description,
                ]);
            }

            return $journal;
        });
    }

    private function nextNumber(string $date): string
    {
        $prefix = 'JU-'.date('Ymd', strtotime($date)).'-';

        $last = Journal::withTrashed()
            ->where('journal_number', 'like', $prefix.'%')
            ->orderByDesc('journal_number')
            ->value('journal_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
