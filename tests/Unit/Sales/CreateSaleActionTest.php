<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use Apps\Api\Sales\Create\CreateSaleAction;
use Apps\Api\Sales\Create\CreateSaleDto;
use Apps\Api\Sales\Create\LineItemInputDto;
use PHPUnit\Framework\Attributes\Test;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Application\Commands\Create\CreateSaleLineItem;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;
use Tests\TestCase;

final class CreateSaleActionTest extends TestCase
{
    #[Test]
    public function test_it_maps_http_input_to_create_sale_command_without_database_lookup(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);
        $capturedCommand = null;

        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (CreateSaleCommand $command) use (&$capturedCommand): void {
                $capturedCommand = $command;
            });

        $action = new CreateSaleAction($commandBus);
        $saleId = SaleId::random();
        $customerId = CustomerId::random();

        $response = $action(new CreateSaleDto(
            id: $saleId,
            customerId: $customerId,
            lineItems: [
                new LineItemInputDto(
                    productId: '01H8M6KJ5NQ8XX4P0N2VYJ4K5D',
                    quantity: 2,
                ),
            ],
        ));

        $this->assertSame($saleId->getValue(), $response->id);
        $this->assertInstanceOf(CreateSaleCommand::class, $capturedCommand);
        $this->assertSame($saleId, $capturedCommand->id);
        $this->assertSame($customerId, $capturedCommand->customerId);
        $this->assertCount(1, $capturedCommand->items);
        $this->assertInstanceOf(CreateSaleLineItem::class, $capturedCommand->items[0]);
        $this->assertSame('01H8M6KJ5NQ8XX4P0N2VYJ4K5D', $capturedCommand->items[0]->productId);
        $this->assertSame(2, $capturedCommand->items[0]->quantity);
    }
}
