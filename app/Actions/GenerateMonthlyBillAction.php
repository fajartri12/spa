<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\BillingService;
use Illuminate\Support\Collection;

final class GenerateMonthlyBillAction
{
    public function __construct(private readonly GenerateMassBillAction $massBills) {}

    /** @return Collection<int, object> */
    public function execute(array $attributes): Collection
    {
        $attributes['period'] ??= now()->format('Y-m');

        return $this->massBills->execute($attributes);
    }
}
