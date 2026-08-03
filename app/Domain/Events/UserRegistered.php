<?php

namespace App\Domain\Events;

final class UserRegistered implements DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly ?string $organizationId = null,
    ) {}

    public function eventName(): string
    {
        return 'user.registered';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'organization_id' => $this->organizationId,
        ];
    }
}
