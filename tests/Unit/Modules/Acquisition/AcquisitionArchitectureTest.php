<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Application\CapabilityGate;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class AcquisitionArchitectureTest extends TestCase
{
    public function test_provider_uses_kernel_scheduler_adapter_without_laravel_schedule_loop(): void
    {
        $provider = $this->contents('app/Providers/AnnouncementAcquisitionServiceProvider.php');

        $this->assertStringContainsString(
            'SchedulerService::class, AcquisitionAwareSchedulerService::class',
            $provider,
        );
        $this->assertStringNotContainsString('everyFiveMinutes', $provider);
        $this->assertStringNotContainsString('Schedule::class', $provider);
    }

    public function test_capabilities_and_source_schema_default_fail_closed(): void
    {
        $gate = new CapabilityGate;
        $migration = $this->contents(
            'database/migrations/2026_08_03_110001_create_sources_table.php',
        );

        $this->assertFalse($gate->isEnabled(CapabilityGate::ACQUISITION));
        $this->assertFalse($gate->isEnabled(CapabilityGate::SOURCE_REGISTRY));
        $this->assertStringContainsString("boolean('enabled')->default(false)", $migration);
        $this->assertStringContainsString("boolean('manual_only')->default(true)", $migration);
        $this->assertStringContainsString(
            "unsignedInteger('acquire_interval_seconds')->default(3600)",
            $migration,
        );
    }

    public function test_acquisition_transport_pins_validated_addresses(): void
    {
        $guard = $this->contents(
            'app/Modules/Acquisition/Infrastructure/Http/SafeUrlGuard.php',
        );
        $fetcher = $this->contents(
            'app/Modules/Acquisition/Infrastructure/Http/LaravelSafeFeedFetcher.php',
        );
        $transport = $this->contents(
            'app/Modules/Acquisition/Infrastructure/Http/CurlPinnedHttpTransport.php',
        );

        $this->assertStringContainsString("'ips' => \$resolvedAddresses", $guard);
        $this->assertStringContainsString('CurlPinnedHttpTransport', $fetcher);
        $this->assertStringContainsString('CURLOPT_RESOLVE', $transport);
        $this->assertStringContainsString("'allow_redirects' => false", $transport);
        $this->assertStringContainsString('new CurlHandler', $transport);
        $this->assertStringNotContainsString('Utils::chooseHandler', $transport);
        $this->assertStringContainsString('stream_handler_forbidden', $transport);
    }

    public function test_migrated_modules_have_no_wordpress_runtime_leakage(): void
    {
        foreach (['app/Modules/Announcement', 'app/Modules/Acquisition'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root().'/'.$directory),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()
                    || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = strtolower((string) file_get_contents($file->getPathname()));
                $this->assertStringNotContainsString('wordpress', $contents, $file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/\bwp_[a-z0-9_]+\b/', $contents);
            }
        }
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($this->root().'/'.$path);
        $this->assertIsString($contents);

        return $contents;
    }

    private function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
