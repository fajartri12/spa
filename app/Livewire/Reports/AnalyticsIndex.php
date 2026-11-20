<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\AcademicYear;
use App\Models\Unit;
use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Analitik piutang, rasio penagihan per kelas, dan proyeksi penerimaan.
 */
#[Layout('layouts.app')]
#[Title('Analitik Finansial')]
class AnalyticsIndex extends Component
{
    public ?int $academic_year_id = null;

    public ?int $unit_id = null;

    public int $forecastMonths = 6;

    public function mount(): void
    {
        $this->authorize('reports.view');

        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $this->academic_year_id = $currentYear?->id;
    }

    public function render(AnalyticsService $service): View
    {
        $this->authorize('reports.view');

        $aging = $service->aging($this->academic_year_id, $this->unit_id);
        $collection = $service->collectionRate($this->academic_year_id, $this->unit_id);
        $forecast = $service->forecast($this->forecastMonths, $this->unit_id);

        $maxForecast = $forecast['values'] === [] ? 0.0 : max($forecast['values']);
        $maxForecast = max($maxForecast, $forecast['projection'], 1.0);

        return view('livewire.reports.analytics-index', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'units' => Unit::query()->orderBy('name')->pluck('name', 'id')->all(),
            'aging' => $aging,
            'collection' => $collection,
            'forecast' => $forecast,
            'maxForecast' => $maxForecast,
        ]);
    }
}
