<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class GestationalAgeCalculator
{
    public function calculate(?string $lmp, ?string $referenceDate): ?float
    {
        if (!$this->isValidDate($lmp) || !$this->isValidDate($referenceDate)) {
            return null;
        }

        $lmpDate = CarbonImmutable::parse($lmp)->startOfDay();
        $reference = CarbonImmutable::parse($referenceDate)->startOfDay();

        if ($reference->lt($lmpDate)) {
            return null;
        }

        return round($lmpDate->diffInDays($reference) / 7, 1);
    }

    private function isValidDate(?string $date): bool
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
