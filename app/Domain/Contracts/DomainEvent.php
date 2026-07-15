<?php

namespace App\Domain\Contracts;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Base interface for all domain events.
 * Domain events represent something important that happened in the domain.
 */
interface DomainEvent extends JsonSerializable
{
    /**
     * Unique identifier for this event instance.
     */
    public function eventId(): string;

    /**
     * When this event occurred.
     */
    public function occurredOn(): DateTimeImmutable;

    /**
     * The aggregate root ID that generated this event.
     */
    public function aggregateId(): string;

    /**
     * Event name for identifying the type of event.
     */
    public function eventName(): string;

    /**
     * Convert the event to a payload array.
     */
    public function toPayload(): array;

    /**
     * Convert the event to a meta array.
     */
    public function toMeta(): array;
}
