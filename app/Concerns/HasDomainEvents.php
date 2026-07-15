<?php

namespace App\Domain\Concerns;

use App\Domain\Events\DomainEvent;

trait HasDomainEvents
{
    /**
     * @var DomainEvent[]
     */
    private array $recordedEvents = [];

    /**
     * Record a domain event to be dispatched later.
     */
    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Get all recorded events.
     *
     * @return DomainEvent[]
     */
    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Clear all recorded events.
     * This should be called after events have been dispatched.
     */
    public function clearEvents(): void
    {
        $this->recordedEvents = [];
    }
}
