<?php

declare(strict_types=1);

namespace Src\Shared\Framework\Domain\Events;

/**
 * Port for persisting DomainEvent instances into an append-only event store.
 *
 * Purpose
 * -------
 * - Keep an immutable audit trail of every state change that happens in the
 *   write side (`SaleCreatedEvent`, `SaleConfirmedEvent`, ...).
 * - Enable later features such as event replay, projections/read model
 *   rebuild, and cross-context integrations.
 *
 * Design notes
 * ------------
 * - This lives in the shared framework layer so any bounded context can reuse
 *   it (Sales today; Reservation, Client, etc. tomorrow).
 * - It is a *port* (hexagonal architecture). The concrete adapter that writes
 *   to the `domain_events` table will live in the infrastructure layer.
 * - `append()` intentionally accepts a single event. Batches are handled by
 *   the caller looping – this keeps the port minimal and easy to swap for an
 *   in-memory / null adapter in tests.
 */
interface DomainEventStoreInterface
{
    /**
     * Persist a domain event.
     *
     * Implementations MUST:
     *  - Assign a unique id if the event does not carry one.
     *  - Record `occurred_at` from the event itself and `recorded_at` = now().
     *  - Serialize event data as JSON (public readonly props of the event).
     *
     * Implementations SHOULD NOT throw for storage errors — surface them as
     * infrastructure exceptions so the caller (event bus / subscriber) can
     * decide whether to retry or degrade gracefully.
     */
    public function append(DomainEvent $event): void;
}
