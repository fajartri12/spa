<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionContent;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Akses Portal PPDB')]
class PortalAccess extends Component
{
    public function render(): View
    {
        $this->authorize('admissions.view');

        $today = now()->toDateString();

        return view('livewire.admissions.portal-access', [
            'registerUrl' => route('ppdb.register'),
            'period' => AdmissionPeriod::query()
                ->where('status', 'open')
                ->whereDate('starts_at', '<=', $today)
                ->whereDate('ends_at', '>=', $today)
                ->latest('id')
                ->first(),
            'publishedContents' => AdmissionContent::query()
                ->where('is_published', true)
                ->latest()
                ->take(5)
                ->get(),
            'totalApplicants' => Applicant::query()->count(),
        ]);
    }
}
