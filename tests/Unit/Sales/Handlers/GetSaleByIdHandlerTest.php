<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Queries\GetById\GetSaleByIdHandler;
use Src\Sales\Application\Queries\GetById\GetSaleByIdQuery;
use Src\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Domain\ReadModels\SaleDetailRM;
use Src\Sales\Domain\ReadModels\SaleLineItemRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Sales\Domain\ValueObjects\SaleId;

/**
 * Unit tests for {@see GetSaleByIdHandler} (AC-003 / CQRS query side).
 *
 * These tests exercise the query handler in isolation: the read-model
 * repository is mocked so we can assert:
 *   - the handler asks the read-side port for the correct sale
 *   - it returns the flat SaleDetailRM DTO unchanged (no domain aggregate,
 *     no state mutation, no event publication)
 *   - it throws SaleNotFoundException when the read model has no entry
 *
 * The read-model repository is the ONLY collaborator, which is exactly
 * what a proper CQRS query handler should look like.
 */
final class GetSaleByIdHandlerTest extends TestCase
{
    public function test_it_returns_sale_detail_read_model_from_read_repository(): void
    {
        $saleId = SaleId::random();

        $expected = new SaleDetailRM(
            id: $saleId->getValue(),
            customerId: '01H8M6KJ5NQ8XX4P0N2VYJ4K5D',
            status: 'PENDING',
            totalAmount: 30000,
            currency: 'IDR',
            lineItems: [
                new SaleLineItemRM(
                    productId: '01H8M6KJ5NQ8XX4P0N2VYJ4K5E',
                    quantity: 2,
                    unitPrice: 15000,
                    currency: 'IDR',
                    lineTotal: 30000,
                ),
            ],
            createdAt: '2026-09-03 04:00:00',
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findDetail')
            ->with($this->identicalTo($saleId))
            ->willReturn($expected);

        // A query handler must never touch the write side or publish events.
        // We assert this implicitly: no write-side collaborators are injected;
        // the only dependency is the read-model repository.
        $handler = new GetSaleByIdHandler($repo);

        $result = $handler(new GetSaleByIdQuery(saleId: $saleId));

        $this->assertSame($expected, $result);
        $this->assertSame($saleId->getValue(), $result->id);
        $this->assertSame('PENDING', $result->status);
        $this->assertCount(1, $result->lineItems);
    }

    public function test_it_throws_sale_not_found_when_read_model_returns_null(): void
    {
        $saleId = SaleId::random();

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findDetail')
            ->with($this->identicalTo($saleId))
            ->willReturn(null);

        $handler = new GetSaleByIdHandler($repo);

        $this->expectException(SaleNotFoundException::class);

        $handler(new GetSaleByIdQuery(saleId: $saleId));
    }
}
