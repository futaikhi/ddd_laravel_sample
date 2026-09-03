<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Src\Sales\Domain\Enums\OrderStatus;

final readonly class SalesFilter
{
    public function __construct(
        public ?CustomerId $customerId = null,
        public ?OrderStatus $status = null,
        public ?DateTimeImmutable $createdFrom = null,
        public ?DateTimeImmutable $createdTo = null,
        public ?int $limit = null,
        public ?int $offset = null,
    ) {
        if ($this->limit !== null && $this->limit < 1) {
            throw new InvalidArgumentException('Sales filter limit must be greater than zero.');
        }

        if ($this->offset !== null && $this->offset < 0) {
            throw new InvalidArgumentException('Sales filter offset cannot be negative.');
        }

        if ($this->createdFrom !== null && $this->createdTo !== null && $this->createdFrom > $this->createdTo) {
            throw new InvalidArgumentException('Sales filter createdFrom cannot be after createdTo.');
        }
    }

    public static function all(): self
    {
        return new self();
    }
}
