<?php

namespace App\Modules\Acquisition\Domain\Collectors;

final class CollectorRegistry
{
    /** @var array<string, CollectorInterface> */
    private array $collectors = [];

    /** @var array<string, string> */
    private array $sourceTypeMap = [];

    public function register(CollectorInterface $collector): void
    {
        $this->collectors[$collector->id()] = $collector;
    }

    public function mapSourceType(string $sourceType, string $collectorId): void
    {
        $type = strtolower(trim($sourceType));
        $id = trim($collectorId);

        if ($type !== '' && $id !== '') {
            $this->sourceTypeMap[$type] = $id;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->collectors[$id]);
    }

    public function get(string $id): ?CollectorInterface
    {
        return $this->collectors[$id] ?? null;
    }

    public function resolveForSourceType(string $sourceType): ?CollectorInterface
    {
        $type = strtolower(trim($sourceType));

        if ($type !== '' && isset($this->sourceTypeMap[$type])) {
            return $this->get($this->sourceTypeMap[$type]);
        }

        return $this->defaultCollector();
    }

    public function defaultCollector(): ?CollectorInterface
    {
        return $this->get('safe_feed');
    }

    /** @return array<string, CollectorInterface> */
    public function all(): array
    {
        return $this->collectors;
    }

    /** @return array<string, string> */
    public function sourceTypeMap(): array
    {
        return $this->sourceTypeMap;
    }
}
