<?php

namespace Tests\Unit\Modules\Editorial\Generation;

use App\Application\Services\ConfigurationService;
use App\Application\Services\ProjectService;
use App\Application\Services\SecretService;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Infrastructure\Generation\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_gpt5_request_omits_temperature_and_includes_max_completion_tokens(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        config(['editorial.ai.openai.model' => 'gpt-5', 'editorial.ai.openai.temperature' => 0.2]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-gpt5',
                'choices' => [['message' => ['content' => "Title: Hello\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);
        $this->assertSame("Title: Hello\n\nBody", $out['content_text']);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return ($data['model'] ?? null) === 'gpt-5'
                && array_key_exists('max_completion_tokens', $data)
                && ! array_key_exists('temperature', $data);
        });
    }

    public function test_temperature_capable_model_includes_temperature(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        config([
            'editorial.ai.openai.model' => 'gpt-5-chat-latest',
            'editorial.ai.openai.temperature' => 0.2,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-chat',
                'choices' => [['message' => ['content' => "Title: Chat\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return ($data['model'] ?? null) === 'gpt-5-chat-latest'
                && array_key_exists('temperature', $data)
                && (float) $data['temperature'] === 0.2
                && array_key_exists('max_completion_tokens', $data);
        });
    }

    public function test_missing_key_fails_closed(): void
    {
        $ctx = $this->seedAnnouncementWithoutKey();
        config(['editorial.ai.openai.api_key' => null]);
        Http::fake();

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_ERROR, $out['error_code']);
        Http::assertNothingSent();
    }

    public function test_401_is_normalized_without_secret_leak(): void
    {
        $ctx = $this->seedAnnouncementWithKey('sk-secret-value-should-not-leak');
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'bad']], 401)]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_ERROR, $out['error_code']);
        $this->assertStringContainsString('HTTP 401', $out['error_message']);
        $this->assertStringNotContainsString('sk-secret-value-should-not-leak', json_encode($out));
        $this->assertStringNotContainsString('bad', $out['error_message']);
    }

    public function test_429_is_retryable_then_fails_closed(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        config(['editorial.ai.openai.retries' => 1]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'rate'], 429)]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertFalse($out['ok']);
        $this->assertStringContainsString('HTTP 429', $out['error_message']);
        Http::assertSentCount(2);
    }

    public function test_5xx_is_retryable_then_fails_closed(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        config(['editorial.ai.openai.retries' => 1]);
        Http::fake(['api.openai.com/*' => Http::response('error', 503)]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertFalse($out['ok']);
        $this->assertStringContainsString('HTTP 503', $out['error_message']);
        Http::assertSentCount(2);
    }

    public function test_malformed_success_payload_fails_safely(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-empty',
                'choices' => [['message' => ['content' => '   ']]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED, $out['error_code']);
        $this->assertArrayNotHasKey('content_text', $out);
    }

    public function test_valid_response_extracts_content_text(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-ok',
                'choices' => [['message' => ['content' => "Title: Extracted\n\nArticle body"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);
        $this->assertSame("Title: Extracted\n\nArticle body", $out['content_text']);
        $this->assertSame(OpenAiProvider::PROVIDER_CODE, $out['provider_code']);
    }

    public function test_config_fallback_key_resolves_without_runtime_env_call(): void
    {
        $ctx = $this->seedAnnouncementWithoutKey();
        config(['editorial.ai.openai.api_key' => 'sk-from-config-fallback']);

        $source = file_get_contents((new \ReflectionClass(OpenAiProvider::class))->getFileName());
        $this->assertStringNotContainsString("env('OPENAI_API_KEY')", $source);
        $this->assertStringNotContainsString('env("OPENAI_API_KEY")', $source);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-fallback',
                'choices' => [['message' => ['content' => "Title: Fallback\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-from-config-fallback'));
    }

    public function test_supports_temperature_model_matrix(): void
    {
        $provider = $this->provider();
        $this->assertFalse($provider->supportsTemperature('gpt-5'));
        $this->assertFalse($provider->supportsTemperature('gpt-5-mini'));
        $this->assertFalse($provider->supportsTemperature('o3-mini'));
        $this->assertTrue($provider->supportsTemperature('gpt-5-chat-latest'));
        $this->assertTrue($provider->supportsTemperature('gpt-4.1'));
    }

    public function test_project_system_prompt_is_sent_to_openai(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctx, 'editorial.ai.system_prompt', 'Project A trusted system prompt.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-custom-system',
                'choices' => [['message' => ['content' => "Title: Custom\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);

        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];

            return ($messages[0]['content'] ?? null) === 'Project A trusted system prompt.';
        });
    }

    public function test_project_article_instructions_are_sent_to_openai(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctx, 'editorial.ai.article_instructions', 'Use a formal tone and three sections.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-custom-instructions',
                'choices' => [['message' => ['content' => "Title: Custom\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);

        Http::assertSent(function (Request $request) {
            $userContent = $request->data()['messages'][1]['content'] ?? '';

            return str_contains($userContent, 'Trusted project editorial instructions:')
                && str_contains($userContent, 'Use a formal tone and three sections.');
        });
    }

    public function test_different_projects_receive_different_instructions(): void
    {
        $ctxA = $this->seedAnnouncementWithKey();
        $ctxB = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctxA, 'editorial.ai.system_prompt', 'Project A system only.');
        $this->setProjectConfig($ctxB, 'editorial.ai.system_prompt', 'Project B system only.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-project-b',
                'choices' => [['message' => ['content' => "Title: B\n\nBody"]]],
            ], 200),
        ]);

        $this->provider()->generate($this->requestFor($ctxB['announcement']->id));

        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];

            return ($messages[0]['content'] ?? null) === 'Project B system only.'
                && ! str_contains((string) ($messages[1]['content'] ?? ''), 'Project A system only.');
        });
    }

    public function test_project_a_instructions_never_appear_in_project_b_request(): void
    {
        $ctxA = $this->seedAnnouncementWithKey();
        $ctxB = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctxA, 'editorial.ai.system_prompt', 'Project A exclusive system prompt.');
        $this->setProjectConfig($ctxA, 'editorial.ai.article_instructions', 'Project A exclusive article rules.');
        $this->setProjectConfig($ctxB, 'editorial.ai.system_prompt', 'Project B exclusive system prompt.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-isolation',
                'choices' => [['message' => ['content' => "Title: Isolated\n\nBody"]]],
            ], 200),
        ]);

        $this->provider()->generate($this->requestFor($ctxB['announcement']->id));

        Http::assertSent(function (Request $request) {
            $payload = json_encode($request->data());

            return str_contains($payload, 'Project B exclusive system prompt.')
                && ! str_contains($payload, 'Project A exclusive system prompt.')
                && ! str_contains($payload, 'Project A exclusive article rules.');
        });
    }

    public function test_absent_instructions_preserve_generic_preview_fallback(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $request = $this->requestFor($ctx['announcement']->id);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-fallback',
                'choices' => [['message' => ['content' => "Title: Fallback\n\nBody"]]],
            ], 200),
        ]);

        $this->provider()->generate($request);

        Http::assertSent(function (Request $httpRequest) use ($ctx, $request) {
            $messages = $httpRequest->data()['messages'] ?? [];
            $expectedUserPrompt = implode("\n", [
                'Write an article preview for this announcement.',
                'Announcement ID: '.$ctx['announcement']->id,
                'Title: OpenAI Announcement',
                'URL: '.(string) $ctx['announcement']->canonical_url,
                'Revision: 1',
                'Request: '.$request->requestId(),
                'Package: '.$request->packageId(),
                'Source summary: Summary',
            ]);

            return ($messages[0]['content'] ?? null) === 'You are an editorial assistant. Write a clear article preview with a title on the first line prefixed by "Title: ", then a blank line, then the article body in Markdown. Do not include secrets or meta commentary.'
                && ($messages[1]['content'] ?? null) === $expectedUserPrompt;
        });
    }

    public function test_existing_model_temperature_max_tokens_and_api_key_still_work_with_custom_instructions(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $config = app(ConfigurationService::class);
        $config->set($ctx['organization']->id, 'editorial.ai.model', ['value' => 'gpt-5-chat-latest'], $ctx['project']->id, $ctx['user']);
        $config->set($ctx['organization']->id, 'editorial.ai.temperature', ['value' => 0.7], $ctx['project']->id, $ctx['user']);
        $config->set($ctx['organization']->id, 'editorial.ai.max_tokens', ['value' => 4096], $ctx['project']->id, $ctx['user']);
        $this->setProjectConfig($ctx, 'editorial.ai.system_prompt', 'Configured system prompt.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-settings',
                'choices' => [['message' => ['content' => "Title: Settings\n\nBody"]]],
            ], 200),
        ]);

        $out = $this->provider()->generate($this->requestFor($ctx['announcement']->id));
        $this->assertTrue($out['ok']);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return ($data['model'] ?? null) === 'gpt-5-chat-latest'
                && (float) ($data['temperature'] ?? 0) === 0.7
                && ($data['max_completion_tokens'] ?? null) === 4096
                && $request->hasHeader('Authorization', 'Bearer sk-test-key');
        });
    }

    public function test_announcement_source_data_remains_included_with_custom_instructions(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctx, 'editorial.ai.system_prompt', 'Write editorial content.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-source',
                'choices' => [['message' => ['content' => "Title: Source\n\nBody"]]],
            ], 200),
        ]);

        $this->provider()->generate($this->requestFor($ctx['announcement']->id));

        Http::assertSent(function (Request $request) use ($ctx) {
            $userContent = (string) ($request->data()['messages'][1]['content'] ?? '');

            return str_contains($userContent, 'Announcement ID: '.$ctx['announcement']->id)
                && str_contains($userContent, 'Title: OpenAI Announcement')
                && str_contains($userContent, 'URL: '.(string) $ctx['announcement']->canonical_url)
                && str_contains($userContent, 'Revision: 1')
                && str_contains($userContent, 'Source summary: Summary');
        });
    }

    public function test_source_summary_stays_in_untrusted_user_content_not_system_prompt(): void
    {
        $ctx = $this->seedAnnouncementWithKey();
        $this->setProjectConfig($ctx, 'editorial.ai.system_prompt', 'Trusted admin system configuration only.');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-summary',
                'choices' => [['message' => ['content' => "Title: Summary\n\nBody"]]],
            ], 200),
        ]);

        $this->provider()->generate($this->requestFor($ctx['announcement']->id));

        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];
            $systemContent = (string) ($messages[0]['content'] ?? '');
            $userContent = (string) ($messages[1]['content'] ?? '');

            return $systemContent === 'Trusted admin system configuration only.'
                && ! str_contains($systemContent, 'Summary')
                && str_contains($userContent, 'Untrusted source reference material:')
                && str_contains($userContent, 'Source summary: Summary');
        });
    }

    /**
     * @param  array{organization: mixed, project: mixed, user: mixed}  $ctx
     */
    private function setProjectConfig(array $ctx, string $key, string $value): void
    {
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            $key,
            ['value' => $value],
            $ctx['project']->id,
            $ctx['user'],
        );
    }

    /**
     * @return array{announcement: Announcement}
     */
    private function seedAnnouncementWithKey(string $apiKey = 'sk-test-key'): array
    {
        $ctx = $this->seedAnnouncementWithoutKey();
        app(SecretService::class)->create(
            $ctx['organization']->id,
            (string) config('editorial.ai.openai.secret_key'),
            $apiKey,
            $ctx['project']->id,
            $ctx['user'],
        );

        return $ctx;
    }

    /**
     * @return array{user: mixed, organization: mixed, project: mixed, announcement: Announcement}
     */
    private function seedAnnouncementWithoutKey(): array
    {
        ['user' => $user, 'organization' => $organization] = $this->createUserWithOrg();
        $project = app(ProjectService::class)->create($organization, $user, 'OpenAI Project');
        $source = app(SourceRegistryService::class)->create($organization->id, $project->id, [
            'slug' => 'openai-src-'.Str::lower(Str::random(6)),
            'name' => 'OpenAI Src',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/openai-'.uniqid().'.xml',
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);
        $announcement = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source['id'],
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/a-'.uniqid(),
            'raw_title' => 'OpenAI Announcement',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'OpenAI Announcement', 'summary' => 'Summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return compact('user', 'organization', 'project', 'announcement');
    }

    private function provider(): OpenAiProvider
    {
        return new OpenAiProvider(
            app(SecretService::class),
            app(ConfigurationService::class),
        );
    }

    private function requestFor(string $announcementId): GenerationRequest
    {
        return new GenerationRequest([
            'request_id' => 'gr_'.Str::lower(Str::random(12)),
            'announcement_id' => $announcementId,
            'lineage_id' => 'lin_'.Str::lower(Str::random(8)),
            'package_id' => 'pp_'.Str::lower(Str::random(8)),
            'package_hash' => hash('sha256', 'pkg'),
            'model_reference' => [
                'model_id' => 'openai.chat',
                'model_version' => '1',
            ],
            'parameters' => [
                'temperature' => 0.2,
                'max_output_tokens' => 2048,
                'response_format' => 'text',
                'seed' => 1,
            ],
            'status' => 'ready',
            'request_hash' => hash('sha256', 'req'),
            'created_at_utc' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
