<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected static function spreadsheetSafe(mixed $value): mixed
    {
        return \App\Support\Csv::safe($value);
    }
}
