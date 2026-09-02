<?php

declare(strict_types=1);

namespace Src\Shared\Framework\Infrastructure\Events;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Src\Shared\Framework\Domain\Events\DomainEvent;
use Src\Shared\Framework\Domain\Events\DomainEventStoreInterface;

/**
 * Eloquent-backed adapter that appends DomainEvent instances to the
 * `domain_events` table (see migration 2026_09_01_000003).
 *
 * Serialization strategy
 * ----------------------
 * The event payload is built from every public property of the concrete
 * DomainEvent class *except* those defined on the base class (`occurredOn`
 * and the Laravel traits). This lets bounded contexts add new events without
 * touching the store.
 *
 * Aggregate correlation
 * ---------------------
 * - `aggregate_id`   ← first field ending with `Id` (e.g. `saleId`, `bookingId`)
 *                     falls back to '' if the event has none.
 * - `aggregate_type` ← the prefix of `getName()` (e.g. `sale.confirmed` → `sale`).
 */
final class EloquentDomainEventStore implements DomainEventStoreInterface
{
    private const TABLE = 'domain_events';

    public function append(DomainEvent $event): void
    {
        $payload = $this->extractPayload($event);

        DB::table(self::TABLE)->insert([
            'id'             => (string) Str::uuid(),
            'aggregate_id'   => $this->extractAggregateId($payload),
            'aggregate_type' => $this->extractAggregateType($event),
            'event_type'     => $event::class,
            'event_name'     => $event->getName(),
            'event_data'     => (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'occurred_at'    => (new DateTimeImmutable('@' . $event->occurredOn))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s'),
            'recorded_at'    => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(DomainEvent $event): array
    {
        $reflection = new ReflectionClass($event);
        $payload    = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            // Skip inherited properties (occurredOn + traits) — only the
            // concrete event's own fields represent the business payload.
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $payload[$property->getName()] = $property->getValue($event);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractAggregateId(array $payload): string
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && str_ends_with($key, 'Id') && is_string($value) && $value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function extractAggregateType(DomainEvent $event): string
    {
        $name = $event->getName();
        $dot  = strpos($name, '.');
        return $dot === false ? $name : substr($name, 0, $dot);
    }
}
