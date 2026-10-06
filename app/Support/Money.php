<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function parse(string $naira): int
    {
        if (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $naira)) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount with no more than two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $naira, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $kobo): string
    {
        $sign = $kobo < 0 ? '−' : '';
        $amount = abs($kobo);

        return $sign.'₦'.number_format(intdiv($amount, 100)).($amount % 100 ? '.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT) : '');
    }
}
