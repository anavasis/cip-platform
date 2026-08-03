<?php

namespace Tests\Unit\Modules\Editorial\Diagnostics;

use App\Modules\Editorial\Application\EditorialDiagnostics;
use Tests\TestCase;

class EditorialDiagnosticsTest extends TestCase
{
    public function test_tenant_partitioning_and_no_body_leakage(): void
    {
        $diag = new EditorialDiagnostics;
        $diag->recordLastGeneration([
            'organization_id' => 'o1',
            'project_id' => 'p1',
            'ok' => true,
            'provider_code' => 'stub.deterministic',
            'model_id' => 'smce.stub.deterministic',
            'duration_ms' => 3,
            'result_status' => 'success',
            'correlation_id' => 'c1',
            'body' => 'SECRET_BODY',
            'content_text' => 'SECRET_TEXT',
        ]);
        $diag->recordLastGeneration([
            'organization_id' => 'o1',
            'project_id' => 'p2',
            'ok' => false,
            'error' => 'boom',
        ]);

        $a = $diag->snapshot('o1', 'p1');
        $b = $diag->snapshot('o1', 'p2');
        $this->assertSame(1, $a['generations_completed']);
        $this->assertSame(0, $b['generations_completed']);
        $this->assertSame(1, $b['generations_failed']);
        $this->assertSame('stub.deterministic', $a['last_provider_code']);
        $encoded = json_encode($a);
        $this->assertStringNotContainsString('SECRET_BODY', $encoded);
        $this->assertStringNotContainsString('SECRET_TEXT', $encoded);
    }

    public function test_reuse_does_not_inflate_completed_and_failures_count_once(): void
    {
        $diag = new EditorialDiagnostics;
        $diag->recordLastGeneration([
            'organization_id' => 'o1',
            'project_id' => 'p1',
            'ok' => true,
            'preview_available' => true,
        ]);
        $diag->recordReuse([
            'organization_id' => 'o1',
            'project_id' => 'p1',
            'preview_available' => true,
        ]);
        $diag->recordLastGeneration([
            'organization_id' => 'o1',
            'project_id' => 'p1',
            'ok' => false,
            'error' => 'provider_error',
        ]);
        // ephemeral orchestrator noise must not inflate counters
        $diag->recordLastGeneration([
            'organization_id' => 'o1',
            'project_id' => 'p1',
            'ok' => false,
            'count' => false,
            'error' => 'ignored',
        ]);

        $snap = $diag->snapshot('o1', 'p1');
        $this->assertSame(1, $snap['generations_completed']);
        $this->assertSame(1, $snap['generations_reused']);
        $this->assertSame(1, $snap['generations_failed']);
        $this->assertSame(3, $snap['generations_requested']);
    }
}
