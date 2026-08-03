<?php

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\MonitoringMetric;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MonitoringService
{
    /**
     * @param  array<string, mixed>|null  $tags
     */
    public function record(string $name, float $value, ?array $tags = null): MonitoringMetric
    {
        return MonitoringMetric::create([
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'recorded_at' => now(),
        ]);
    }

    public function increment(string $name, float $amount = 1.0, ?array $tags = null): MonitoringMetric
    {
        return $this->record($name, $amount, $tags);
    }

    /**
     * @return LengthAwarePaginator<int, MonitoringMetric>|Collection<int, MonitoringMetric>
     */
    public function list(?string $name = null, int $perPage = 50)
    {
        $query = MonitoringMetric::query()->orderByDesc('recorded_at');

        if ($name) {
            $query->where('name', $name);
        }

        return $query->paginate($perPage);
    }
}
