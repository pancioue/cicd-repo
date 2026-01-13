<?php

namespace App\Support;

use InvalidArgumentException;

final class TaxCalculator
{
    public function calculateTax(int $netCents, float $ratePercent): int
    {
        $this->guardNetAmount($netCents);
        $this->guardRate($ratePercent);

        return (int) round($netCents * $ratePercent / 100);
    }

    public function calculateGross(int $netCents, float $ratePercent): int
    {
        return $netCents + $this->calculateTax($netCents, $ratePercent);
    }

    private function guardNetAmount(int $netCents): void
    {
        if ($netCents < 0) {
            throw new InvalidArgumentException('Net amount must be zero or positive.');
        }
    }

    private function guardRate(float $ratePercent): void
    {
        if ($ratePercent < 0 || $ratePercent > 100) {
            throw new InvalidArgumentException('Tax rate must be between 0 and 100.');
        }
    }
}
