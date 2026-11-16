<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClearanceStatus;
use App\Models\AppSetting;
use App\Models\Student;
use App\Support\Formatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak Surat Keterangan Lulus (SKL) sebagai PDF.
 *
 * SKL hanya dapat dicetak bila status bebas tanggungan siswa mengizinkan.
 * Pemeriksaan dilakukan di sini juga (bukan hanya di tombol) agar tautan
 * langsung tidak dapat dipakai melewati aturan.
 */
class SklPdfController extends Controller
{
    public function __invoke(Request $request, Student $student): Response
    {
        abort_unless($request->user()?->can('clearances.issue'), 403);

        $clearance = $student->clearance;

        abort_if($clearance === null, 404, 'Bebas tanggungan siswa ini belum diperiksa.');
        abort_unless(
            $clearance->status->allowsIssuance(),
            403,
            'SKL tidak dapat dicetak: siswa masih memiliki tanggungan.',
        );
        abort_if($clearance->skl_number === null, 404, 'Nomor SKL belum diterbitkan.');

        $student->load(['unit', 'schoolClass.academicYear', 'guardians']);

        $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();

        $pdf = Pdf::loadView('clearances.skl', [
            'student' => $student,
            'clearance' => $clearance,
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'headmasterName' => AppSetting::get('headmaster_name', '..........................'),
            'headmasterTitle' => 'Kepala Madrasah',
            'issuedDate' => ($clearance->skl_issued_at ?? now())->timezone('Asia/Jakarta'),
            'birthDate' => $student->birth_date,
            'parentName' => $guardian?->name ?? $student->parent_name,
            'hasOutstanding' => $clearance->status === ClearanceStatus::Overridden,
        ])->setPaper('a4');

        return $pdf->download('skl-'.$student->nis.'.pdf');
    }
}
