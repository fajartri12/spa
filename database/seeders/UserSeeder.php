<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo dengan password default tidak boleh pernah dibuat di
        // produksi (AGENTS.md bagian 9).
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('UserSeeder dilewati: akun demo hanya untuk lingkungan local/testing.');

            return;
        }

        $accounts = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@email.com',
                'username' => 'superadmin',
                'phone' => '081234567001',
                'role' => RoleName::SuperAdministrator,
            ],
            [
                'name' => 'Administrator',
                'email' => 'admin@email.com',
                'username' => 'admin',
                'phone' => '081234567002',
                'role' => RoleName::Administrator,
            ],
            [
                'name' => 'Bendahara',
                'email' => 'bendahara@email.com',
                'username' => 'bendahara',
                'phone' => '081234567003',
                'role' => RoleName::Treasurer,
            ],
            [
                'name' => 'Petugas Pembayaran',
                'email' => 'petugas@email.com',
                'username' => 'petugas',
                'phone' => '081234567004',
                'role' => RoleName::PaymentOfficer,
            ],
            [
                'name' => 'Verifikator',
                'email' => 'verifikator@email.com',
                'username' => 'verifikator',
                'phone' => '081234567005',
                'role' => RoleName::Verifier,
            ],
            [
                'name' => 'Kepala Sekolah',
                'email' => 'kepsek@email.com',
                'username' => 'kepsek',
                'phone' => '081234567006',
                'role' => RoleName::Headmaster,
            ],
            [
                'name' => 'Operator PPDB',
                'email' => 'ppdb@email.com',
                'username' => 'operator-ppdb',
                'phone' => '081234567007',
                'role' => RoleName::AdmissionOperator,
            ],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'phone' => $account['phone'],
                    'status' => UserStatus::Active,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']->value]);
        }

        $this->command?->info(sprintf(
            'Users seeded: %d (password default: "password" untuk lingkungan lokal)',
            count($accounts),
        ));
    }
}
