<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Enums\AccountType;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1101', 'name' => 'Kas Madrasah', 'type' => AccountType::Cash, 'description' => 'Uang tunai di ruang bendahara.'],
            ['code' => '1102', 'name' => 'Bank Jatim', 'type' => AccountType::Bank, 'description' => 'Rekening operasional pembayaran siswa.'],
            ['code' => '1103', 'name' => 'Bank BRI', 'type' => AccountType::Bank, 'description' => 'Rekening PPDB.'],
            ['code' => '4101', 'name' => 'Pendapatan SPP', 'type' => AccountType::Revenue],
            ['code' => '4102', 'name' => 'Pendapatan Uang Gedung', 'type' => AccountType::Revenue],
            ['code' => '5101', 'name' => 'Beban Operasional', 'type' => AccountType::Expense],
            ['code' => '5102', 'name' => 'Beban Honorarium Guru & Karyawan', 'type' => AccountType::Expense, 'description' => 'Honorarium, insentif komite, dan gaji staf madrasah.'],
        ];

        foreach ($accounts as $index => $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'opening_balance' => 0,
                    'description' => $account['description'] ?? null,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Accounts seeded.');
    }
}
