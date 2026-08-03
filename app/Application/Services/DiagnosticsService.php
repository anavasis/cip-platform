<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class DiagnosticsService
{
    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return [
            'status' => $this->overallStatus(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function overallStatus(): string
    {
        $checks = [$this->checkDatabase(), $this->checkRedis(), $this->checkQueue()];

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                return 'degraded';
            }
        }

        return 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRedis(): array
    {
        try {
            if (config('cache.default') === 'array' || config('database.redis.client') === null) {
                return ['status' => 'ok', 'message' => 'Redis not required in current configuration'];
            }

            Redis::connection()->ping();

            return ['status' => 'ok', 'message' => 'Redis connection successful'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            Queue::connection($connection);

            return ['status' => 'ok', 'message' => "Queue connection [{$connection}] available"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
