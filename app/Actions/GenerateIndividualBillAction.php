<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\BillingService;

final class GenerateIndividualBillAction
{
    public function __construct(private readonly BillingService $billing) {}

    /** @param array<string,mixed> $attributes */
    public function execute(array $attributes): object
    {
        return $this->billing->create($attributes);
    }
}
