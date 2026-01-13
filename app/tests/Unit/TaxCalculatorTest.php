<?php

namespace Tests\Unit;

use App\Support\TaxCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaxCalculatorTest extends TestCase
{
    public function test_calculates_tax_and_gross_amounts(): void
    {
        $calculator = new TaxCalculator();

        $tax = $calculator->calculateTax(10000, 5.5);
        $gross = $calculator->calculateGross(10000, 5.5);

        $this->assertSame(550, $tax);
        $this->assertSame(10550, $gross);
    }

    public function test_rounds_tax_to_nearest_cent(): void
    {
        $calculator = new TaxCalculator();

        $tax = $calculator->calculateTax(999, 5.5);

        $this->assertSame(55, $tax);
    }

    public function test_throws_for_negative_net_amount(): void
    {
        $calculator = new TaxCalculator();

        $this->expectException(InvalidArgumentException::class);

        $calculator->calculateTax(-1, 5.0);
    }

    public function test_throws_for_rate_out_of_range(): void
    {
        $calculator = new TaxCalculator();

        $this->expectException(InvalidArgumentException::class);

        $calculator->calculateTax(1000, 150.0);
    }
}
