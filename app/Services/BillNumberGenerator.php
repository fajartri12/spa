<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

final class BillNumberGenerator
{
    public function generate(?string $date = null): string
    {
        $date = $date ?? now()->format('Ymd');
        $prefix = $this->prefix().'-'.$date.'-';

        return DB::transaction(function () use ($prefix): string {
            $last = DB::table('bills')->where('bill_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('bill_number')->value('bill_number');
            $sequence = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Awalan nomor tagihan dari pengaturan aplikasi.
     */
    private function prefix(): string
    {
        $prefix = trim((string) AppSetting::get('bill_prefix', 'INV'));

        return $prefix === '' ? 'INV' : $prefix;
    }
}
