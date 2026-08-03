<?php

namespace App\Domain\Events;

interface DomainEvent
{
    public function eventName(): string;

  /**
   * @return array<string, mixed>
   */
    public function payload(): array;
}
