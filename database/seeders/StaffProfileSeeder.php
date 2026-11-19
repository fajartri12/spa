<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\StaffPosition;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffProfileSeeder extends Seeder
{
    public function run(): void
    {
        $man7 = Unit::query()->where('code', 'MAN7')->first();

        $staff = [
            [
                'name' => 'Drs. H. Ahmad Shodiq, M.Pd.',
                'nip' => '197005121995031001',
                'gender' => Gender::Male,
                'position' => StaffPosition::Headmaster,
                'email' => 'kepsek@email.com',
                'phone' => '081234567006',
                'link_user' => true,
            ],
            [
                'name' => 'Hj. Nurul Hidayah, S.E.',
                'nip' => '198203152008012004',
                'gender' => Gender::Female,
                'position' => StaffPosition::Treasurer,
                'email' => 'bendahara@email.com',
                'phone' => '081234567003',
                'link_user' => true,
            ],
            [
                'name' => 'Muhammad Fauzan, S.Kom.',
                'nip' => '199004102015031002',
                'gender' => Gender::Male,
                'position' => StaffPosition::PaymentOfficer,
                'email' => 'petugas@email.com',
                'phone' => '081234567004',
                'link_user' => true,
            ],
            [
                'name' => 'Siti Aminah, S.Pd.',
                'nip' => '198809212012012003',
                'gender' => Gender::Female,
                'position' => StaffPosition::Verifier,
                'email' => 'verifikator@email.com',
                'phone' => '081234567005',
                'link_user' => true,
            ],
            [
                'name' => 'Abdul Ghofur, S.Pd.I.',
                'nip' => '198507172011011005',
                'gender' => Gender::Male,
                'position' => StaffPosition::Teacher,
                'email' => 'abdul.ghofur@man7jombang.sch.id',
                'phone' => '081234567010',
                'link_user' => false,
            ],
            [
                'name' => 'Lailatul Badriyah, S.Si.',
                'nip' => '199211052018012006',
                'gender' => Gender::Female,
                'position' => StaffPosition::Teacher,
                'email' => 'lailatul.badriyah@man7jombang.sch.id',
                'phone' => '081234567011',
                'link_user' => false,
            ],
            [
                'name' => 'Rudi Hartono',
                'nip' => '199507222020011007',
                'gender' => Gender::Male,
                'position' => StaffPosition::AdministrativeStaff,
                'email' => 'rudi.hartono@man7jombang.sch.id',
                'phone' => '081234567012',
                'link_user' => false,
            ],
            [
                'name' => 'Dewi Kartika Sari, S.Pd.',
                'nip' => '199603182021012008',
                'gender' => Gender::Female,
                'position' => StaffPosition::AdmissionOperator,
                'email' => 'ppdb@email.com',
                'phone' => '081234567007',
                'link_user' => true,
            ],
        ];

        foreach ($staff as $row) {
            StaffProfile::query()->updateOrCreate(
                ['nip' => $row['nip']],
                [
                    'user_id' => $row['link_user']
                        ? User::query()->where('email', $row['email'])->value('id')
                        : null,
                    'unit_id' => $man7?->id,
                    'name' => $row['name'],
                    'gender' => $row['gender'],
                    'position' => $row['position'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Staff profiles seeded.');
    }
}
