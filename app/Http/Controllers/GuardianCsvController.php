<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor data orang tua/wali ke CSV.
 *
 * Password tidak pernah ikut diekspor karena hanya berupa hash dan bukan data
 * yang boleh dibagikan lewat berkas.
 */
class GuardianCsvController extends Controller
{
    /**
     * @var list<string>
     */
    private const COLUMNS = ['name', 'relation', 'phone', 'email', 'occupation', 'address', 'students', 'status'];

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('guardians.export'), 403);

        $unit = $request->filled('unit') ? (int) $request->string('unit')->toString() : null;
        $status = $request->filled('status') ? (string) $request->string('status') : null;

        $query = Guardian::query()
            ->with(['students:id,name,nis'])
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($unit !== null, fn (Builder $q) => $q->whereHas(
                'students',
                fn (Builder $student) => $student->where('unit_id', $unit),
            ))
            ->orderBy('name');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, self::COLUMNS, escape: '');

            foreach ($query->lazy() as $guardian) {
                fputcsv($output, Csv::safeAll([
                    $guardian->name,
                    $guardian->relation?->label(),
                    $guardian->phone,
                    $guardian->email,
                    $guardian->occupation,
                    $guardian->address,
                    $guardian->students->pluck('name')->implode(', '),
                    $guardian->is_active ? 'Aktif' : 'Nonaktif',
                ]), escape: '');
            }

            fclose($output);
        }, 'orang-tua-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
