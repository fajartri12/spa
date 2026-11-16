<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\AcademicYear;
use App\Services\FinancialReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Laporan Keuangan')]
class ReportsIndex extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $academicYearId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->academicYearId = AcademicYear::current()?->id;
    }

    public function render(FinancialReportService $reports): View
    {
        $this->authorize('reports.view');
        return view('livewire.reports.reports-index', [
            'report' => $reports->report($this->dateFrom, $this->dateTo, $this->academicYearId),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
        ]);
    }
}
