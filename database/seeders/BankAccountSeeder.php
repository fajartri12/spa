<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $man7 = Unit::query()->where('code', 'MAN7')->first();

        $rows = [
            [
                'bank_name' => 'Bank Jatim',
                'account_number' => '0011002233',
                'account_name' => 'KOMITE MAN 7 JOMBANG',
                'branch' => 'Cabang Jombang',
                'account_code' => '1102',
                'unit_id' => $man7?->id,
                'sort_order' => 1,
            ],
            [
                'bank_name' => 'Bank BRI',
                'account_number' => '009901887766',
                'account_name' => 'KOMITE MAN 7 JOMBANG',
                'branch' => 'Cabang Jombang',
                'account_code' => '1103',
                'unit_id' => $man7?->id,
                'sort_order' => 2,
            ],
            [
                'bank_name' => 'Bank Jatim',
                'account_number' => '0011002244',
                'account_name' => 'KOMITE MAN 7 JOMBANG - PPDB',
                'branch' => 'Cabang Jombang',
                'account_code' => '1102',
                'unit_id' => null,
                'sort_order' => 3,
            ],
        ];

        foreach ($rows as $row) {
            BankAccount::query()->updateOrCreate(
                [
                    'bank_name' => $row['bank_name'],
                    'account_number' => $row['account_number'],
                ],
                [
                    'account_name' => $row['account_name'],
                    'branch' => $row['branch'],
                    'account_id' => Account::query()->where('code', $row['account_code'])->value('id'),
                    'unit_id' => $row['unit_id'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Bank accounts seeded.');
    }
}
