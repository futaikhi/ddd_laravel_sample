<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use Src\Sales\Domain\ValueObjects\CustomerId;

/**
 * Domain-side port that lets the Sales bounded context verify whether
 * a customer identity is known without depending on the Customer
 * bounded context or an ORM. Infrastructure adapters provide the
 * actual lookup (Eloquent, HTTP, in-memory, etc.).
 */
interface CustomerExistenceCheckerInterface
{
    public function exists(CustomerId $customerId): bool;
}
