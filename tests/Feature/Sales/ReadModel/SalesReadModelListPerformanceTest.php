<?php

declare(strict_types=1);

namespace Tests\Feature\Sales\ReadModel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Infrastructure\Persistence\SaleReadModelRepository;
use Tests\TestCase;

/**
 * AC-003 performance-oriented read-model test.
 *
 * The list query must use the denormalized sale_list_items read model and
 * supporting indexes instead of joining/aggregating write-side sales tables.
 */
final class SalesReadModelListPerformanceTest extends TestCase
{
    /** @var list<string> */
    private array $trackedSaleIds = [];

    protected function tearDown(): void
    {
        if ($this->trackedSaleIds !== []) {
            DB::table('sale_list_items')
                ->whereIn('id', $this->trackedSaleIds)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_list_query_uses_indexed_read_model_path_under_100ms(): void
    {
        $this->assumeDatabaseIsAvailable();

        $customerId = CustomerId::random();
        $this->seedProjectedSaleListItems($customerId->getValue(), 1_000);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $repository = new SaleReadModelRepository();

        $startedAt = hrtime(true);
        $result = $repository->paginate(
            customerId: $customerId,
            status: OrderStatus::COMPLETED->value,
            dateFrom: '2026-09-01 00:00:00',
            dateTo: '2026-09-30 23:59:59',
            limit: 25,
            offset: 0,
        );
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

        $this->assertLessThan(
            100.0,
            $elapsedMs,
            sprintf('AC-003 list read-model query should stay under 100ms, took %.2fms.', $elapsedMs),
        );

        $this->assertCount(25, $result->items);
        $this->assertSame(500, $result->totalCount);

        $executedSql = implode("\n", $queries);
        $this->assertStringContainsString('sale_list_items', $executedSql);
        $this->assertStringNotContainsString(' from "sales"', $executedSql);
        $this->assertStringNotContainsString(' from sales ', $executedSql);
        $this->assertStringNotContainsString('sale_line_items', $executedSql);

        $this->assertDatabaseIndexExists('sale_list_items_status_created_at_idx');
        $this->assertDatabaseIndexExists('sale_list_items_customer_created_at_idx');
        $this->assertDatabaseIndexExists('sale_list_items_created_at_id_idx');
    }

    private function seedProjectedSaleListItems(string $customerId, int $count): void
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $saleId = (string) Str::ulid();
            $this->trackedSaleIds[] = $saleId;

            $createdAt = sprintf('2026-09-%02d %02d:%02d:00', ($i % 28) + 1, $i % 24, $i % 60);
            $status = $i % 2 === 0 ? OrderStatus::COMPLETED->value : OrderStatus::PENDING->value;

            $rows[] = [
                'id' => $saleId,
                'customer_id' => $customerId,
                'customer_name' => null,
                'status' => $status,
                'total_amount' => 100_000 + $i,
                'currency' => 'IDR',
                'created_at' => $createdAt,
                'confirmed_at' => $status === OrderStatus::COMPLETED->value ? $createdAt : null,
                'completed_at' => $status === OrderStatus::COMPLETED->value ? $createdAt : null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'projected_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('sale_list_items')->insert($chunk);
        }
    }

    private function assumeDatabaseIsAvailable(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database connection is unavailable for AC-003 read-model performance test: '.$exception->getMessage());
        }
    }

    private function assertDatabaseIndexExists(string $indexName): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::table('pg_indexes')
                ->where('tablename', 'sale_list_items')
                ->where('indexname', $indexName)
                ->exists();
        } else {
            $indexes = DB::select("PRAGMA index_list('sale_list_items')");
            $exists = collect($indexes)->contains(static function (object $index) use ($indexName): bool {
                return ($index->name ?? null) === $indexName;
            });
        }

        $this->assertTrue($exists, sprintf('Expected database index [%s] to exist.', $indexName));
    }
}
