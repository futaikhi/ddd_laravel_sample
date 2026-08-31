<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use Apps\Api\Sales\Cancel\CancelSaleAction;
use Apps\Api\Sales\Cancel\CancelSaleDto;
use Apps\Api\Sales\Complete\CompleteSaleAction;
use Apps\Api\Sales\Complete\CompleteSaleDto;
use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Commands\Cancel\CancelSaleCommand;
use Src\Sales\Application\Commands\Complete\CompleteSaleCommand;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final class CancelCompleteSaleActionTest extends TestCase
{
    public function test_it_dispatches_cancel_sale_command(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);

        $captured = null;
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($command) use (&$captured): bool {
                $captured = $command;

                return $command instanceof CancelSaleCommand
                    && $command->reason === 'customer changed mind';
            }));

        $action = new CancelSaleAction($commandBus);
        $action(new CancelSaleDto(
            saleId: SaleId::random(),
            reason: 'customer changed mind',
        ));

        $this->assertInstanceOf(CancelSaleCommand::class, $captured);
    }

    public function test_it_dispatches_complete_sale_command(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);

        $captured = null;
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($command) use (&$captured): bool {
                $captured = $command;

                return $command instanceof CompleteSaleCommand;
            }));

        $action = new CompleteSaleAction($commandBus);
        $action(new CompleteSaleDto(
            saleId: SaleId::random(),
        ));

        $this->assertInstanceOf(CompleteSaleCommand::class, $captured);
    }
}
