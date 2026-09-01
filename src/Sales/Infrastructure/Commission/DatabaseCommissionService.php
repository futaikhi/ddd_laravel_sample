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
     * Calculate commission based on sale amount using business rules
     *
     * Business Rules:
     * - If total amount > Rp 1,000,000: 5% commission
     * - If total amount <= Rp 1,000,000: 3% commission
     *
     * This logic could be extended to query from a commission_rates table
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
     * Determine commission rate based on sale amount
     *
     * Could be extended to:
     * - Query from database table
     * - Use tiered rate structure
     * - Consider customer tier/loyalty
     * - Use external rate engine
     */
    private function determineRate(int $amount): float
    {
        // Business rule: amounts > 1 million get 5% commission
        if ($amount > 1000000) {
            return 5.0;
        }

        // Default: 3% commission for smaller amounts
        return 3.0;
    }
}
