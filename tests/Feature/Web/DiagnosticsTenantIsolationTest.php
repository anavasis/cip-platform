<?php

namespace Tests\Feature\Web;

use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Infrastructure\Persistence\Models\User;
use App\Support\OperatorContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiagnosticsTenantIsolationTest extends TestCase
{
    public function test_project_a_sees_only_its_failed_events(): void
    {
        $a = $this->operatorContext('Org A', 'Project A');
        $b = $this->operatorContext('Org B', 'Project B');
        $this->storeFailedEvent($a['organization']->id, $a['project']->id, 'editorial.generation_failed', 'err_a');
        $this->storeFailedEvent($b['organization']->id, $b['project']->id, 'editorial.generation_failed', 'err_b');
        $this->storeFailedEvent(null, null, 'system.failed', 'orphan');

        $html = $this->actingAs($a['user'])->withSession([
            OperatorContext::SESSION_ORG => $a['organization']->id,
            OperatorContext::SESSION_PROJECT => $a['project']->id,
        ])->get(route('app.diagnostics'))->assertOk()->getContent();

        $this->assertStringContainsString('err_a', $html);
        $this->assertStringNotContainsString('err_b', $html);
        $this->assertStringNotContainsString('orphan', $html);
        $this->assertStringNotContainsString('system.failed', $html);
    }

    public function test_project_b_sees_only_its_failed_events(): void
    {
        $a = $this->operatorContext('Org A2', 'Project A2');
        $b = $this->operatorContext('Org B2', 'Project B2');
        $this->storeFailedEvent($a['organization']->id, $a['project']->id, 'acquisition.run_failed', 'code_a');
        $this->storeFailedEvent($b['organization']->id, $b['project']->id, 'acquisition.run_failed', 'code_b');

        $html = $this->actingAs($b['user'])->withSession([
            OperatorContext::SESSION_ORG => $b['organization']->id,
            OperatorContext::SESSION_PROJECT => $b['project']->id,
        ])->get(route('app.diagnostics'))->assertOk()->getContent();

        $this->assertStringContainsString('code_b', $html);
        $this->assertStringNotContainsString('code_a', $html);
    }

    public function test_event_without_verifiable_tenant_context_is_not_displayed(): void
    {
        $ctx = $this->operatorContext();
        StoredEvent::create([
            'id' => (string) Str::uuid(),
            'event_type' => 'mystery.failed',
            'payload' => [
                'prompt' => 'SECRET_PROMPT_BODY',
                'api_key' => 'sk-should-not-render',
                'raw_response' => ['choices' => [['message' => ['content' => 'ARTICLE_BODY']]]],
            ],
            'occurred_at' => now(),
        ]);

        $html = $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->get(route('app.diagnostics'))->assertOk()->getContent();

        $this->assertStringNotContainsString('mystery.failed', $html);
        $this->assertStringNotContainsString('SECRET_PROMPT_BODY', $html);
        $this->assertStringNotContainsString('sk-should-not-render', $html);
        $this->assertStringNotContainsString('ARTICLE_BODY', $html);
    }

    public function test_sensitive_payload_fields_are_not_rendered_for_tenant_events(): void
    {
        $ctx = $this->operatorContext();
        StoredEvent::create([
            'id' => (string) Str::uuid(),
            'event_type' => 'editorial.generation_failed',
            'payload' => [
                'organization_id' => $ctx['organization']->id,
                'project_id' => $ctx['project']->id,
                'error_code' => 'provider_error',
                'prompt' => 'DO_NOT_SHOW_PROMPT',
                'api_key' => 'sk-hidden',
                'article_body' => 'DO_NOT_SHOW_ARTICLE',
                'stack_trace' => 'Trace: secret stack',
            ],
            'occurred_at' => now(),
        ]);

        $html = $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->get(route('app.diagnostics'))->assertOk()->getContent();

        $this->assertStringContainsString('editorial.generation_failed', $html);
        $this->assertStringContainsString('provider_error', $html);
        $this->assertStringNotContainsString('DO_NOT_SHOW_PROMPT', $html);
        $this->assertStringNotContainsString('sk-hidden', $html);
        $this->assertStringNotContainsString('DO_NOT_SHOW_ARTICLE', $html);
        $this->assertStringNotContainsString('secret stack', $html);
    }

    public function test_unauthorized_diagnostics_access_denied(): void
    {
        $owner = $this->operatorContext('Owner Org', 'Owner Project');
        $outsider = User::factory()->create(['password' => Hash::make('password-secret')]);

        $this->actingAs($outsider)->withSession([
            OperatorContext::SESSION_ORG => $owner['organization']->id,
            OperatorContext::SESSION_PROJECT => $owner['project']->id,
        ])->get(route('app.diagnostics'))->assertRedirect(route('app.context.select'));
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function operatorContext(string $orgName = 'Diag Org', string $projectName = 'Diag Project'): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, $orgName);
        $project = app(ProjectService::class)->create($organization, $user, $projectName);

        return compact('user', 'organization', 'project');
    }

    private function storeFailedEvent(?string $organizationId, ?string $projectId, string $type, string $errorCode): void
    {
        $payload = ['error_code' => $errorCode];
        if ($organizationId !== null) {
            $payload['organization_id'] = $organizationId;
        }
        if ($projectId !== null) {
            $payload['project_id'] = $projectId;
        }

        StoredEvent::create([
            'id' => (string) Str::uuid(),
            'event_type' => $type,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
