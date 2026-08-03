<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\ConfigurationService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Application\Services\SecretService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Application\CapabilityGate as AcquisitionCapabilityGate;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly ProjectService $projects,
        private readonly FeatureFlagService $flags,
        private readonly SecretService $secrets,
        private readonly SourceRegistryService $sources,
        private readonly ConfigurationService $configuration,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Organization::query()->exists()) {
            return Auth::check()
                ? redirect()->route('app.home')
                : redirect()->route('login');
        }

        return view('app.setup.wizard', [
            'needsAdmin' => User::query()->count() === 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Organization::query()->exists()) {
            return redirect()->route('login')->withErrors(['form' => 'Setup has already been completed.']);
        }

        $validated = $request->validate([
            'admin_name' => ['required_without:use_current_user', 'nullable', 'string', 'max:191'],
            'admin_email' => ['required_without:use_current_user', 'nullable', 'email', 'max:191'],
            'admin_password' => ['required_without:use_current_user', 'nullable', 'string', 'min:8'],
            'organization_name' => ['required', 'string', 'max:191'],
            'project_name' => ['required', 'string', 'max:191'],
            'openai_api_key' => ['nullable', 'string', 'max:4096'],
            'ai_model' => ['nullable', 'string', 'max:128'],
            'ai_temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'ai_max_tokens' => ['nullable', 'integer', 'min:16', 'max:128000'],
            'ai_timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'source_name' => ['nullable', 'string', 'max:191'],
            'source_feed_url' => ['nullable', 'url:http,https', 'max:2048'],
            'source_allowed_domain' => ['nullable', 'string', 'max:253'],
            'enable_editorial' => ['sometimes', 'boolean'],
            'enable_acquisition' => ['sometimes', 'boolean'],
        ]);

        if (Auth::check()) {
            $user = $request->user();
        } else {
            $user = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
            ]);
            Auth::login($user);
            $request->session()->regenerate();
        }

        $organization = $this->organizations->create($user, $validated['organization_name']);
        $project = $this->projects->create($organization, $user, $validated['project_name']);

        $enableEditorial = $request->boolean('enable_editorial', true);
        $enableAcquisition = $request->boolean('enable_acquisition', true);
        $this->flags->upsert(CapabilityGate::EDITORIAL, $enableEditorial, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);
        $this->flags->upsert(CapabilityGate::EDITORIAL_GENERATION, $enableEditorial, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);
        $this->flags->upsert(AcquisitionCapabilityGate::ACQUISITION, $enableAcquisition, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);
        $this->flags->upsert(AcquisitionCapabilityGate::SOURCE_REGISTRY, $enableAcquisition, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);

        foreach ([
            'editorial.ai.model' => $validated['ai_model'] ?? config('editorial.ai.openai.model'),
            'editorial.ai.temperature' => $validated['ai_temperature'] ?? config('editorial.ai.openai.temperature'),
            'editorial.ai.max_tokens' => $validated['ai_max_tokens'] ?? config('editorial.ai.openai.max_tokens'),
            'editorial.ai.timeout_seconds' => $validated['ai_timeout_seconds'] ?? config('editorial.ai.openai.timeout_seconds'),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $this->configuration->set($organization->id, $key, ['value' => $value], $project->id, $user);
            }
        }

        if (! empty($validated['openai_api_key'])) {
            $this->secrets->create(
                $organization->id,
                (string) config('editorial.ai.openai.secret_key'),
                $validated['openai_api_key'],
                $project->id,
                $user,
            );
        }

        if (! empty($validated['source_name']) && ! empty($validated['source_feed_url']) && ! empty($validated['source_allowed_domain'])) {
            $this->sources->create($organization->id, $project->id, [
                'slug' => 'source-'.substr(hash('sha256', $validated['source_feed_url']), 0, 8),
                'name' => $validated['source_name'],
                'source_type' => 'rss',
                'base_url' => $validated['source_feed_url'],
                'feed_url' => $validated['source_feed_url'],
                'allowed_domains' => [$validated['source_allowed_domain']],
                'enabled' => true,
                'manual_only' => false,
                'acquire_interval_seconds' => 3600,
            ]);
        }

        OperatorContext::set($organization->id, $project->id);

        return redirect()->route('app.dashboard')->with('status', 'Initial setup completed.');
    }
}
