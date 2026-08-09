<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\ConfigurationService;
use App\Application\Services\DiagnosticsService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\SecretService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Http\Controllers\Controller;
use App\Modules\Acquisition\Application\CapabilityGate as AcquisitionCapabilityGate;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsWebController extends Controller
{
    public function __construct(
        private readonly ConfigurationService $configuration,
        private readonly SecretService $secrets,
        private readonly FeatureFlagService $flags,
        private readonly DiagnosticsService $diagnostics,
    ) {}

    public function edit(): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        $ai = [
            'driver' => config('editorial.ai.driver'),
            'model' => $this->cfg($org->id, $project->id, 'editorial.ai.model') ?? config('editorial.ai.openai.model'),
            'temperature' => $this->cfg($org->id, $project->id, 'editorial.ai.temperature') ?? config('editorial.ai.openai.temperature'),
            'max_tokens' => $this->cfg($org->id, $project->id, 'editorial.ai.max_tokens') ?? config('editorial.ai.openai.max_tokens'),
            'timeout_seconds' => $this->cfg($org->id, $project->id, 'editorial.ai.timeout_seconds') ?? config('editorial.ai.openai.timeout_seconds'),
            'system_prompt' => (string) ($this->cfg($org->id, $project->id, 'editorial.ai.system_prompt') ?? ''),
            'article_instructions' => (string) ($this->cfg($org->id, $project->id, 'editorial.ai.article_instructions') ?? ''),
        ];

        $secretConfigured = $this->secrets->list($org->id, $project->id)
            ->contains(fn ($s) => $s->key === config('editorial.ai.openai.secret_key'));

        return view('app.settings.edit', [
            'ai' => $ai,
            'secretConfigured' => $secretConfigured,
            'flags' => [
                CapabilityGate::EDITORIAL => $this->flags->isEnabled(CapabilityGate::EDITORIAL, $org->id, $project->id),
                CapabilityGate::EDITORIAL_GENERATION => $this->flags->isEnabled(CapabilityGate::EDITORIAL_GENERATION, $org->id, $project->id),
                AcquisitionCapabilityGate::ACQUISITION => $this->flags->isEnabled(AcquisitionCapabilityGate::ACQUISITION, $org->id, $project->id),
                AcquisitionCapabilityGate::SOURCE_REGISTRY => $this->flags->isEnabled(AcquisitionCapabilityGate::SOURCE_REGISTRY, $org->id, $project->id),
            ],
            'health' => $this->diagnostics->health(),
            'queueConnection' => config('queue.default'),
            'scheduler' => 'cron: * * * * * php artisan platform:schedules:run-due',
        ]);
    }

    public function updateAi(Request $request): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $user = $request->user();
        $validated = $request->validate([
            'model' => ['required', 'string', 'max:128'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:16', 'max:128000'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'system_prompt' => ['nullable', 'string', 'max:12000'],
            'article_instructions' => ['nullable', 'string', 'max:24000'],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);

        foreach ([
            'editorial.ai.model' => $validated['model'],
            'editorial.ai.temperature' => $validated['temperature'],
            'editorial.ai.max_tokens' => $validated['max_tokens'],
            'editorial.ai.timeout_seconds' => $validated['timeout_seconds'],
            'editorial.ai.system_prompt' => $validated['system_prompt'] ?? '',
            'editorial.ai.article_instructions' => $validated['article_instructions'] ?? '',
        ] as $key => $value) {
            $this->configuration->set($org->id, $key, ['value' => $value], $project->id, $user);
        }

        if (! empty($validated['api_key'])) {
            $keyName = (string) config('editorial.ai.openai.secret_key');
            $existing = $this->secrets->list($org->id, $project->id)->firstWhere('key', $keyName);
            if ($existing) {
                $this->secrets->update($existing, $validated['api_key'], $user);
            } else {
                $this->secrets->create($org->id, $keyName, $validated['api_key'], $project->id, $user);
            }
        }

        return back()->with('status', 'AI settings saved.');
    }

    public function updateFlags(Request $request): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $user = $request->user();
        $map = [
            CapabilityGate::EDITORIAL => $request->boolean('editorial'),
            CapabilityGate::EDITORIAL_GENERATION => $request->boolean('editorial_generation'),
            AcquisitionCapabilityGate::ACQUISITION => $request->boolean('acquisition'),
            AcquisitionCapabilityGate::SOURCE_REGISTRY => $request->boolean('source_registry'),
        ];
        foreach ($map as $key => $enabled) {
            $this->flags->upsert($key, $enabled, FeatureFlagScope::Project, null, $org->id, $project->id, $user);
        }

        return back()->with('status', 'Feature flags updated.');
    }

    private function cfg(string $orgId, string $projectId, string $key): mixed
    {
        $entry = $this->configuration->get($orgId, $key, $projectId);
        if ($entry === null) {
            return null;
        }
        $value = $entry->value;

        return is_array($value) ? ($value['value'] ?? null) : $value;
    }
}
