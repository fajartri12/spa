<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BankStatementProfile;
use Illuminate\Database\Seeder;

/**
 * Profil pembacaan mutasi rekening untuk bank yang dipakai madrasah.
 *
 * Nama kolom mengikuti hasil ekspor internet banking masing-masing bank. Bila
 * bank mengubah format ekspor, admin dapat menimpa nama kolom dari antarmuka
 * import tanpa mengubah kode.
 */
class BankStatementProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'code' => 'bsi',
                'name' => 'BSI — Rekening Koran',
                'bank_name' => 'Bank Syariah Indonesia',
                'header_row' => 0,
                'delimiter' => ',',
                'column_date' => 'Tanggal',
                'column_description' => 'Keterangan',
                'column_reference' => 'No. Referensi',
                'column_debit' => 'Debit',
                'column_credit' => 'Kredit',
                'column_amount' => null,
                'column_balance' => 'Saldo',
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
            ],
            [
                'code' => 'bri',
                'name' => 'BRI — Rekening Koran',
                'bank_name' => 'Bank Rakyat Indonesia',
                'header_row' => 0,
                'delimiter' => ',',
                'column_date' => 'Tanggal',
                'column_description' => 'Keterangan',
                'column_reference' => 'No. Referensi',
                'column_debit' => 'Debet',
                'column_credit' => 'Kredit',
                'column_amount' => null,
                'column_balance' => 'Saldo',
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
            ],
            [
                'code' => 'bank-jatim',
                'name' => 'Bank Jatim — Rekening Koran',
                'bank_name' => 'Bank Jatim',
                'header_row' => 0,
                'delimiter' => ';',
                'column_date' => 'Tanggal',
                'column_description' => 'Uraian',
                'column_reference' => 'Referensi',
                'column_debit' => 'Debet',
                'column_credit' => 'Kredit',
                'column_amount' => null,
                'column_balance' => 'Saldo',
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
            ],
        ];

        foreach ($profiles as $profile) {
            BankStatementProfile::query()->updateOrCreate(
                ['code' => $profile['code']],
                [...$profile, 'is_active' => true],
            );
        }

        $this->command?->info(sprintf('Bank statement profiles seeded: %d', count($profiles)));
    }
}
