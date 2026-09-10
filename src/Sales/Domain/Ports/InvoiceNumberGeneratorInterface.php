<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use DateTimeImmutable;
use Src\Sales\Domain\ValueObjects\InvoiceNumber;

/**
 * Port for generating backend-side, human-readable invoice numbers.
 *
 * Implementations must guarantee uniqueness of the generated invoice number.
 */
interface InvoiceNumberGeneratorInterface
{
    /**
     * Generate the next invoice number.
     *
     * The generator may use $referenceDate as the basis for the numbering
     * scheme (e.g. INV-YYYYMMDD-NNNN). When null, the current date is used.
     */
    public function next(?DateTimeImmutable $referenceDate = null): InvoiceNumber;
}
