<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Sales\Domain\ValueObjects\CustomerId;

/**
 * Raised when the referenced customer does not exist.
 *
 * Kept in the Sales domain (not the Customer domain) because from Sales's
 * point of view it's a Sales-side integrity violation: we cannot create a
 * sale for an unknown customer.
 */
final class CustomerNotFoundException extends RuntimeException
{
    public static function withId(CustomerId $id): self
    {
        return new self(sprintf('Customer %s was not found.', $id->getValue()));
    }
}
