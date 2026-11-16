<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = (int) now()->year;
        $startYear = now()->month >= 7 ? $currentYear : $currentYear - 1;
        $activeName = sprintf('%d/%d', $startYear, $startYear + 1);

        // Previous academic year (non-active, for history & reporting)
        $prevStartYear = $startYear - 1;
        $prevName = sprintf('%d/%d', $prevStartYear, $prevStartYear + 1);
        AcademicYear::query()->updateOrCreate(
            ['name' => $prevName],
            [
                'start_date' => sprintf('%d-07-01', $prevStartYear),
                'end_date' => sprintf('%d-06-30', $prevStartYear + 1),
                'is_active' => false,
            ],
        );

        // Active academic year
        AcademicYear::query()->updateOrCreate(
            ['name' => $activeName],
            [
                'start_date' => sprintf('%d-07-01', $startYear),
                'end_date' => sprintf('%d-06-30', $startYear + 1),
                'is_active' => true,
            ],
        );

        // Upcoming academic year (non-active, for PPDB / advance billing)
        $nextStartYear = $startYear + 1;
        $nextName = sprintf('%d/%d', $nextStartYear, $nextStartYear + 1);
        AcademicYear::query()->updateOrCreate(
            ['name' => $nextName],
            [
                'start_date' => sprintf('%d-07-01', $nextStartYear),
                'end_date' => sprintf('%d-06-30', $nextStartYear + 1),
                'is_active' => false,
            ],
        );

        AcademicYear::query()->where('name', '!=', $activeName)->update(['is_active' => false]);

        $this->command?->info("Academic year seeded: {$activeName}");
    }
}
