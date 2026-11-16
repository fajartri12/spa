<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * Menimpa sebagian konfigurasi dari tabel `app_settings`.
 *
 * Kredensial payment gateway tetap hanya berasal dari environment
 * (AGENTS.MD bagian 9). Yang boleh diubah lewat antarmuka hanyalah pengaturan
 * operasional: penyedia bawaan, masa berlaku tagihan digital, dan perilaku
 * rekonsiliasi bank. Nilai dari database menjadi sumber utama, sedangkan
 * environment dipakai sebagai cadangan bila barisnya belum ada.
 */
final class SettingsConfigOverlay
{
    /**
     * @var array<string, string>
     */
    private const GATEWAY_SETTINGS = [
        'payment_gateway_default' => 'payment_gateway.default',
        'payment_gateway_expiry_hours' => 'payment_gateway.expiry_hours',
        'reconciliation_unique_amount_enabled' => 'payment_gateway.reconciliation.unique_amount_enabled',
        'reconciliation_date_tolerance_days' => 'payment_gateway.reconciliation.date_tolerance_days',
    ];

    public static function applyPaymentGateway(): void
    {
        try {
            $values = [];

            foreach (array_keys(self::GATEWAY_SETTINGS) as $key) {
                $values[$key] = AppSetting::get($key);
            }
        } catch (\Throwable) {
            // Tabel belum tersedia (fresh install atau saat migrasi):
            // biarkan konfigurasi environment dipakai apa adanya.
            return;
        }

        foreach (self::GATEWAY_SETTINGS as $setting => $configKey) {
            if ($values[$setting] === null) {
                continue;
            }

            config([$configKey => self::cast($setting, (string) $values[$setting])]);
        }
    }

    private static function cast(string $setting, string $value): string|int|bool
    {
        return match ($setting) {
            'payment_gateway_expiry_hours',
            'reconciliation_date_tolerance_days' => (int) $value,
            'reconciliation_unique_amount_enabled' => in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true),
            default => $value,
        };
    }
}
