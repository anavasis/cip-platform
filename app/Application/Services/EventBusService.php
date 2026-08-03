<?php

namespace App\Application\Services;

use App\Domain\Events\DomainEvent;
use App\Infrastructure\Persistence\Models\StoredEvent;
use Illuminate\Support\Facades\Event;

class EventBusService
{
    public function dispatch(DomainEvent $event): void
    {
        StoredEvent::create([
            'event_type' => $event->eventName(),
            'payload' => $event->payload(),
            'occurred_at' => now(),
        ]);

        Event::dispatch($event);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, StoredEvent>
     */
    public function recent(int $limit = 50)
    {
        return StoredEvent::query()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }
}
