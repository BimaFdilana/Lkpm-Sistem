<?php

namespace App\Support;

class Rupiah
{
    public static function compact(int|float $amount): string
    {
        if (abs($amount) >= 1000000000000) {
            return 'Rp '.number_format($amount / 1000000000000, 2, ',', '.').' T';
        }

        if (abs($amount) >= 1000000000) {
            return 'Rp '.number_format($amount / 1000000000, 2, ',', '.').' M';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
