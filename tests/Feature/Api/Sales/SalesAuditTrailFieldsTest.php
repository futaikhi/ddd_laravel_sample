<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sales;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Domain\Events\CommissionCalculatedEvent;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Events\SaleCreatedEvent;
use Tests\TestCase;

final class SalesAuditTrailFieldsTest extends TestCase
{
    public function test_it_records_complete_audit_trail_fields_for_sale_created_confirmed_completed_and_commission_events(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct(price: 100000);

        $createResponse = $this->postJson('/api/sales', [
            'customer_id' => $customer->id,
            'line_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $saleId = (string) $createResponse->json('id');

        [, $createdPayload] = $this->assertDomainEventAuditTrail(
            aggregateId: $saleId,
            aggregateType: 'sale',
            eventName: 'sale.created',
            eventType: SaleCreatedEvent::class,
            requiredPayloadKeys: ['saleId', 'customerId', 'totalAmount', 'items', 'createdAt'],
        );

        $this->assertSame($customer->id, $createdPayload['customerId']);
        $this->assertSame(200000, $createdPayload['totalAmount']);
        $this->assertCount(1, $createdPayload['items']);
        $this->assertSame([
            'productId' => $product->id,
            'quantity' => 2,
            'unitPrice' => 100000,
            'currency' => 'IDR',
            'total' => 200000,
        ], $createdPayload['items'][0]);

        $confirmResponse = $this->postJson('/api/sales/' . $saleId . '/confirm', [
            'payment_method' => 'credit_card',
        ]);

        $confirmResponse->assertStatus(200);

        [, $confirmedPayload] = $this->assertDomainEventAuditTrail(
            aggregateId: $saleId,
            aggregateType: 'sale',
            eventName: 'sale.confirmed',
            eventType: SaleConfirmedEvent::class,
            requiredPayloadKeys: ['saleId', 'confirmedAt'],
        );

        $this->assertNotSame('', $confirmedPayload['confirmedAt']);

        $completeResponse = $this->postJson('/api/sales/' . $saleId . '/complete');

        $completeResponse->assertStatus(200);

        [, $completedPayload] = $this->assertDomainEventAuditTrail(
            aggregateId: $saleId,
            aggregateType: 'sale',
            eventName: 'sale.completed',
            eventType: SaleCompletedEvent::class,
            requiredPayloadKeys: ['saleId', 'completedAt', 'totalAmount', 'commissionAmount', 'commissionRate', 'commissionCurrency'],
        );

        $this->assertSame(200000, $completedPayload['totalAmount']);
        $this->assertSame(6000, $completedPayload['commissionAmount']);
        $this->assertEqualsWithDelta(3.0, (float) $completedPayload['commissionRate'], 0.001);
        $this->assertSame('IDR', $completedPayload['commissionCurrency']);
        $this->assertNotSame('', $completedPayload['completedAt']);

        [, $commissionPayload] = $this->assertDomainEventAuditTrail(
            aggregateId: $saleId,
            aggregateType: 'commission',
            eventName: 'commission.calculated',
            eventType: CommissionCalculatedEvent::class,
            requiredPayloadKeys: ['saleId', 'amount', 'percentage', 'currency', 'calculatedAt'],
        );

        $this->assertSame(6000, $commissionPayload['amount']);
        $this->assertEqualsWithDelta(3.0, (float) $commissionPayload['percentage'], 0.001);
        $this->assertSame('IDR', $commissionPayload['currency']);
        $this->assertSame($completedPayload['completedAt'], $commissionPayload['calculatedAt']);
    }

    public function test_it_records_complete_audit_trail_fields_for_sale_cancelled_event(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct(price: 50000);

        $createResponse = $this->postJson('/api/sales', [
            'customer_id' => $customer->id,
            'line_items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $saleId = (string) $createResponse->json('id');

        $cancelResponse = $this->postJson('/api/sales/' . $saleId . '/cancel', [
            'reason' => 'customer changed mind',
        ]);

        $cancelResponse->assertStatus(200);

        [, $payload] = $this->assertDomainEventAuditTrail(
            aggregateId: $saleId,
            aggregateType: 'sale',
            eventName: 'sale.cancelled',
            eventType: SaleCancelledEvent::class,
            requiredPayloadKeys: ['saleId', 'reason', 'cancelledAt'],
        );

        $this->assertSame('customer changed mind', $payload['reason']);
        $this->assertNotSame('', $payload['cancelledAt']);
    }

    /**
     * @param list<string> $requiredPayloadKeys
     * @return array{0: object, 1: array<string, mixed>}
     */
    private function assertDomainEventAuditTrail(
        string $aggregateId,
        string $aggregateType,
        string $eventName,
        string $eventType,
        array $requiredPayloadKeys,
    ): array {
        $event = DB::table('domain_events')
            ->where('aggregate_id', $aggregateId)
            ->where('event_name', $eventName)
            ->first();

        $this->assertNotNull($event, "Expected {$eventName} event for aggregate {$aggregateId}.");
        $this->assertTrue(Str::isUuid((string) $event->id), "Expected {$eventName} audit id to be a UUID.");
        $this->assertSame($aggregateId, (string) $event->aggregate_id);
        $this->assertSame($aggregateType, (string) $event->aggregate_type);
        $this->assertSame($eventType, (string) $event->event_type);
        $this->assertSame($eventName, (string) $event->event_name);
        $this->assertNotSame('', (string) $event->event_data);
        $this->assertNotSame('', (string) $event->occurred_at);
        $this->assertNotSame('', (string) $event->recorded_at);

        $payload = json_decode((string) $event->event_data, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($payload);
        $this->assertSame($aggregateId, $payload['saleId'] ?? null);

        foreach ($requiredPayloadKeys as $key) {
            $this->assertArrayHasKey($key, $payload, "Expected {$eventName} payload to contain {$key}.");
        }

        return [$event, $payload];
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Audit Trail Customer',
            'email' => 'audit-' . Str::lower((string) Str::ulid()) . '@example.test',
            'phone' => '08123456789',
        ]);
    }

    private function createProduct(int $price): Product
    {
        return Product::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Audit Trail Product',
            'sku' => 'AUDIT-' . Str::upper((string) Str::ulid()),
            'price' => $price,
            'currency' => 'IDR',
        ]);
    }
}
