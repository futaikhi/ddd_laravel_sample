<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\ValueObjects\Commission;

interface CommissionCalculatorInterface
{
    /**
     * Calculate commission for a sale
     *
     * @throws InvalidCommissionCalculationException
     */
    public function calculate(Sale $sale): Commission;
}
