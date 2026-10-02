<?php

namespace App\Support;

use InvalidArgumentException;

class WeightFormatter
{
    public static function formatKg(int|float|string|null $weight): ?string
    {
        if ($weight === null || $weight === '') {
            return null;
        }

        if (!is_numeric($weight)) {
            throw new InvalidArgumentException('Kilogram weight must be numeric.');
        }

        return number_format((float) $weight, 1, '.', '');
    }
}
