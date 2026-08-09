<?php

namespace Tests\Feature\Web;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Support\OperatorContext;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsAiInstructionsTest extends TestCase
{
    public function test_admin_can_save_project_editorial_instructions(): void
    {
        $ctx = $this->operatorContext('Instructions Org', 'Project A');

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.ai'), $this->aiPayload([
                'system_prompt' => 'Project A system prompt text.',
                'article_instructions' => 'Project A article instruction text.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'AI settings saved.');

        $config = app(ConfigurationService::class);
        $system = $config->get($ctx['organization']->id, 'editorial.ai.system_prompt', $ctx['project']->id);
        $article = $config->get($ctx['organization']->id, 'editorial.ai.article_instructions', $ctx['project']->id);

        $this->assertSame('Project A system prompt text.', $system?->value['value'] ?? null);
        $this->assertSame('Project A article instruction text.', $article?->value['value'] ?? null);
    }

    public function test_settings_page_displays_saved_project_instructions(): void
    {
        $ctx = $this->operatorContext('Display Org', 'Project A');
        $config = app(ConfigurationService::class);
        $config->set($ctx['organization']->id, 'editorial.ai.system_prompt', ['value' => 'Saved system prompt.'], $ctx['project']->id, $ctx['user']);
        $config->set($ctx['organization']->id, 'editorial.ai.article_instructions', ['value' => 'Saved article instructions.'], $ctx['project']->id, $ctx['user']);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.settings.edit'))
            ->assertOk()
            ->assertSee('System prompt', false)
            ->assertSee('Saved system prompt.', false)
            ->assertSee('Saved article instructions.', false)
            ->assertSee('Editorial instructions are project-specific.', false);
    }

    public function test_project_b_does_not_see_project_a_instruction_values(): void
    {
        $ctxA = $this->operatorContext('Shared Org', 'Project A');
        $ctxB = $this->operatorContext('Shared Org B', 'Project B');
        $config = app(ConfigurationService::class);
        $config->set($ctxA['organization']->id, 'editorial.ai.system_prompt', ['value' => 'Project A only system.'], $ctxA['project']->id, $ctxA['user']);
        $config->set($ctxA['organization']->id, 'editorial.ai.article_instructions', ['value' => 'Project A only article.'], $ctxA['project']->id, $ctxA['user']);

        $html = $this->actingAs($ctxB['user'])->withSession($this->sessionFor($ctxB))
            ->get(route('app.settings.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Project A only system.', $html);
        $this->assertStringNotContainsString('Project A only article.', $html);
    }

    public function test_project_b_can_save_different_instructions_without_changing_project_a(): void
    {
        $ctxA = $this->operatorContext('Isolation Org A', 'Project A');
        $ctxB = $this->operatorContext('Isolation Org B', 'Project B');
        $config = app(ConfigurationService::class);
        $config->set($ctxA['organization']->id, 'editorial.ai.system_prompt', ['value' => 'Project A preserved system.'], $ctxA['project']->id, $ctxA['user']);
        $config->set($ctxA['organization']->id, 'editorial.ai.article_instructions', ['value' => 'Project A preserved article.'], $ctxA['project']->id, $ctxA['user']);

        $this->actingAs($ctxB['user'])->withSession($this->sessionFor($ctxB))
            ->post(route('app.settings.ai'), $this->aiPayload([
                'system_prompt' => 'Project B unique system.',
                'article_instructions' => 'Project B unique article.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'AI settings saved.');

        $projectASystem = $config->get($ctxA['organization']->id, 'editorial.ai.system_prompt', $ctxA['project']->id);
        $projectAArticle = $config->get($ctxA['organization']->id, 'editorial.ai.article_instructions', $ctxA['project']->id);
        $projectBSystem = $config->get($ctxB['organization']->id, 'editorial.ai.system_prompt', $ctxB['project']->id);
        $projectBArticle = $config->get($ctxB['organization']->id, 'editorial.ai.article_instructions', $ctxB['project']->id);

        $this->assertSame('Project A preserved system.', $projectASystem?->value['value'] ?? null);
        $this->assertSame('Project A preserved article.', $projectAArticle?->value['value'] ?? null);
        $this->assertSame('Project B unique system.', $projectBSystem?->value['value'] ?? null);
        $this->assertSame('Project B unique article.', $projectBArticle?->value['value'] ?? null);
    }

    public function test_empty_values_clear_existing_project_instructions(): void
    {
        $ctx = $this->operatorContext('Clear Org', 'Project A');
        $config = app(ConfigurationService::class);
        $config->set($ctx['organization']->id, 'editorial.ai.system_prompt', ['value' => 'To be cleared system.'], $ctx['project']->id, $ctx['user']);
        $config->set($ctx['organization']->id, 'editorial.ai.article_instructions', ['value' => 'To be cleared article.'], $ctx['project']->id, $ctx['user']);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.ai'), $this->aiPayload([
                'system_prompt' => '',
                'article_instructions' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'AI settings saved.');

        $system = $config->get($ctx['organization']->id, 'editorial.ai.system_prompt', $ctx['project']->id);
        $article = $config->get($ctx['organization']->id, 'editorial.ai.article_instructions', $ctx['project']->id);

        $this->assertSame('', $system?->value['value'] ?? null);
        $this->assertSame('', $article?->value['value'] ?? null);
    }

    public function test_existing_model_temperature_max_tokens_and_timeout_still_save(): void
    {
        $ctx = $this->operatorContext('Model Org', 'Project A');

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.settings.ai'), $this->aiPayload([
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.9,
                'max_tokens' => 8192,
                'timeout_seconds' => 120,
                'system_prompt' => 'Still saves with model settings.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'AI settings saved.');

        $config = app(ConfigurationService::class);
        $model = $config->get($ctx['organization']->id, 'editorial.ai.model', $ctx['project']->id);
        $temperature = $config->get($ctx['organization']->id, 'editorial.ai.temperature', $ctx['project']->id);
        $maxTokens = $config->get($ctx['organization']->id, 'editorial.ai.max_tokens', $ctx['project']->id);
        $timeout = $config->get($ctx['organization']->id, 'editorial.ai.timeout_seconds', $ctx['project']->id);

        $this->assertSame('gpt-4.1-mini', $model?->value['value'] ?? null);
        $this->assertSame(0.9, $temperature?->value['value'] ?? null);
        $this->assertSame(8192, $maxTokens?->value['value'] ?? null);
        $this->assertSame(120, $timeout?->value['value'] ?? null);
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function operatorContext(string $orgName, string $projectName): array
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-secret'),
        ]);
        $organization = app(OrganizationService::class)->create($user, $orgName);
        $project = app(ProjectService::class)->create($organization, $user, $projectName);

        return compact('user', 'organization', 'project');
    }

    /**
     * @param  array{organization: mixed, project: mixed}  $ctx
     * @return array<string, string>
     */
    private function sessionFor(array $ctx): array
    {
        return [
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function aiPayload(array $overrides = []): array
    {
        return array_merge([
            'model' => 'gpt-5',
            'temperature' => 0.2,
            'max_tokens' => 2048,
            'timeout_seconds' => 60,
            'system_prompt' => '',
            'article_instructions' => '',
        ], $overrides);
    }
}
