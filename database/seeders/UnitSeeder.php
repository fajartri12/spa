<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'MAN7', 'name' => 'MAN 7 Jombang', 'level' => 'Madrasah Aliyah', 'sort_order' => 1],
            ['code' => 'MTsN', 'name' => 'MTsN Jombang', 'level' => 'Madrasah Tsanawiyah', 'sort_order' => 2],
            ['code' => 'MA', 'name' => 'MA Terpadu', 'level' => 'Madrasah Aliyah', 'sort_order' => 3],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'level' => $unit['level'],
                    'sort_order' => $unit['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Units seeded.');
    }
}
