<?php

declare(strict_types=1);

namespace App\Enums;

enum CashTransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
}
