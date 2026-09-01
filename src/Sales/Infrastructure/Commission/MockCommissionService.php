<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Commission;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\Money;

final class MockCommissionService implements CommissionCalculatorInterface
{
    private float $fixedRate = 3.0;

    public function __construct()
    {
    }

    /**
     * Set fixed commission rate for testing
     */
    public function setFixedRate(float $rate): void
    {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException("Commission rate must be between 0 and 100, got {$rate}");
        }
        $this->fixedRate = $rate;
    }

    /**
     * Calculate commission for a sale (mock implementation with fixed rate)
     */
    public function calculate(Sale $sale): Commission
    {
        $totalAmount = $sale->getTotalAmount();

        return Commission::fromRate(
            $totalAmount,
            $this->fixedRate,
            "Mock commission at {$this->fixedRate}%"
        );
    }

    /**
     * Reset to default rate
     */
    public function reset(): void
    {
        $this->fixedRate = 3.0;
    }
}
