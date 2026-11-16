<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PaymentGatewayService;
use Illuminate\Console\Command;

class ExpirePaymentIntents extends Command
{
    protected $signature = 'gateway:expire-intents';

    protected $description = 'Tandai tagihan digital (VA/QRIS) yang melewati masa berlaku sebagai kedaluwarsa';

    public function handle(PaymentGatewayService $gateway): int
    {
        $expired = $gateway->expireOverdue();

        $this->info("Tagihan digital kedaluwarsa: {$expired}");

        return self::SUCCESS;
    }
}
