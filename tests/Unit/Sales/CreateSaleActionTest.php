<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Models\Customer;
use App\Models\Product;
use Apps\Api\Sales\Create\CreateSaleAction;
use Apps\Api\Sales\Create\CreateSaleDto;
use Apps\Api\Sales\Create\LineItemInputDto;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;
use Tests\TestCase;

final class CreateSaleActionTest extends TestCase
{
    #[Test]
    public function test_it_uses_the_product_price_and_currency_when_creating_a_sale(): void
    {
        $sku = 'LP-'.strtoupper((string) Str::ulid());

        $customer = Customer::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Alice',
            'email' => 'alice-'.Str::ulid().'@example.com',
            'phone' => '081234567890',
        ]);

        $product = Product::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Laptop',
            'sku' => $sku,
            'price' => 2500000,
            'currency' => 'IDR',
        ]);

        $commandBus = $this->createMock(CommandBusInterface::class);
        $capturedCommand = null;

        $commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (CreateSaleCommand $command) use (&$capturedCommand): void {
                $capturedCommand = $command;
            });

        $action = new CreateSaleAction($commandBus);

        $action(new CreateSaleDto(
            id: SaleId::random(),
            customerId: CustomerId::fromString($customer->id),
            lineItems: [
                new LineItemInputDto(
                    productId: $product->id,
                    quantity: 2,
                ),
            ],
        ));

        $this->assertInstanceOf(CreateSaleCommand::class, $capturedCommand);
        $this->assertSame(2500000, $capturedCommand->lineItems[0]->unitPrice->getValue());
        $this->assertSame('IDR', $capturedCommand->lineItems[0]->unitPrice->currency);
    }
}
