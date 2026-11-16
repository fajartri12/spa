<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\GatewayProvider;
use App\Models\AppSetting;
use App\Services\ActivityLogger;
use App\Services\Gateway\GatewayManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan penyedia pembayaran non-tunai.
 *
 * Kredensial gateway tidak pernah disimpan di database maupun ditampilkan pada
 * halaman ini. Bagian ini hanya melaporkan status keterisian kredensial dari
 * environment dan mengelola pengaturan operasional yang aman diubah pengguna.
 */
#[Layout('layouts.app')]
#[Title('Pengaturan Pembayaran')]
class GatewayIndex extends Component
{
    public string $payment_gateway_default = '';

    public int $payment_gateway_expiry_hours = 24;

    public bool $reconciliation_unique_amount_enabled = false;

    public int $reconciliation_date_tolerance_days = 7;

    public function mount(): void
    {
        $this->authorize('settings.view');

        $default = (string) AppSetting::get(
            'payment_gateway_default',
            (string) config('payment_gateway.default', ''),
        );
        // Penyedia simulasi bisa dimatikan lewat environment; nilai lama yang
        // sudah tidak dapat dipilih tidak boleh tertinggal di formulir.
        $this->payment_gateway_default = in_array($default, $this->selectableProviders(), true) ? $default : '';

        $this->payment_gateway_expiry_hours = (int) AppSetting::get(
            'payment_gateway_expiry_hours',
            (int) config('payment_gateway.expiry_hours', 24),
        );
        $this->reconciliation_unique_amount_enabled = (bool) AppSetting::get(
            'reconciliation_unique_amount_enabled',
            config('payment_gateway.reconciliation.unique_amount_enabled', false),
        );
        $this->reconciliation_date_tolerance_days = (int) AppSetting::get(
            'reconciliation_date_tolerance_days',
            (int) config('payment_gateway.reconciliation.date_tolerance_days', 7),
        );
    }

    public function updatedReconciliationUniqueAmountEnabled(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        AppSetting::set('reconciliation_unique_amount_enabled', $this->reconciliation_unique_amount_enabled ? '1' : '0');
        $logger->log('settings.updated', 'Mengubah nominal unik rekonsiliasi bank');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan nominal unik berhasil diperbarui.');
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $data = $this->validate([
            // String kosong berarti "tanpa penyedia bawaan" (verifikasi manual).
            'payment_gateway_default' => ['nullable', 'string', Rule::in(array_merge([''], $this->selectableProviders()))],
            'payment_gateway_expiry_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'reconciliation_unique_amount_enabled' => ['boolean'],
            'reconciliation_date_tolerance_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $data['reconciliation_unique_amount_enabled'] = $this->reconciliation_unique_amount_enabled ? '1' : '0';

        foreach ($data as $key => $value) {
            AppSetting::set($key, $value);
        }

        $logger->log('settings.updated', 'Memperbarui pengaturan penyedia pembayaran');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan pembayaran berhasil disimpan.');
    }

    public function render(GatewayManager $gateways): View
    {
        $this->authorize('settings.view');

        $status = $gateways->availability();
        $providers = [];

        foreach (GatewayProvider::cases() as $provider) {
            $config = (array) config('payment_gateway.providers.'.$provider->value, []);

            $providers[] = [
                'key' => $provider->value,
                'label' => $provider->label(),
                'badge' => $provider->badgeVariant(),
                'enabled' => (bool) ($config['enabled'] ?? false),
                'configured' => (bool) ($status[$provider->value] ?? false),
                'mode' => isset($config['mode']) ? (string) $config['mode'] : null,
                'is_simulator' => $provider === GatewayProvider::Fake,
                'channels' => (array) ($config['channels'] ?? []),
                'callback_url' => route('webhooks.payment', ['provider' => $provider->value]),
                'credentials' => $this->credentialStatus($provider, $config),
            ];
        }

        return view('livewire.settings.gateway-index', [
            'providers' => $providers,
            'providerOptions' => $this->providerOptions(),
        ]);
    }

    /**
     * Pilihan penyedia bawaan.
     *
     * Penyedia simulasi hanya muncul bila memang diaktifkan lewat environment,
     * sehingga tidak pernah terpilih tanpa sengaja pada lingkungan produksi.
     *
     * @return array<string, string>
     */
    private function providerOptions(): array
    {
        $options = GatewayProvider::options();

        if ((bool) config('payment_gateway.providers.fake.enabled', false)) {
            $options[GatewayProvider::Fake->value] = GatewayProvider::Fake->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function selectableProviders(): array
    {
        return array_keys($this->providerOptions());
    }

    /**
     * Status keterisian kredensial: hanya nama field, tanpa nilai.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{label: string, filled: bool, required: bool}>
     */
    private function credentialStatus(GatewayProvider $provider, array $config): array
    {
        $fields = match ($provider) {
            GatewayProvider::Tripay => [
                'api_key' => 'API Key',
                'private_key' => 'Private Key',
                'merchant_code' => 'Merchant Code',
            ],
            GatewayProvider::Midtrans => [
                'server_key' => 'Server Key',
                'client_key' => 'Client Key',
                'merchant_id' => 'Merchant ID',
            ],
            GatewayProvider::Fake => [],
        };

        $required = match ($provider) {
            GatewayProvider::Tripay => ['api_key', 'private_key', 'merchant_code'],
            GatewayProvider::Midtrans => ['server_key'],
            GatewayProvider::Fake => [],
        };

        $credentials = [];

        foreach ($fields as $field => $label) {
            $credentials[] = [
                'label' => $label,
                'filled' => filled($config[$field] ?? null),
                'required' => in_array($field, $required, true),
            ];
        }

        return $credentials;
    }
}
