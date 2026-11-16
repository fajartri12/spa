<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            AcademicYearSeeder::class,
            PaymentMethodSeeder::class,
            BankStatementProfileSeeder::class,
            AppSettingSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
