<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Tunai'],
            ['code' => 'transfer', 'name' => 'Transfer Bank'],
            ['code' => 'qris', 'name' => 'QRIS'],
            ['code' => 'other', 'name' => 'Lainnya'],
        ];

        foreach ($methods as $index => $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                ['name' => $method['name'], 'is_active' => true, 'sort_order' => $index],
            );
        }

        $this->command?->info('Payment methods seeded.');
    }
}
