<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentCategoryType;
use App\Models\PaymentCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class PaymentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $man7 = Unit::query()->where('code', 'MAN7')->first();

        $categories = [
            [
                'code' => 'SPP',
                'name' => 'Sumbangan Pembinaan Pendidikan',
                'type' => PaymentCategoryType::Monthly,
                'default_amount' => 350000,
                'due_day' => 10,
                'description' => 'Iuran bulanan yang dibayarkan setiap awal bulan.',
                'sort_order' => 1,
            ],
            [
                'code' => 'UANG-GEDUNG',
                'name' => 'Uang Gedung',
                'type' => PaymentCategoryType::Once,
                'default_amount' => 2500000,
                'due_day' => 15,
                'description' => 'Dibayarkan sekali saat siswa masuk.',
                'sort_order' => 2,
            ],
            [
                'code' => 'SERAGAM',
                'name' => 'Seragam Madrasah',
                'type' => PaymentCategoryType::Once,
                'default_amount' => 750000,
                'due_day' => 20,
                'description' => 'Paket seragam harian, olahraga, dan batik.',
                'sort_order' => 3,
            ],
            [
                'code' => 'KEGIATAN',
                'name' => 'Iuran Kegiatan Tahunan',
                'type' => PaymentCategoryType::Annual,
                'default_amount' => 500000,
                'due_day' => 31,
                'description' => 'Mendukung kegiatan ekstrakurikuler dan study tour.',
                'sort_order' => 4,
            ],
            [
                'code' => 'UJIAN',
                'name' => 'Biaya Ujian',
                'type' => PaymentCategoryType::Once,
                'default_amount' => 250000,
                'due_day' => 25,
                'description' => 'Biaya penyelenggaraan ujian semester.',
                'sort_order' => 5,
            ],
            [
                'code' => 'PPDB',
                'name' => 'Pendaftaran PPDB',
                'type' => PaymentCategoryType::Once,
                'default_amount' => 150000,
                'due_day' => 30,
                'description' => 'Biaya formulir pendaftaran peserta didik baru.',
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            PaymentCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'unit_id' => $man7?->id,
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'default_amount' => $category['default_amount'],
                    'due_day' => $category['due_day'],
                    'description' => $category['description'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Payment categories seeded.');
    }
}
