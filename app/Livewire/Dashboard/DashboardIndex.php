<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\AcademicYear;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Beranda')]
class DashboardIndex extends Component
{
    #[Url(as: 'tahun-ajaran', history: true)]
    public ?int $academicYearId = null;

    #[Url(as: 'unit', history: true)]
    public ?int $unitId = null;

    #[Url(as: 'periode', history: true)]
    public string $period = 'year';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->academicYearId ??= AcademicYear::current()?->id;

        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
    }

    public function updatedPeriod(): void
    {
        if ($this->period !== 'custom') {
            $this->dateFrom = '';
            $this->dateTo = '';
        } else {
            $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
            $this->dateTo = Carbon::now()->toDateString();
        }
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
    }

    /**
     * Rentang tanggal efektif berdasarkan pilihan periode.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateRange(): array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'custom' => [
                $this->dateFrom !== '' ? Carbon::parse($this->dateFrom) : null,
                $this->dateTo !== '' ? Carbon::parse($this->dateTo) : null,
            ],
            default => [null, null],
        };
    }

    public function resetFilters(): void
    {
        $this->academicYearId = AcademicYear::current()?->id;
        $this->unitId = null;
        $this->period = 'year';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function render(DashboardService $dashboard): View
    {
        $yearId = $this->academicYearId;
        $unitId = $this->unitId;
        [$from, $to] = $this->dateRange();

        return view('livewire.dashboard.dashboard-index', [
            'statistics' => $dashboard->statistics($yearId, $unitId, $from, $to),
            'monthly' => $dashboard->monthlyPayments($yearId, $unitId, $from, $to),
            'billingStatus' => $dashboard->billingStatus($yearId, $unitId),
            'byCategory' => $dashboard->paymentsByCategory($yearId, 6, $unitId, $from, $to),
            'recentPayments' => $dashboard->recentPayments(8, $unitId, $from, $to),
            'pendingPayments' => $dashboard->pendingPayments(8, $unitId),
            'range' => $dashboard->resolveRange($yearId, $from, $to),
            'units' => $dashboard->units(),
            'academicYears' => $dashboard->academicYears(),
            'activeYear' => AcademicYear::current(),
        ]);
    }
}
