<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $man7 = Unit::query()->where('code', 'MAN7')->first();

        if ($man7 === null) {
            $this->command?->warn('Unit MAN7 belum ada, seeder siswa dilewati.');

            return;
        }

        $classes = SchoolClass::query()
            ->where('unit_id', $man7->id)
            ->orderBy('sort_order')
            ->get();

        if ($classes->isEmpty()) {
            $this->command?->warn('Kelas belum ada, seeder siswa dilewati.');

            return;
        }

        $names = [
            ['Ahmad Fauzi Ramadhan', Gender::Male, 'Jombang', '2008-03-14', 'Sutrisno'],
            ['Aisyah Nur Fadhilah', Gender::Female, 'Jombang', '2008-06-22', 'Imam Syafii'],
            ['Bagas Prasetyo', Gender::Male, 'Nganjuk', '2008-01-09', 'Bambang Prasetyo'],
            ['Citra Ayu Lestari', Gender::Female, 'Kediri', '2008-09-30', 'Agus Setiawan'],
            ['Dimas Aditya Nugraha', Gender::Male, 'Jombang', '2007-11-17', 'Nur Kholis'],
            ['Eka Putri Wulandari', Gender::Female, 'Mojokerto', '2007-05-03', 'Slamet Riyadi'],
            ['Fajar Maulana Ibrahim', Gender::Male, 'Jombang', '2007-08-25', 'Ibrahim Malik'],
            ['Gita Puspita Sari', Gender::Female, 'Jombang', '2007-02-11', 'Hariyanto'],
            ['Hafiz Rizki Pratama', Gender::Male, 'Surabaya', '2006-12-05', 'Rizal Pratama'],
            ['Indah Permata Sari', Gender::Female, 'Jombang', '2006-04-19', 'Yusuf Hidayat'],
            ['Joko Susilo Wibowo', Gender::Male, 'Jombang', '2006-07-28', 'Wibowo Santoso'],
            ['Kartika Dewi Anggraini', Gender::Female, 'Malang', '2006-10-02', 'Darmawan'],
            ['Lukman Hakim', Gender::Male, 'Jombang', '2008-04-16', 'Abdul Hakim'],
            ['Maulida Rahmawati', Gender::Female, 'Jombang', '2008-08-08', 'Rahmat Saleh'],
            ['Naufal Arifin', Gender::Male, 'Blitar', '2008-12-21', 'Arifin Ilham'],
            ['Oktavia Nur Aini', Gender::Female, 'Jombang', '2007-09-13', 'Nur Salim'],
            ['Putra Bayu Setiawan', Gender::Male, 'Jombang', '2007-03-27', 'Setiawan Budi'],
            ['Qonita Zahra Amelia', Gender::Female, 'Jombang', '2006-06-30', 'Amir Hamzah'],
            ['Rizky Kurniawan', Gender::Male, 'Jombang', '2006-01-24', 'Kurniawan Adi'],
            ['Salma Kamila Husna', Gender::Female, 'Jombang', '2006-11-15', 'Husnan Falah'],
        ];

        $entryYear = (int) $classes->first()->academicYear?->start_date?->format('Y')
            ?: (int) now()->year;

        foreach ($names as $index => [$name, $gender, $birthPlace, $birthDate, $parentName]) {
            $class = $classes[$index % $classes->count()];
            $sequence = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $nis = $entryYear . $sequence;

            Student::query()->updateOrCreate(
                ['nis' => $nis],
                [
                    'unit_id' => $man7->id,
                    'school_class_id' => $class->id,
                    'nisn' => '00' . str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'gender' => $gender,
                    'birth_place' => $birthPlace,
                    'birth_date' => $birthDate,
                    'religion' => 'Islam',
                    'address' => 'Jl. Pendidikan No. ' . ($index + 1) . ', Jombang',
                    'phone' => '08123456' . str_pad((string) (7000 + $index), 4, '0', STR_PAD_LEFT),
                    'parent_name' => $parentName,
                    'parent_phone' => '08134567' . str_pad((string) (8000 + $index), 4, '0', STR_PAD_LEFT),
                    'parent_relation' => 'Ayah',
                    'entry_year' => $entryYear,
                    'status' => StudentStatus::Active,
                ],
            );
        }

        $this->command?->info('Students seeded.');
    }
}
