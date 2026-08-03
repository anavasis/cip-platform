<?php

namespace App\Modules\Acquisition\Application;

use App\Application\Services\EventBusService;
use App\Modules\Acquisition\Domain\Events\SourceCreated;
use App\Modules\Acquisition\Domain\Events\SourceDisabled;
use App\Modules\Acquisition\Domain\Events\SourceEnabled;
use App\Modules\Acquisition\Domain\Events\SourceUpdated;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use Illuminate\Support\Str;

final readonly class SourceRegistryService
{
    private const SOURCE_TYPES = ['rss', 'atom', 'html', 'json', 'xml', 'pdf', 'manual'];

    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private SourceRepositoryInterface $repository,
        private ?EventBusService $eventBus = null,
        private ?AcquisitionScheduleRegistrar $scheduleRegistrar = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(string $organizationId, string $projectId, array $input): array
    {
        if (! $this->validTenant($organizationId, $projectId)) {
            return ['success' => false, 'error' => 'invalid_tenant'];
        }

        $slugResult = $this->validateSlug($input['slug'] ?? '');

        if ($slugResult['error'] !== '') {
            return ['success' => false, 'error' => $slugResult['error']];
        }

        $nameResult = $this->validateName($input['name'] ?? '');

        if ($nameResult['error'] !== '') {
            return ['success' => false, 'error' => $nameResult['error']];
        }

        $typeResult = $this->validateSourceType($input['source_type'] ?? '');

        if ($typeResult['error'] !== '') {
            return ['success' => false, 'error' => $typeResult['error']];
        }

        $baseUrlResult = $this->validateOptionalUrl($input['base_url'] ?? '');

        if ($baseUrlResult['error'] !== '') {
            return ['success' => false, 'error' => $baseUrlResult['error']];
        }

        $feedUrlResult = $this->validateRequiredUrl($input['feed_url'] ?? '');

        if ($feedUrlResult['error'] !== '') {
            return ['success' => false, 'error' => $feedUrlResult['error']];
        }

        if ($this->repository->slugExists(
            $organizationId,
            $projectId,
            (string) $slugResult['value'],
        )) {
            return ['success' => false, 'error' => 'duplicate_slug'];
        }

        $feedHash = hash('sha256', (string) $feedUrlResult['value']);

        if ($this->repository->feedHashExists($organizationId, $projectId, $feedHash)) {
            return ['success' => false, 'error' => 'duplicate_feed_url'];
        }

        $domainsResult = $this->validateAllowedDomains($input['allowed_domains'] ?? '');

        if ($domainsResult['error'] !== '') {
            return ['success' => false, 'error' => $domainsResult['error']];
        }

        $parserResult = $this->validateParserProfile($input['parser_profile'] ?? '');

        if ($parserResult['error'] !== '') {
            return ['success' => false, 'error' => 'validation'];
        }

        $intervalResult = $this->validateAcquireInterval($input['acquire_interval_seconds'] ?? 3600);

        if ($intervalResult['error'] !== '') {
            return ['success' => false, 'error' => $intervalResult['error']];
        }

        $utcNow = gmdate('Y-m-d H:i:s');
        $insertId = $this->repository->insert([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slugResult['value'],
            'name' => $nameResult['value'],
            'source_type' => $typeResult['value'],
            'base_url' => $baseUrlResult['value'],
            'feed_url' => $feedUrlResult['value'],
            'feed_url_hash' => $feedHash,
            'allowed_domains' => $domainsResult['value'],
            'parser_profile' => $parserResult['value'],
            'enabled' => $this->booleanValue($input['enabled'] ?? false),
            'manual_only' => $this->booleanValue($input['manual_only'] ?? true),
            'acquire_interval_seconds' => $intervalResult['value'],
            'created_at_utc' => $utcNow,
            'updated_at_utc' => $utcNow,
        ]);

        if ($insertId === false) {
            return ['success' => false, 'error' => 'database'];
        }

        $this->eventBus?->dispatch(new SourceCreated(
            $organizationId,
            $projectId,
            (string) $insertId,
            (string) $slugResult['value'],
            (string) $typeResult['value'],
        ));

        if ($this->booleanValue($input['enabled'] ?? false)) {
            $this->scheduleRegistrar?->ensureForProject($organizationId, $projectId);
            $this->eventBus?->dispatch(new SourceEnabled(
                $organizationId,
                $projectId,
                (string) $insertId,
            ));
        }

        return ['success' => true, 'id' => $insertId];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(
        string $organizationId,
        string $projectId,
        string $id,
        array $input,
    ): array {
        $sourceId = trim($id);

        if (! $this->validTenant($organizationId, $projectId)) {
            return ['success' => false, 'error' => 'invalid_tenant'];
        }

        if ($sourceId === '') {
            return ['success' => false, 'error' => 'invalid_id'];
        }

        $existing = $this->repository->findById($organizationId, $projectId, $sourceId);

        if ($existing === null) {
            return ['success' => false, 'error' => 'not_found'];
        }

        $nameResult = $this->validateName($input['name'] ?? '');

        if ($nameResult['error'] !== '') {
            return ['success' => false, 'error' => $nameResult['error']];
        }

        $typeResult = $this->validateSourceType($input['source_type'] ?? '');

        if ($typeResult['error'] !== '') {
            return ['success' => false, 'error' => $typeResult['error']];
        }

        $baseUrlResult = $this->validateOptionalUrl($input['base_url'] ?? '');

        if ($baseUrlResult['error'] !== '') {
            return ['success' => false, 'error' => $baseUrlResult['error']];
        }

        $feedUrlResult = $this->validateRequiredUrl($input['feed_url'] ?? '');

        if ($feedUrlResult['error'] !== '') {
            return ['success' => false, 'error' => $feedUrlResult['error']];
        }

        $feedHash = hash('sha256', (string) $feedUrlResult['value']);

        if ($this->repository->feedHashExists(
            $organizationId,
            $projectId,
            $feedHash,
            $sourceId,
        )) {
            return ['success' => false, 'error' => 'duplicate_feed_url'];
        }

        $domainsResult = $this->validateAllowedDomains($input['allowed_domains'] ?? '');

        if ($domainsResult['error'] !== '') {
            return ['success' => false, 'error' => $domainsResult['error']];
        }

        $parserResult = $this->validateParserProfile($input['parser_profile'] ?? '');

        if ($parserResult['error'] !== '') {
            return ['success' => false, 'error' => 'validation'];
        }

        $intervalResult = $this->validateAcquireInterval(
            $input['acquire_interval_seconds'] ?? ($existing['acquire_interval_seconds'] ?? 3600),
        );

        if ($intervalResult['error'] !== '') {
            return ['success' => false, 'error' => $intervalResult['error']];
        }

        $updates = [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'name' => $nameResult['value'],
            'source_type' => $typeResult['value'],
            'base_url' => $baseUrlResult['value'],
            'feed_url' => $feedUrlResult['value'],
            'feed_url_hash' => $feedHash,
            'allowed_domains' => $domainsResult['value'],
            'parser_profile' => $parserResult['value'],
            'manual_only' => $this->booleanValue($input['manual_only'] ?? ($existing['manual_only'] ?? true)),
            'acquire_interval_seconds' => $intervalResult['value'],
            'updated_at_utc' => gmdate('Y-m-d H:i:s'),
        ];

        if (array_key_exists('enabled', $input)) {
            $updates['enabled'] = $this->booleanValue($input['enabled']);
        }

        $updated = $this->repository->update($sourceId, $updates);

        if ($updated) {
            if (($updates['enabled'] ?? false) === true) {
                $this->scheduleRegistrar?->ensureForProject($organizationId, $projectId);
            }

            $changedFields = [];

            foreach (array_keys($updates) as $field) {
                if (! in_array($field, ['organization_id', 'project_id', 'updated_at_utc'], true)) {
                    $changedFields[] = $field;
                }
            }

            $this->eventBus?->dispatch(new SourceUpdated(
                $organizationId,
                $projectId,
                $sourceId,
                $changedFields,
            ));

            if (array_key_exists('enabled', $updates)
                && (bool) ($existing['enabled'] ?? false) !== $updates['enabled']) {
                $this->dispatchToggleEvent($organizationId, $projectId, $sourceId, $updates['enabled']);
            }
        }

        return $updated
            ? ['success' => true, 'id' => $sourceId]
            : ['success' => false, 'error' => 'database'];
    }

    /** @return array<string, mixed> */
    public function toggle(
        string $organizationId,
        string $projectId,
        string $id,
        bool $enabled,
    ): array {
        $sourceId = trim($id);

        if (! $this->validTenant($organizationId, $projectId)) {
            return ['success' => false, 'error' => 'invalid_tenant'];
        }

        if ($sourceId === '') {
            return ['success' => false, 'error' => 'invalid_id'];
        }

        if ($this->repository->findById($organizationId, $projectId, $sourceId) === null) {
            return ['success' => false, 'error' => 'not_found'];
        }

        if (! $this->repository->setEnabled($organizationId, $projectId, $sourceId, $enabled)) {
            return ['success' => false, 'error' => 'database'];
        }

        if ($enabled) {
            $this->scheduleRegistrar?->ensureForProject($organizationId, $projectId);
        }

        $this->dispatchToggleEvent($organizationId, $projectId, $sourceId, $enabled);

        return ['success' => true, 'id' => $sourceId, 'enabled' => $enabled];
    }

    public function decodeAllowedDomainsForDisplay(mixed $jsonValue): string
    {
        $decoded = is_array($jsonValue)
            ? $jsonValue
            : (is_string($jsonValue) && $jsonValue !== '' ? json_decode($jsonValue, true) : []);

        if (! is_array($decoded)) {
            return '';
        }

        return implode("\n", array_values(array_filter(
            $decoded,
            static fn (mixed $domain): bool => is_string($domain) && $domain !== '',
        )));
    }

    /** @return array{value: mixed, error: string} */
    private function validateSlug(mixed $rawSlug): array
    {
        $slug = Str::slug((string) $rawSlug);

        if ($slug === '' || strlen($slug) > 128 || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return ['value' => '', 'error' => 'invalid_slug'];
        }

        return ['value' => $slug, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateName(mixed $rawName): array
    {
        $name = strip_tags((string) $rawName);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name);

        if ($name === '' || strlen($name) > 191) {
            return ['value' => '', 'error' => 'invalid_name'];
        }

        return ['value' => $name, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateSourceType(mixed $rawType): array
    {
        $type = strtolower((string) $rawType);
        $type = preg_replace('/[^a-z0-9_\-]/', '', $type) ?? '';

        if ($type === '' || ! in_array($type, self::SOURCE_TYPES, true)) {
            return ['value' => '', 'error' => 'invalid_source_type'];
        }

        return ['value' => $type, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateOptionalUrl(mixed $rawUrl): array
    {
        $trimmed = trim((string) $rawUrl);

        if ($trimmed === '') {
            return ['value' => null, 'error' => ''];
        }

        $normalized = $this->normalizeUrl($trimmed);

        return $normalized === ''
            ? ['value' => '', 'error' => 'invalid_base_url']
            : ['value' => $normalized, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateRequiredUrl(mixed $rawUrl): array
    {
        $normalized = $this->normalizeUrl(trim((string) $rawUrl));

        return $normalized === ''
            ? ['value' => '', 'error' => 'invalid_feed_url']
            : ['value' => $normalized, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateParserProfile(mixed $rawProfile): array
    {
        $profile = strtolower((string) $rawProfile);
        $profile = preg_replace('/[^a-z0-9_\-]/', '', $profile) ?? '';

        if ($profile === '') {
            return ['value' => null, 'error' => ''];
        }

        return strlen($profile) > 64
            ? ['value' => '', 'error' => 'validation']
            : ['value' => $profile, 'error' => ''];
    }

    /** @return array{value: int, error: string} */
    private function validateAcquireInterval(mixed $rawInterval): array
    {
        $interval = filter_var($rawInterval, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 31536000,
            ],
        ]);

        return $interval === false
            ? ['value' => 0, 'error' => 'invalid_acquire_interval']
            : ['value' => $interval, 'error' => ''];
    }

    /** @return array{value: mixed, error: string} */
    private function validateAllowedDomains(mixed $rawDomains): array
    {
        $lines = is_array($rawDomains)
            ? $rawDomains
            : preg_split('/\R/', (string) $rawDomains);
        $normalized = [];
        $seen = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            $domain = $this->normalizeDomainLine($line);

            if ($domain === '') {
                continue;
            }

            if (str_contains($domain, '*') || ! $this->isValidHostname($domain)) {
                return ['value' => '', 'error' => 'invalid_domain'];
            }

            if (! isset($seen[$domain])) {
                $seen[$domain] = true;
                $normalized[] = $domain;
            }
        }

        $json = json_encode($normalized);

        return ['value' => is_string($json) ? $json : '[]', 'error' => ''];
    }

    private function normalizeDomainLine(mixed $line): string
    {
        $domain = strtolower(trim((string) $line));

        if ($domain === '') {
            return '';
        }

        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);

            if (is_array($parsed) && isset($parsed['host'])) {
                $domain = strtolower((string) $parsed['host']);
            }
        }

        $domain = preg_replace('#/.*$#', '', $domain) ?? '';
        $domain = preg_replace('#\?.*$#', '', $domain) ?? '';
        $domain = preg_replace('#\#.*$#', '', $domain) ?? '';

        return trim(rtrim($domain, '.'));
    }

    private function isValidHostname(string $hostname): bool
    {
        return $hostname !== ''
            && strlen($hostname) <= 253
            && filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function normalizeUrl(string $url): string
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])
            || isset($parsed['user']) || isset($parsed['pass'])) {
            return '';
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $host = strtolower((string) $parsed['host']);
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if (($port === 80 && $scheme === 'http') || ($port === 443 && $scheme === 'https')) {
            $port = null;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';

        if ($path !== '') {
            $path = preg_replace('#/+#', '/', $path) ?? '';

            if ($path !== '/' && str_ends_with($path, '/')) {
                $path = rtrim($path, '/');
            }
        }

        return $scheme.'://'.$host
            .($port !== null ? ':'.$port : '')
            .$path
            .(isset($parsed['query']) ? '?'.$parsed['query'] : '');
    }

    private function validTenant(string $organizationId, string $projectId): bool
    {
        return trim($organizationId) !== '' && trim($projectId) !== '';
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function dispatchToggleEvent(
        string $organizationId,
        string $projectId,
        string $sourceId,
        bool $enabled,
    ): void {
        $event = $enabled
            ? new SourceEnabled($organizationId, $projectId, $sourceId)
            : new SourceDisabled($organizationId, $projectId, $sourceId);

        $this->eventBus?->dispatch($event);
    }
}
