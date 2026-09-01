<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Confirm;

use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class ConfirmSaleCommand implements CommandInterface
{
    public function __construct(
        public SaleId $id,
        public PaymentMethod $paymentMethod,
    ) {
    }
}
