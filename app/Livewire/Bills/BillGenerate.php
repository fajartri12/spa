<?php

declare(strict_types=1);

namespace App\Livewire\Bills;

use App\Actions\GenerateIndividualBillAction;
use App\Actions\GenerateMassBillAction;
use App\Enums\BillStatus;
use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Generate Tagihan')]
class BillGenerate extends Component
{
    /**
     * massal  : satu kategori untuk semua siswa aktif pada unit terpilih.
     * bulanan : sama seperti massal, tetapi periode wajib dan dapat diulang
     *           untuk beberapa bulan sekaligus.
     * individu: satu siswa, nominal dan potongan bebas.
     */
    #[Url(as: 'mode', history: true)]
    public string $mode = 'massal';

    public ?int $academic_year_id = null;

    public ?int $payment_category_id = null;

    public ?int $unit_id = null;

    public ?int $class_id = null;

    public ?int $student_id = null;

    public string $period = '';

    public string $period_end = '';

    public string $due_date = '';

    public string $amount = '';

    public string $discount_amount = '0';

    public string $title = '';

    public string $description = '';

    /** @var array<int, array{description: string, amount: string}> */
    public array $items = [];

    public bool $confirmed = false;

    public function mount(): void
    {
        $this->academic_year_id = AcademicYear::current()?->id;
        $this->period = now()->format('Y-m');
        $this->period_end = now()->format('Y-m');
        $this->due_date = now()->addMonth()->startOfMonth()->format('Y-m-d');
    }

    public function updatedMode(): void
    {
        $this->resetValidation();
        $this->confirmed = false;
    }

    public function updatedPaymentCategoryId(): void
    {
        $this->confirmed = false;

        $category = $this->payment_category_id ? PaymentCategory::query()->find($this->payment_category_id) : null;

        if ($category !== null && $this->mode === 'individu') {
            $this->amount = (string) (float) $category->default_amount;
        }
    }

    public function updatedStudentId(): void
    {
        $this->confirmed = false;
    }

    public function addItem(): void
    {
        $max = max(1, (int) AppSetting::get('max_bill_items', 6));

        if (count($this->items) >= $max) {
            $this->dispatch('toast', type: 'error', message: "Maksimal {$max} item tagihan.");

            return;
        }

        $this->items[] = ['description' => '', 'amount' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Samakan nominal tagihan dengan total item agar validasi tidak membingungkan.
     */
    public function syncAmountFromItems(): void
    {
        $total = 0.0;

        foreach ($this->items as $item) {
            $total += (float) Formatter::parseRupiah($item['amount'] ?? 0);
        }

        if ($total > 0) {
            $this->amount = (string) round($total, 2);
        }
    }

    public function generate(
        GenerateMassBillAction $massAction,
        GenerateIndividualBillAction $individualAction,
    ): void {
        $this->authorize('bills.generate');

        $this->mode === 'individu'
            ? $this->generateIndividual($individualAction)
            : $this->generateBulk($massAction);
    }

    /**
     * Tagihan massal/bulanan memakai aturan yang sama: satu baris per siswa
     * aktif per kategori per periode, duplikat dilewati.
     */
    private function generateBulk(GenerateMassBillAction $action): void
    {
        $rules = [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'payment_category_id' => ['required', 'exists:payment_categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'confirmed' => ['accepted'],
        ];

        if ($this->mode === 'bulanan') {
            $rules['period_end'] = ['required', 'date_format:Y-m', 'after_or_equal:period'];
        }

        $data = $this->validate($rules, ['confirmed.accepted' => 'Konfirmasi data sebelum membuat tagihan.']);

        $category = PaymentCategory::query()->findOrFail($data['payment_category_id']);
        $periods = $this->mode === 'bulanan'
            ? $this->monthRange($data['period'], $data['period_end'])
            : [$data['period']];

        $created = 0;
        $skipped = 0;

        try {
            foreach ($periods as $period) {
                try {
                    $bills = $action->execute([
                        'payment_category_id' => $category->id,
                        'academic_year_id' => $data['academic_year_id'],
                        'period' => $period,
                        'amount' => $category->default_amount,
                        'due_date' => $data['due_date'],
                        'unit_id' => $data['unit_id'] ?? null,
                        'class_id' => $data['class_id'] ?? null,
                        'title' => $category->name.' - '.$period,
                    ]);

                    $created += $bills->count();
                } catch (InvalidArgumentException) {
                    // Seluruh siswa pada periode ini sudah punya tagihan; lanjut
                    // ke periode berikutnya alih-alih menggagalkan semuanya.
                    $skipped++;
                }
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('confirmed', $exception->getMessage());

            return;
        }

        if ($created === 0) {
            $this->addError('confirmed', 'Tidak ada tagihan baru: seluruh siswa pada periode terpilih sudah memiliki tagihan kategori ini.');

            return;
        }

        $message = "{$created} tagihan siswa berhasil dibuat.";

        if ($skipped > 0) {
            $message .= " {$skipped} periode dilewati karena sudah ada.";
        }

        session()->flash('success', $message);
        $this->redirectRoute('bills.index');
    }

    private function generateIndividual(GenerateIndividualBillAction $action): void
    {
        $this->amount = Formatter::parseRupiah($this->amount);
        $this->discount_amount = Formatter::parseRupiah($this->discount_amount);

        $data = $this->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'payment_category_id' => ['required', 'exists:payment_categories,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'discount_amount' => ['required', 'numeric', 'min:0', 'lte:amount'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:150'],
            'items.*.amount' => ['nullable'],
            'confirmed' => ['accepted'],
        ], [
            'confirmed.accepted' => 'Konfirmasi data sebelum membuat tagihan.',
            'discount_amount.lte' => 'Potongan tidak boleh melebihi nominal tagihan.',
        ]);

        $items = collect($data['items'] ?? [])
            ->map(fn (array $item) => [
                'description' => trim((string) ($item['description'] ?? '')),
                'amount' => (float) Formatter::parseRupiah($item['amount'] ?? 0),
            ])
            ->filter(fn (array $item) => $item['description'] !== '' || $item['amount'] > 0)
            ->values()
            ->all();

        try {
            $action->execute([
                'student_id' => $data['student_id'],
                'payment_category_id' => $data['payment_category_id'],
                'academic_year_id' => $data['academic_year_id'],
                'period' => $data['period'],
                'due_date' => $data['due_date'],
                'amount' => $items === [] ? $data['amount'] : round((float) array_sum(array_column($items, 'amount')), 2),
                'discount_amount' => $data['discount_amount'],
                'title' => $data['title'] ?: null,
                'description' => $data['description'] ?: null,
                'items' => $items,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('confirmed', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Tagihan siswa berhasil dibuat.');
        $this->redirectRoute('bills.index');
    }

    /**
     * Daftar periode bulanan inklusif antara dua nilai Y-m.
     *
     * @return array<int, string>
     */
    private function monthRange(string $start, string $end): array
    {
        $cursor = \Illuminate\Support\Carbon::createFromFormat('Y-m', $start)->startOfMonth();
        $last = \Illuminate\Support\Carbon::createFromFormat('Y-m', $end)->startOfMonth();
        $periods = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $periods[] = $cursor->format('Y-m');
            $cursor = $cursor->copy()->addMonth();
        }

        return $periods;
    }

    public function render(): View
    {
        $this->authorize('bills.generate');

        $category = $this->payment_category_id ? PaymentCategory::query()->find($this->payment_category_id) : null;

        return view('livewire.bills.bill-generate', [
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id'),
            'categories' => PaymentCategory::query()->active()->ordered()->pluck('name', 'id'),
            'units' => Unit::query()->ordered()->pluck('name', 'id'),
            'classes' => SchoolClass::query()
                ->when($this->unit_id, fn (Builder $query, $unitId) => $query->where('unit_id', $unitId))
                ->when($this->academic_year_id, fn (Builder $query, $yearId) => $query->where('academic_year_id', $yearId))
                ->orderBy('name')->pluck('name', 'id'),
            'students' => $this->studentOptions(),
            'category' => $category,
            'maxBillItems' => (int) AppSetting::get('max_bill_items', 6),
            'existingPeriods' => $this->existingPeriods($category),
            'previewStudentCount' => $this->previewStudentCount(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function studentOptions(): array
    {
        if ($this->mode !== 'individu') {
            return [];
        }

        return Student::query()
            ->active()
            ->when($this->unit_id, fn (Builder $query, $unitId) => $query->where('unit_id', $unitId))
            ->when($this->class_id, fn (Builder $query, $classId) => $query->where('school_class_id', $classId))
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Student $student) => [$student->id => $student->nis.' — '.$student->name])
            ->all();
    }

    private function previewStudentCount(): int
    {
        if ($this->mode === 'individu' || $this->student_id !== null) {
            return 1;
        }

        return Student::query()
            ->active()
            ->when($this->unit_id, fn (Builder $query, $unitId) => $query->where('unit_id', $unitId))
            ->when($this->class_id, fn (Builder $query, $classId) => $query->where('school_class_id', $classId))
            ->count();
    }

    /**
     * Periode yang sudah memiliki tagihan untuk kategori terpilih.
     *
     * @return array<int, string>
     */
    private function existingPeriods(?PaymentCategory $category): array
    {
        if ($category === null) {
            return [];
        }

        return DB::table('bills')
            ->where('payment_category_id', $category->id)
            ->when($this->academic_year_id, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->when($this->unit_id, fn ($query, $unitId) => $query->whereExists(fn ($sub) => $sub
                ->select(DB::raw(1))
                ->from('student_bills')
                ->join('students', 'students.id', '=', 'student_bills.student_id')
                ->whereColumn('student_bills.bill_id', 'bills.id')
                ->where('students.unit_id', $unitId)))
            ->distinct()
            ->orderBy('period')
            ->pluck('period')
            ->filter()
            ->all();
    }
}
