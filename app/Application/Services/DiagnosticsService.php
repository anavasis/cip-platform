<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiagnosticsService
{
    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'scheduler' => $this->checkScheduler(),
            'provider' => $this->checkProvider(),
        ];

        return [
            'status' => $this->overallStatus($checks),
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function overallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'error') {
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default', 'local'));
            $probe = 'diagnostics/health-'.uniqid('', true).'.txt';
            $disk->put($probe, 'ok');
            $ok = $disk->exists($probe);
            $disk->delete($probe);

            return $ok
                ? ['status' => 'ok', 'message' => 'Storage read/write successful']
                : ['status' => 'error', 'message' => 'Storage write verification failed'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkScheduler(): array
    {
        try {
            $exit = Artisan::call('list', ['--raw' => true]);
            $output = Artisan::output();
            $hasPlatform = str_contains($output, 'platform:schedules:run-due');

            return $hasPlatform && $exit === 0
                ? ['status' => 'ok', 'message' => 'Scheduler command platform:schedules:run-due is registered']
                : ['status' => 'error', 'message' => 'Scheduler command platform:schedules:run-due is missing'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Provider connectivity is fail-closed and never logs secrets.
     *
     * @return array<string, mixed>
     */
    private function checkProvider(): array
    {
        $driver = (string) config('editorial.ai.driver', 'stub');
        if ($driver === 'stub') {
            return ['status' => 'ok', 'message' => 'Stub AI provider bound (testing/offline)'];
        }

        if ($driver !== 'openai') {
            return ['status' => 'error', 'message' => 'Unknown AI driver: '.$driver];
        }

        try {
            $baseUrl = rtrim((string) config('editorial.ai.openai.base_url', 'https://api.openai.com/v1'), '/');
            $response = Http::acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->get($baseUrl.'/models');

            // Without a key OpenAI returns 401; that still proves network reachability.
            if (in_array($response->status(), [200, 401, 403], true)) {
                return [
                    'status' => 'ok',
                    'message' => 'OpenAI endpoint reachable (HTTP '.$response->status().')',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'OpenAI endpoint unexpected HTTP '.$response->status(),
            ];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => 'OpenAI endpoint unreachable'];
        }
    }
}
