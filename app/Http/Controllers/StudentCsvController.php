<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCsvController extends Controller
{
    /**
     * Urutan kolom CSV yang dipakai untuk import dan export.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'unit_code', 'nis', 'nisn', 'name', 'gender', 'birth_place', 'birth_date', 'religion',
        'address', 'phone', 'email', 'parent_name', 'parent_phone', 'parent_relation', 'entry_year', 'status',
    ];

    private const IMPORT_BATCH_SIZE = 200;

    public function export(): StreamedResponse
    {
        $query = Student::query()->with('unit')->ordered();
        $user = request()->user();

        if (! $user?->hasAnyRole(['super-administrator', 'administrator'])) {
            $unitId = $user === null
                ? null
                : StaffProfile::query()->where('user_id', $user->id)->value('unit_id');

            if ($unitId !== null) {
                $query->where('unit_id', $unitId);
            }
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, self::COLUMNS, escape: '');

            foreach ($query->lazy() as $student) {
                fputcsv($output, array_map(self::spreadsheetSafe(...), [
                    $student->unit?->code,
                    $student->nis,
                    $student->nisn,
                    $student->name,
                    $student->gender?->value,
                    $student->birth_place,
                    $student->birth_date?->toDateString(),
                    $student->religion,
                    $student->address,
                    $student->phone,
                    $student->email,
                    $student->parent_name,
                    $student->parent_phone,
                    $student->parent_relation,
                    $student->entry_year,
                    $student->status?->value,
                ]), escape: '');
            }

            fclose($output);
        }, 'siswa-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = $handle ? fgetcsv($handle, escape: '') : false;
        $header = array_map(static fn ($value) => trim(str_replace("\u{FEFF}", '', (string) $value)), $header ?: []);

        if ($header !== self::COLUMNS) {
            if ($handle) {
                fclose($handle);
            }

            return back()->withErrors(['file' => 'Header CSV harus berurutan: ' . implode(',', self::COLUMNS)]);
        }

        $units = Unit::query()->pluck('id', 'code');
        $created = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;
        $buffer = [];

        // Simpan per batch agar transaksi tidak menahan lock untuk file besar.
        $flush = function () use (&$buffer, &$created, &$errors): void {
            if ($buffer === []) {
                return;
            }

            DB::transaction(function () use (&$buffer, &$created, &$errors): void {
                foreach ($buffer as $item) {
                    $row = $item['__row'];
                    unset($item['__row']);

                    try {
                        Student::query()->create($item);
                        $created++;
                    } catch (UniqueConstraintViolationException) {
                        $errors[] = "Baris {$row}: NIS atau NISN sudah dipakai.";
                    }
                }

                $buffer = [];
            });
        };

        while ($handle && ($row = fgetcsv($handle, escape: '')) !== false) {
            $rowNumber++;

            if (array_filter($row, static fn ($value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $data = array_combine(self::COLUMNS, array_pad(array_slice($row, 0, count(self::COLUMNS)), count(self::COLUMNS), null));

            if (Student::query()->withTrashed()->where('nis', $data['nis'])->exists()) {
                $skipped++;

                continue;
            }

            $unitId = $units[$data['unit_code']] ?? null;

            if ($unitId === null) {
                $errors[] = "Baris {$rowNumber}: unit_code '{$data['unit_code']}' tidak dikenal.";

                continue;
            }

            $payload = [
                'unit_id' => $unitId,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?: null,
                'name' => $data['name'],
                'gender' => $data['gender'] ?: 'male',
                'birth_place' => $data['birth_place'] ?: null,
                'birth_date' => $data['birth_date'] ?: null,
                'religion' => $data['religion'] ?: null,
                'address' => $data['address'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'parent_name' => $data['parent_name'] ?: null,
                'parent_phone' => $data['parent_phone'] ?: null,
                'parent_relation' => $data['parent_relation'] ?: null,
                'entry_year' => $data['entry_year'] ? (int) $data['entry_year'] : null,
                'status' => $data['status'] ?: 'active',
                'portal_password' => Student::generatePortalPassword(),
                'portal_password_set_at' => now(),
            ];

            $validator = Validator::make($payload, [
                'nis' => ['required', 'string', 'max:30'],
                'nisn' => ['nullable', 'string', 'max:30', 'unique:students,nisn'],
                'name' => ['required', 'string', 'max:120'],
                'gender' => ['required', 'in:male,female'],
                'birth_date' => ['nullable', 'date'],
                'email' => ['nullable', 'email', 'max:150'],
                'entry_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
                'status' => ['required', 'in:active,graduated,transferred,dropped-out'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: " . implode(' ', $validator->errors()->all());

                continue;
            }

            // Setiap siswa baru mendapat password portal sendiri.
            $buffer[] = ['__row' => $rowNumber] + $payload;

            if (count($buffer) >= self::IMPORT_BATCH_SIZE) {
                $flush();
            }
        }

        $flush();

        if ($handle) {
            fclose($handle);
        }

        $summary = "Import selesai: {$created} dibuat, {$skipped} dilewati karena NIS duplikat, " . count($errors) . ' gagal.';

        return redirect()->route('students.index')
            ->with($errors === [] ? 'success' : 'warning', $summary)
            ->with('import_errors', $errors);
    }
}
