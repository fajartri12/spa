<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan lokal & demo.
 *
 * Dipisahkan dari seeder inti supaya lingkungan pengujian tetap deterministik
 * (AGENTS.MD bagian 11 & 15: tidak ada data palsu pada test).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountSeeder::class,
            PaymentCategorySeeder::class,
            BankAccountSeeder::class,
            StaffProfileSeeder::class,
            SchoolClassSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
