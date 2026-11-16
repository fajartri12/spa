<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdmissionContent;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublicRegistrationController extends Controller
{
    public function portal(): View
    {
        $today = now()->toDateString();
        $period = AdmissionPeriod::query()
            ->where('status', 'open')
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today)
            ->latest('id')
            ->first();

        return view('ppdb.portal', [
            'period' => $period,
            'contents' => AdmissionContent::query()
                ->where('is_published', true)
                ->where(fn ($q) => $period === null
                    ? $q->whereNull('admission_period_id')
                    : $q->whereNull('admission_period_id')->orWhere('admission_period_id', $period->id))
                ->latest()
                ->take(6)
                ->get(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $period = $this->activePeriod();

        if ($period === null) {
            return redirect()->route('ppdb.register.closed');
        }

        return view('ppdb.register', [
            'period' => $period,
            'contents' => AdmissionContent::query()
                ->where('is_published', true)
                ->where(fn ($q) => $q->whereNull('admission_period_id')->orWhere('admission_period_id', $period->id))
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $period = $this->activePeriod();

        if ($period === null) {
            return redirect()->route('ppdb.register.closed');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['required', 'string', 'max:1000'],
            'parent_name' => ['required', 'string', 'max:120'],
            'parent_phone' => ['required', 'string', 'max:30'],
        ]);

        $data['admission_period_id'] = $period->id;
        $data['status'] = 'registered';
        $applicant = $this->createApplicant($data);

        return redirect()->to(
            URL::temporarySignedRoute('ppdb.register.success', now()->addHours(24), ['applicant' => $applicant->id])
        );
    }

    public function success(Applicant $applicant): View
    {
        return view('ppdb.success', compact('applicant'));
    }

    public function closed(): View
    {
        return view('ppdb.closed');
    }

    private function activePeriod(): ?AdmissionPeriod
    {
        $today = now()->toDateString();

        return AdmissionPeriod::query()
            ->where('status', 'open')
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createApplicant(array $data): Applicant
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return Applicant::query()->create($data + ['registration_number' => $this->registrationNumber()]);
            } catch (UniqueConstraintViolationException $exception) {
                if (! str_contains($exception->getMessage(), 'registration_number') || $attempt === 5) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat nomor pendaftaran unik.');
    }

    private function registrationNumber(): string
    {
        return 'PPDB-'.now()->format('Y').'-'.Str::upper(Str::random(6));
    }
}
