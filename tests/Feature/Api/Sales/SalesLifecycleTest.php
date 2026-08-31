<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sales;

use Illuminate\Support\Str;
use Src\Sales\Infrastructure\Persistence\SaleLineItemModel;
use Src\Sales\Infrastructure\Persistence\SaleModel;
use Tests\TestCase;

final class SalesLifecycleTest extends TestCase
{
    public function test_it_can_cancel_a_sale(): void
    {
        $saleId = (string) Str::ulid();

        SaleModel::query()->create([
            'id' => $saleId,
            'customer_id' => (string) Str::ulid(),
            'status' => 'pending',
            'total_amount' => 100000,
        ]);

        SaleLineItemModel::query()->create([
            'sale_id' => $saleId,
            'product_id' => (string) Str::ulid(),
            'quantity' => 2,
            'unit_price' => 50000,
            'currency' => 'IDR',
        ]);

        $response = $this->postJson('/api/sales/'.$saleId.'/cancel', [
            'reason' => 'customer changed mind',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Sale cancelled successfully');
    }

    public function test_it_can_complete_a_sale(): void
    {
        $saleId = (string) Str::ulid();

        SaleModel::query()->create([
            'id' => $saleId,
            'customer_id' => (string) Str::ulid(),
            'status' => 'confirmed',
            'total_amount' => 100000,
            'confirmed_at' => now()->toDateTimeString(),
        ]);

        SaleLineItemModel::query()->create([
            'sale_id' => $saleId,
            'product_id' => (string) Str::ulid(),
            'quantity' => 2,
            'unit_price' => 50000,
            'currency' => 'IDR',
        ]);

        $response = $this->postJson('/api/sales/'.$saleId.'/complete');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Sale completed successfully');
    }
}
