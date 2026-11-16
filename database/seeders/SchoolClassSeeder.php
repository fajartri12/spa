<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        $man7 = Unit::query()->where('code', 'MAN7')->first();

        if ($man7 === null) {
            $this->command?->warn('Unit MAN7 belum ada, seeder kelas dilewati.');

            return;
        }

        $year = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();

        if ($year === null) {
            $this->command?->warn('Tahun ajaran belum ada, seeder kelas dilewati.');

            return;
        }

        $classes = [
            ['name' => 'X-1', 'level' => 'X', 'homeroom_teacher' => 'Abdul Ghofur, S.Pd.I.', 'capacity' => 32],
            ['name' => 'X-2', 'level' => 'X', 'homeroom_teacher' => 'Lailatul Badriyah, S.Si.', 'capacity' => 32],
            ['name' => 'X-3', 'level' => 'X', 'homeroom_teacher' => 'Siti Aminah, S.Pd.', 'capacity' => 32],
            ['name' => 'XI-IPA-1', 'level' => 'XI', 'homeroom_teacher' => 'Muhammad Fauzan, S.Kom.', 'capacity' => 30],
            ['name' => 'XI-IPA-2', 'level' => 'XI', 'homeroom_teacher' => 'Rudi Hartono', 'capacity' => 30],
            ['name' => 'XI-IPS-1', 'level' => 'XI', 'homeroom_teacher' => 'Dewi Kartika Sari, S.Pd.', 'capacity' => 30],
            ['name' => 'XII-IPA-1', 'level' => 'XII', 'homeroom_teacher' => 'Hj. Nurul Hidayah, S.E.', 'capacity' => 28],
            ['name' => 'XII-IPS-1', 'level' => 'XII', 'homeroom_teacher' => 'Drs. H. Ahmad Shodiq, M.Pd.', 'capacity' => 28],
        ];

        foreach ($classes as $index => $class) {
            SchoolClass::query()->updateOrCreate(
                [
                    'unit_id' => $man7->id,
                    'academic_year_id' => $year->id,
                    'name' => $class['name'],
                ],
                [
                    'level' => $class['level'],
                    'homeroom_teacher' => $class['homeroom_teacher'],
                    'capacity' => $class['capacity'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info("School classes seeded untuk tahun ajaran {$year->name}.");
    }
}
