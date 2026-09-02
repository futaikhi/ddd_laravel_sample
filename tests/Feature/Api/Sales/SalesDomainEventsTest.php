<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sales;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Infrastructure\Persistence\SaleLineItemModel;
use Src\Sales\Infrastructure\Persistence\SaleModel;
use Tests\TestCase;

final class SalesDomainEventsTest extends TestCase
{
    public function test_it_records_audit_trail_when_sale_is_cancelled(): void
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

        $response = $this->postJson('/api/sales/' . $saleId . '/cancel', [
            'reason' => 'customer changed mind',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('domain_events', [
            'aggregate_id' => $saleId,
            'aggregate_type' => 'sale',
            'event_name' => 'sale.cancelled',
        ]);
    }

    public function test_it_updates_metrics_and_commission_projections_when_sale_is_completed(): void
    {
        Cache::flush();

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

        $response = $this->postJson('/api/sales/' . $saleId . '/complete');

        $response->assertStatus(200);

        $event = DB::table('domain_events')
            ->where('aggregate_id', $saleId)
            ->where('event_name', 'sale.completed')
            ->first();

        $this->assertNotNull($event);

        $payload = json_decode((string) $event->event_data, true, 512, JSON_THROW_ON_ERROR);
        $completedDate = substr((string) $payload['completedAt'], 0, 10);

        $this->assertSame(1, Cache::get("sales.metrics.{$completedDate}.completed_count"));
        $this->assertSame(100000, Cache::get("sales.metrics.{$completedDate}.revenue_total"));
        $this->assertSame(1, Cache::get("sales.commission.{$completedDate}.completed_sales_count"));
        $this->assertSame(3000, Cache::get("sales.commission.{$completedDate}.commission_total"));
        $this->assertSame('IDR', Cache::get("sales.commission.{$completedDate}.currency"));
    }
}
