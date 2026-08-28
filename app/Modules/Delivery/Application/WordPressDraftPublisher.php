<?php

namespace App\Modules\Delivery\Application;

use App\Application\Services\ConnectorRegistryService;
use App\Application\Services\SecretService;
use App\Infrastructure\Persistence\Models\ProjectConnector;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Facades\Http;

/**
 * Draft-only WordPress REST publisher. Never updates published posts.
 */
final class WordPressDraftPublisher
{
    public const ERROR_CONNECTOR_UNAVAILABLE = 'wordpress_connector_unavailable';

    public const ERROR_ACTION_NOT_CREATE_NEW = 'wordpress_action_not_create_new';

    private const SECRET_KEY = 'wordpress_app_password';

    public function __construct(
        private readonly ConnectorRegistryService $connectors,
        private readonly SecretService $secrets,
    ) {}

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function availability(string $organizationId, string $projectId): array
    {
        $connection = $this->resolveConnection($organizationId, $projectId);

        if ($connection === null) {
            return ['available' => false, 'reason' => self::ERROR_CONNECTOR_UNAVAILABLE];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * @return array{ok: bool, reason: string|null, remote_post_id: string|null, draft_url: string|null}
     */
    public function createDraft(
        string $organizationId,
        string $projectId,
        string $ciAction,
        string $title,
        string $body,
        ?string $slug,
        ContentEntityModel $entity,
        ?User $user = null,
    ): array {
        if ($ciAction !== ContentIntelligencePlan::ACTION_CREATE_NEW) {
            return [
                'ok' => false,
                'reason' => self::ERROR_ACTION_NOT_CREATE_NEW,
                'remote_post_id' => null,
                'draft_url' => null,
            ];
        }

        $connection = $this->resolveConnection($organizationId, $projectId);
        if ($connection === null) {
            return [
                'ok' => false,
                'reason' => self::ERROR_CONNECTOR_UNAVAILABLE,
                'remote_post_id' => null,
                'draft_url' => null,
            ];
        }

        $endpoint = rtrim($connection['site_url'], '/').'/wp-json/wp/v2/posts';
        $payload = [
            'title' => $title,
            'content' => $body,
            'status' => 'draft',
        ];

        $normalizedSlug = trim((string) ($slug ?? ''));
        if ($normalizedSlug !== '') {
            $payload['slug'] = $normalizedSlug;
        }

        $response = Http::withBasicAuth($connection['username'], $connection['password'])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'reason' => 'wordpress_draft_failed',
                'remote_post_id' => null,
                'draft_url' => null,
            ];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [
                'ok' => false,
                'reason' => 'wordpress_draft_invalid_response',
                'remote_post_id' => null,
                'draft_url' => null,
            ];
        }

        $remotePostId = isset($json['id']) ? (string) $json['id'] : '';
        if ($remotePostId === '') {
            return [
                'ok' => false,
                'reason' => 'wordpress_draft_missing_post_id',
                'remote_post_id' => null,
                'draft_url' => null,
            ];
        }

        $draftUrl = isset($json['link']) && is_string($json['link']) ? trim($json['link']) : null;
        $responseSlug = isset($json['slug']) && is_string($json['slug']) ? trim($json['slug']) : $normalizedSlug;

        $this->storeUnconfirmedRemotePostId(
            $organizationId,
            $projectId,
            $entity,
            $remotePostId,
            $responseSlug !== '' ? $responseSlug : null,
            $draftUrl,
        );

        return [
            'ok' => true,
            'reason' => null,
            'remote_post_id' => $remotePostId,
            'draft_url' => $draftUrl,
        ];
    }

    /**
     * @return array{site_url: string, username: string, password: string}|null
     */
    private function resolveConnection(string $organizationId, string $projectId): ?array
    {
        $connector = $this->findWordPressConnector($projectId);
        if ($connector === null) {
            return null;
        }

        $config = is_array($connector->config) ? $connector->config : [];
        $siteUrl = trim((string) ($config['site_url'] ?? $config['wordpress_site_url'] ?? ''));
        if ($siteUrl === '') {
            return null;
        }

        $username = trim((string) ($config['username'] ?? ''));
        $secretKey = trim((string) ($config['secret_key'] ?? self::SECRET_KEY));
        if ($secretKey === '') {
            $secretKey = self::SECRET_KEY;
        }

        $credentialSecret = $this->secrets->list($organizationId, $projectId)
            ->firstWhere('key', $secretKey);

        if ($credentialSecret === null) {
            return null;
        }

        try {
            $revealed = $this->secrets->reveal($credentialSecret, null);
        } catch (\Throwable) {
            return null;
        }

        $password = trim($revealed);
        if ($password === '') {
            return null;
        }

        if ($username === '' && str_contains($password, ':')) {
            [$username, $password] = explode(':', $password, 2);
            $username = trim($username);
            $password = trim($password);
        }

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'site_url' => $siteUrl,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function findWordPressConnector(string $projectId): ?ProjectConnector
    {
        $connectors = $this->connectors->listProjectConnectors($projectId);

        foreach ($connectors as $connector) {
            if ($connector->enabled !== true) {
                continue;
            }

            $config = is_array($connector->config) ? $connector->config : [];
            $provider = strtolower(trim((string) ($config['provider'] ?? '')));
            $name = strtolower(trim((string) $connector->name));

            if ($provider === 'wordpress' || str_contains($name, 'wordpress')) {
                return $connector;
            }
        }

        return null;
    }

    private function storeUnconfirmedRemotePostId(
        string $organizationId,
        string $projectId,
        ContentEntityModel $entity,
        string $remotePostId,
        ?string $slug,
        ?string $draftUrl,
    ): void {
        $binding = RemotePostBindingModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('content_entity_id', $entity->id)
            ->where('remote_system', 'wordpress')
            ->first();

        if ($binding === null) {
            RemotePostBindingModel::create([
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'content_entity_id' => $entity->id,
                'remote_system' => 'wordpress',
                'remote_post_id' => $remotePostId,
                'canonical_url' => $draftUrl !== null && $draftUrl !== ''
                    ? $draftUrl
                    : (string) ($entity->entity_id !== '' ? 'https://pending.local/'.$entity->entity_id : 'https://pending.local/pending'),
                'slug' => $slug,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'bound_at' => now(),
                'last_synced_at' => null,
            ]);

            return;
        }

        if ($binding->confirmed_at !== null) {
            return;
        }

        $binding->remote_post_id = $remotePostId;
        if ($slug !== null && $slug !== '') {
            $binding->slug = $slug;
        }
        $binding->save();
    }
}
