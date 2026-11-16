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
#[Title('Pengaturan Sistem')]
class SystemIndex extends Component
{
    // Application Info
    public string $app_name = '';
    public string $app_url = '';

    // Bills & Payments
    public float $service_fee = 0;
    public int $max_bill_items = 6;
    public int $bill_due_day = 10;
    public float $min_category_amount = 0;
    public float $max_category_amount = 10000000;
    public float $min_payment_amount = 1000;
    public float $min_installment_amount = 1000;
    public float $min_savings_deposit = 1000;
    public float $max_savings_deposit = 1000000;
    public ?int $savings_cash_account_id = null;
    public ?int $ppdb_cash_account_id = null;
    public ?int $ppdb_revenue_account_id = null;

    public function mount(): void
    {
        $this->authorize('settings.view');

        $this->app_name = (string) AppSetting::get('app_name', 'KOMITE MAN 7 JOMBANG');
        $this->app_url = (string) AppSetting::get('app_url', 'http://127.0.0.1:8123');

        $this->service_fee = (float) AppSetting::get('service_fee', 0);
        $this->max_bill_items = (int) AppSetting::get('max_bill_items', 6);
        $this->bill_due_day = (int) AppSetting::get('bill_due_day', 10);
        $this->min_category_amount = (float) AppSetting::get('min_category_amount', 0);
        $this->max_category_amount = (float) AppSetting::get('max_category_amount', 10000000);
        $this->min_payment_amount = (float) AppSetting::get('min_payment_amount', 1000);
        $this->min_installment_amount = (float) AppSetting::get('min_installment_amount', 1000);
        $this->min_savings_deposit = (float) AppSetting::get('min_savings_deposit', 1000);
        $this->max_savings_deposit = (float) AppSetting::get('max_savings_deposit', 1000000);
        $this->savings_cash_account_id = AppSetting::get('savings_cash_account_id') !== null
            ? (int) AppSetting::get('savings_cash_account_id')
            : null;
        $this->ppdb_cash_account_id = AppSetting::get('ppdb_cash_account_id') !== null
            ? (int) AppSetting::get('ppdb_cash_account_id')
            : null;
        $this->ppdb_revenue_account_id = AppSetting::get('ppdb_revenue_account_id') !== null
            ? (int) AppSetting::get('ppdb_revenue_account_id')
            : null;
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $data = $this->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url'],
            'service_fee' => ['required', 'numeric', 'min:0'],
            'max_bill_items' => ['required', 'integer', 'min:1', 'max:6'],
            'bill_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'min_category_amount' => ['required', 'numeric', 'min:0'],
            'max_category_amount' => ['required', 'numeric', 'gte:min_category_amount'],
            'min_payment_amount' => ['required', 'numeric', 'min:0'],
            'min_installment_amount' => ['required', 'numeric', 'min:0'],
            'min_savings_deposit' => ['required', 'numeric', 'min:0'],
            'max_savings_deposit' => ['required', 'numeric', 'gte:min_savings_deposit'],
            'savings_cash_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'ppdb_cash_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'ppdb_revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::set($key, $value);
        }

        $logger->log('settings.updated', 'Memperbarui pengaturan sistem');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan sistem berhasil disimpan.');
    }

    public function render(): View
    {
        return view('livewire.settings.system-index', [
            'currencySymbol' => (string) AppSetting::get('currency_symbol', 'Rp'),
            'cashAccounts' => \App\Models\Account::query()
                ->active()
                ->whereIn('type', [\App\Enums\AccountType::Cash->value, \App\Enums\AccountType::Bank->value])
                ->ordered()
                ->get()
                ->mapWithKeys(static fn (\App\Models\Account $account): array => [$account->id => $account->code.' - '.$account->name])
                ->all(),
            'ppdbRevenueAccounts' => \App\Models\Account::query()
                ->active()
                ->where('type', \App\Enums\AccountType::Revenue->value)
                ->ordered()
                ->get()
                ->mapWithKeys(static fn (\App\Models\Account $account): array => [$account->id => $account->code.' - '.$account->name])
                ->all(),
        ]);
    }
}
