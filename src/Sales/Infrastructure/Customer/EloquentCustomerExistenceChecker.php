<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Customer;

use App\Models\Customer;
use Src\Sales\Domain\Ports\CustomerExistenceCheckerInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;

/**
 * Eloquent-backed implementation of the customer existence port.
 *
 * Queries the shared `customers` table by primary key. Sales never
 * hydrates a Customer aggregate; it only asks whether the id is
 * known, which keeps the two bounded contexts loosely coupled.
 */
final class EloquentCustomerExistenceChecker implements CustomerExistenceCheckerInterface
{
    public function exists(CustomerId $customerId): bool
    {
        return Customer::query()
            ->whereKey($customerId->getValue())
            ->exists();
    }
}
