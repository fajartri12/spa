<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\SppMatrixService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SppRecapPdfController extends Controller
{
    public function __invoke(Request $request, Student $student, SppMatrixService $matrixService): Response
    {
        $this->authorizeAccess($request, $student);

        $academicYearId = $request->filled('academic_year_id')
            ? (int) $request->input('academic_year_id')
            : null;

        $matrix = $matrixService->getMatrix($student, $academicYearId);
        $student->load(['schoolClass', 'unit']);

        $pdf = Pdf::loadView('reports.spp-recap-pdf', [
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'title' => 'Kartu Rekapitulasi SPP Tahunan',
            'student' => $student,
            'academicYear' => $matrix['academic_year']?->name ?? (string) now()->year,
            'months' => $matrix['months'],
            'summary' => $matrix['summary'],
            'headmasterName' => AppSetting::get('headmaster_name', '..........................'),
            'printedBy' => $request->user()?->name ?? 'Portal Wali',
        ])->setPaper('a4', 'portrait');

        $filename = 'rekap-spp-' . ($student->nis ?? $student->id) . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    private function authorizeAccess(Request $request, Student $student): void
    {
        // 1. Pengguna internal dengan hak akses bills.view / reports.export
        $user = $request->user();
        if ($user !== null && ($user->can('bills.view') || $user->can('reports.export'))) {
            return;
        }

        // 2. Sesi siswa portal
        $publicStudentId = session('public_student_id');
        if ($publicStudentId !== null && (int) $publicStudentId === $student->id) {
            return;
        }

        // 3. Sesi orang tua/wali portal
        $publicGuardianId = session('public_guardian_id');
        if ($publicGuardianId !== null) {
            $guardian = Guardian::query()->find($publicGuardianId);
            if ($guardian !== null && $guardian->activeStudents()->where('students.id', $student->id)->exists()) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki hak akses untuk mengunduh rekapitulasi SPP ini.');
    }
}
