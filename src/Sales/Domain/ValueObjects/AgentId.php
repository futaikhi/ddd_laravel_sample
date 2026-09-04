<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Identity of the sales agent that owns a sale.
 *
 * Reuses the shared Ulid value object so agent ids share the
 * same generation, validation, and serialization rules as the
 * other Sales identifiers (SaleId, CustomerId, ProductId).
 */
final class AgentId extends Ulid
{
}
