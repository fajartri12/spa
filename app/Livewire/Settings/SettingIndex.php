<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Umum')]
class SettingIndex extends Component
{
    public bool $studentSavingsEnabled = true;

    public string $app_name = '';

    public string $app_subtitle = '';

    public string $school_name = '';

    public string $school_address = '';

    public string $school_phone = '';

    public string $school_email = '';

    public string $headmaster_name = '';

    public string $treasurer_name = '';

    public string $currency_symbol = 'Rp';

    public string $date_format = 'd F Y';

    public int $rows_per_page = 15;

    public string $footer_text = '';

    public string $payment_prefix = 'TRX';

    public string $bill_prefix = 'TGH';

    public function mount(): void
    {
        $this->authorize('settings.view');

        $this->app_name = (string) AppSetting::get('app_name', 'KOMITE MAN 7 JOMBANG');
        $this->app_subtitle = (string) AppSetting::get('app_subtitle', 'APLIKASI PENGELOLAAN PEMBAYARAN TAGIHAN');
        $this->school_name = (string) AppSetting::get('school_name', 'MAN 7 JOMBANG');
        $this->school_address = (string) AppSetting::get('school_address', '');
        $this->school_phone = (string) AppSetting::get('school_phone', '');
        $this->school_email = (string) AppSetting::get('school_email', '');
        $this->headmaster_name = (string) AppSetting::get('headmaster_name', '');
        $this->treasurer_name = (string) AppSetting::get('treasurer_name', '');
        $this->currency_symbol = (string) AppSetting::get('currency_symbol', 'Rp');
        $this->date_format = (string) AppSetting::get('date_format', 'd F Y');
        $this->rows_per_page = (int) AppSetting::get('rows_per_page', 15);
        $this->footer_text = (string) AppSetting::get('footer_text', '© KOMITE MAN 7 JOMBANG');
        $this->payment_prefix = (string) AppSetting::get('payment_prefix', 'TRX');
        $this->bill_prefix = (string) AppSetting::get('bill_prefix', 'TGH');
        $this->studentSavingsEnabled = (string) AppSetting::get('feature_student_savings_enabled', '1') === '1';
    }

    public function updatedStudentSavingsEnabled(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');
        AppSetting::set('feature_student_savings_enabled', $this->studentSavingsEnabled ? '1' : '0');
        $logger->log('features.updated', 'Mengubah status fitur Tabungan Siswa');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan fitur berhasil diperbarui.');
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $data = $this->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'app_subtitle' => ['nullable', 'string', 'max:200'],
            'school_name' => ['required', 'string', 'max:120'],
            'school_address' => ['nullable', 'string', 'max:500'],
            'school_phone' => ['nullable', 'string', 'max:30'],
            'school_email' => ['nullable', 'email', 'max:120'],
            'headmaster_name' => ['nullable', 'string', 'max:120'],
            'treasurer_name' => ['nullable', 'string', 'max:120'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'date_format' => ['required', 'string', 'max:30'],
            'rows_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'footer_text' => ['required', 'string', 'max:200'],
            'payment_prefix' => ['required', 'string', 'max:10', 'alpha_dash'],
            'bill_prefix' => ['required', 'string', 'max:10', 'alpha_dash'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::set($key, $value);
        }

        $logger->log('settings.updated', 'Memperbarui pengaturan umum aplikasi');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan berhasil disimpan.');
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        return view('livewire.settings.setting-index');
    }
}
