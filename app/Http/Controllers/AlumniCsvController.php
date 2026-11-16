<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor data alumni ke CSV untuk pelaporan ke yayasan atau instansi.
 */
class AlumniCsvController extends Controller
{
    /**
     * Urutan kolom CSV yang dipakai saat ekspor.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'nis', 'nisn', 'name', 'gender', 'unit', 'class_name', 'major', 'graduation_year',
        'status', 'phone', 'email', 'address', 'occupation', 'workplace', 'higher_education', 'notes',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('alumni.export'), 403);

        $unit = $request->filled('unit') ? (int) $request->string('unit')->toString() : null;
        $year = $request->filled('tahun-lulus') ? (int) $request->string('tahun-lulus')->toString() : null;
        $status = $request->filled('status') ? (string) $request->string('status') : null;

        $query = Alumni::query()
            ->with('unit:id,name')
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit))
            ->when($year !== null, fn (Builder $q) => $q->where('graduation_year', $year))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->ordered();

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, self::COLUMNS, escape: '');

            foreach ($query->lazy() as $alumni) {
                fputcsv($output, Csv::safeAll([
                    $alumni->nis,
                    $alumni->nisn,
                    $alumni->name,
                    $alumni->gender?->label(),
                    $alumni->unit?->name,
                    $alumni->class_name,
                    $alumni->major,
                    $alumni->graduation_year,
                    $alumni->status?->label(),
                    $alumni->phone,
                    $alumni->email,
                    $alumni->address,
                    $alumni->occupation,
                    $alumni->workplace,
                    $alumni->higher_education,
                    $alumni->notes,
                ]), escape: '');
            }

            fclose($output);
        }, 'alumni-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
