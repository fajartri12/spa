<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Enums\GatewayProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Pemilihan driver payment gateway berdasarkan penyedia.
 *
 * `PaymentGatewayService` tidak pernah meng-instantiate driver secara langsung,
 * sehingga penambahan penyedia baru cukup didaftarkan di sini.
 */
final class GatewayManager
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private const DRIVERS = [
        'tripay' => TripayGateway::class,
        'midtrans' => MidtransGateway::class,
        'fake' => FakeGateway::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * Driver untuk penyedia yang diminta.
     *
     * @throws GatewayException
     */
    public function driver(GatewayProvider|string $provider): PaymentGateway
    {
        $key = $provider instanceof GatewayProvider ? $provider->value : $provider;
        $class = self::DRIVERS[$key] ?? null;

        if ($class === null) {
            throw new GatewayException("Penyedia pembayaran '{$key}' tidak dikenal.");
        }

        /** @var PaymentGateway $driver */
        $driver = $this->container->make($class);

        return $driver;
    }

    /**
     * Penyedia yang kredensialnya lengkap, dipakai untuk validasi pengaturan.
     *
     * @return array<string, bool>
     */
    public function availability(): array
    {
        $result = [];

        foreach (array_keys(self::DRIVERS) as $key) {
            $result[$key] = $this->driver($key)->isConfigured();
        }

        return $result;
    }

    /**
     * Kanal yang tersedia untuk sebuah penyedia: kode => label.
     *
     * @return array<string, string>
     */
    public function channels(GatewayProvider|string $provider): array
    {
        return $this->driver($provider)->channels();
    }
}
