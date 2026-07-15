<?php

namespace App\Concerns;

use App\Domain\Contracts\RecordsDomainEvents;
use Closure;
use Illuminate\Support\Facades\DB;

trait TransactsWithEvents
{
    protected function saveWithEvents(RecordsDomainEvents $aggregate, Closure $write): void
    {
        DB::transaction(function () use ($aggregate, $write) {
            $write();
            DB::afterCommit(fn () => $this->emitEvents($aggregate));
        });
    }

    private function emitEvents(RecordsDomainEvents $aggregate): void
    {
        foreach ($aggregate->recordedEvents() as $event) {
            event($event);
        }
        $aggregate->clearEvents();
    }
}
