<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Commission;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\ValueObjects\Commission;

final class DatabaseCommissionService implements CommissionCalculatorInterface
{
    /**
     * Calculate commission based on sale amount using business rules.
     *
     * Business Rules (aligned with Task.txt deep-dive):
     * - If total amount >= Rp 1,000,000  => 5% commission
     * - Else if total amount >= Rp 500,000 => 3% commission
     * - Otherwise                          => 1% commission
     *
     * This logic could be extended to query a commission_rates table
     * or use a rate engine service.
     */
    public function calculate(Sale $sale): Commission
    {
        $totalAmount = $sale->getTotalAmount();
        $amountValue = $totalAmount->getValue();

        // Determine commission rate based on sale amount
        $rate = $this->determineRate($amountValue);

        Log::info('Commission calculated', [
            'sale_id' => $sale->getId()->getValue(),
            'amount' => $amountValue,
            'rate' => $rate,
        ]);

        return Commission::fromRate(
            $totalAmount,
            $rate,
            "Commission at {$rate}% for amount {$amountValue}"
        );
    }

    /**
     * Determine commission rate based on sale amount.
     *
     * Tiered structure derived from Task.txt:
     * - >= 1,000,000  => 5%
     * - >= 500,000    => 3%
     * - otherwise     => 1%
     *
     * This could be extended to:
     * - Query a database table
     * - Consider customer tier/loyalty
     * - Use an external rate engine
     */
    private function determineRate(int $amount): float
    {
        if ($amount >= 1_000_000) {
            return 5.0;
        }

        if ($amount >= 500_000) {
            return 3.0;
        }

        return 1.0;
    }
}
