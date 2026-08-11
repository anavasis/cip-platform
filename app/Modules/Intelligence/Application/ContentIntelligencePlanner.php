<?php

namespace App\Modules\Intelligence\Application;

use App\Application\Services\ConfigurationService;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;

/**
 * Deterministic Content Intelligence planner (plan preview only).
 */
final class ContentIntelligencePlanner
{
    public const PROFILE_KEY = 'editorial.content_intelligence_profile';

    private const ALLOWED_MATCH_SCOPES = ['title', 'title_and_body'];

    private const ALLOWED_CONTENT_ROLES = ['hub', 'satellite'];

    private const ALLOWED_PLACEHOLDERS = [
        '{announcement_title}',
        '{entity_label}',
        '{entity_id}',
    ];

    public function __construct(
        private readonly ConfigurationService $configuration,
    ) {}

    /**
     * @return array{valid: bool, errors: array<int, string>, profile: array<string, mixed>|null}
     */
    public function validateProfile(mixed $profile): array
    {
        if (! is_array($profile)) {
            return [
                'valid' => false,
                'errors' => ['Profile root must be a JSON object.'],
                'profile' => null,
            ];
        }

        if ($this->isListArray($profile)) {
            return [
                'valid' => false,
                'errors' => ['Profile root must be a JSON object.'],
                'profile' => null,
            ];
        }

        $errors = [];

        if (! array_key_exists('version', $profile) || (int) $profile['version'] !== 1) {
            $errors[] = 'version must equal 1.';
        }

        if (! array_key_exists('publishing_mode', $profile) || (string) $profile['publishing_mode'] !== 'plan_only') {
            $errors[] = 'publishing_mode must equal "plan_only".';
        }

        if (! array_key_exists('entity_rules', $profile) || ! is_array($profile['entity_rules'])) {
            $errors[] = 'entity_rules must be an array.';
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'profile' => null];
        }

        foreach ($profile['entity_rules'] as $index => $rule) {
            if (! is_array($rule)) {
                $errors[] = "entity_rules[$index] must be an object.";

                continue;
            }

            if (! isset($rule['entity_id']) || trim((string) $rule['entity_id']) === '') {
                $errors[] = "entity_rules[$index].entity_id is required.";
            }

            if (! isset($rule['patterns']) || ! is_array($rule['patterns'])) {
                $errors[] = "entity_rules[$index].patterns must be an array.";
            }

            if (isset($rule['match_scope']) && ! in_array((string) $rule['match_scope'], self::ALLOWED_MATCH_SCOPES, true)) {
                $errors[] = "entity_rules[$index].match_scope must be title or title_and_body.";
            }

            if (isset($rule['content_role']) && ! in_array((string) $rule['content_role'], self::ALLOWED_CONTENT_ROLES, true)) {
                $errors[] = "entity_rules[$index].content_role must be hub or satellite.";
            }
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'profile' => null];
        }

        return ['valid' => true, 'errors' => [], 'profile' => $profile];
    }

    public function planForAnnouncement(
        string $organizationId,
        string $projectId,
        Announcement $announcement,
    ): ContentIntelligencePlan {
        if ((string) $announcement->organization_id !== (string) $organizationId
            || (string) $announcement->project_id !== (string) $projectId) {
            return $this->unresolvedPlan(['announcement_tenant_mismatch']);
        }

        $profile = $this->loadProfile($organizationId, $projectId);

        if ($profile === null) {
            return $this->unresolvedPlan(['content_intelligence_profile_missing']);
        }

        $validation = $this->validateProfile($profile);
        if (! $validation['valid']) {
            return new ContentIntelligencePlan([
                'status' => ContentIntelligencePlan::STATUS_INVALID_PROFILE,
                'confidence' => 'none',
                'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
                'hub_impact' => ContentIntelligencePlan::HUB_IMPACT_NONE,
                'warnings' => array_merge(['invalid_profile'], $validation['errors']),
            ]);
        }

        /** @var array<string, mixed> $validatedProfile */
        $validatedProfile = $validation['profile'];
        $title = trim((string) $announcement->raw_title);
        $body = $this->extractSourceBody(is_array($announcement->raw_payload) ? $announcement->raw_payload : []);

        $matches = [];
        $invalidPatterns = [];

        foreach ($validatedProfile['entity_rules'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $entityId = trim((string) ($rule['entity_id'] ?? ''));
            if ($entityId === '') {
                continue;
            }

            $matchScope = isset($rule['match_scope']) ? (string) $rule['match_scope'] : 'title';
            $haystack = $matchScope === 'title_and_body'
                ? trim($title."\n".$body)
                : $title;

            if ($haystack === '') {
                continue;
            }

            $patterns = is_array($rule['patterns'] ?? null) ? $rule['patterns'] : [];
            foreach ($patterns as $pattern) {
                $pattern = (string) $pattern;
                if ($pattern === '') {
                    continue;
                }

                $regex = $this->compilePattern($pattern);
                if ($regex === null) {
                    $invalidPatterns[] = $pattern;

                    continue;
                }

                if (@preg_match($regex, $haystack) === 1) {
                    $matches[$entityId] = [
                        'rule' => $rule,
                        'matched_pattern' => $pattern,
                    ];
                    break;
                }
            }
        }

        if ($invalidPatterns !== []) {
            return new ContentIntelligencePlan([
                'status' => ContentIntelligencePlan::STATUS_INVALID_PROFILE,
                'confidence' => 'none',
                'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
                'hub_impact' => ContentIntelligencePlan::HUB_IMPACT_NONE,
                'warnings' => array_merge(
                    ['invalid_regex_pattern'],
                    array_values(array_unique($invalidPatterns)),
                ),
            ]);
        }

        if ($matches === []) {
            return $this->unresolvedPlan(['no_entity_rule_matched']);
        }

        if (count($matches) > 1) {
            return new ContentIntelligencePlan([
                'status' => ContentIntelligencePlan::STATUS_AMBIGUOUS,
                'confidence' => 'low',
                'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
                'hub_impact' => ContentIntelligencePlan::HUB_IMPACT_NONE,
                'warnings' => [
                    'ambiguous_entity_resolution',
                    'matched_entities: '.implode(', ', array_keys($matches)),
                ],
            ]);
        }

        $entityId = array_key_first($matches);
        $match = $matches[$entityId];
        /** @var array<string, mixed> $rule */
        $rule = $match['rule'];

        return $this->buildResolvedPlan($rule, $entityId, (string) $match['matched_pattern'], $title);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function buildResolvedPlan(array $rule, string $entityId, string $matchedPattern, string $announcementTitle): ContentIntelligencePlan
    {
        $warnings = [];
        $entityLabel = trim((string) ($rule['label'] ?? $entityId));
        $contentRole = isset($rule['content_role']) ? (string) $rule['content_role'] : 'satellite';

        if (! in_array($contentRole, self::ALLOWED_CONTENT_ROLES, true)) {
            $contentRole = 'satellite';
            $warnings[] = 'unsupported_content_role_defaulted_to_satellite';
        }

        $canonicalTargetUrl = trim((string) ($rule['canonical_target_url'] ?? ''));
        $parentHub = is_array($rule['parent_hub'] ?? null) ? $rule['parent_hub'] : null;

        $parentHubEntityId = $parentHub !== null ? trim((string) ($parentHub['entity_id'] ?? '')) : '';
        $parentHubLabel = $parentHub !== null ? trim((string) ($parentHub['label'] ?? '')) : '';
        $parentHubUrl = $parentHub !== null ? trim((string) ($parentHub['url'] ?? '')) : '';

        $hubImpact = ContentIntelligencePlan::HUB_IMPACT_NONE;
        if ($contentRole === 'hub') {
            $hubImpact = ContentIntelligencePlan::HUB_IMPACT_SELF_UPDATE;
        } elseif ($parentHub !== null && $parentHubEntityId !== '' && $parentHubUrl !== '') {
            $hubImpact = ContentIntelligencePlan::HUB_IMPACT_UPDATE_REQUIRED;
        }

        $publishFlag = $rule['publish'] ?? true;
        if ($publishFlag === false) {
            $action = ContentIntelligencePlan::ACTION_NO_PUBLISH;
        } elseif ($canonicalTargetUrl !== '') {
            $action = ContentIntelligencePlan::ACTION_UPDATE_EXISTING;
        } else {
            $action = ContentIntelligencePlan::ACTION_CREATE_NEW;
        }

        $placeholders = [
            '{announcement_title}' => $announcementTitle,
            '{entity_label}' => $entityLabel,
            '{entity_id}' => $entityId,
        ];

        $seoConfig = is_array($rule['seo'] ?? null) ? $rule['seo'] : [];
        $seoPlan = $this->buildSeoPlan(
            $seoConfig,
            $placeholders,
            $contentRole,
            $entityId,
            $entityLabel,
            $canonicalTargetUrl,
            $parentHubEntityId,
            $parentHubUrl,
            $warnings,
        );

        $internalLinks = $this->buildInternalLinks(
            $contentRole,
            $entityId,
            $entityLabel,
            $canonicalTargetUrl,
            $parentHubEntityId,
            $parentHubLabel,
            $parentHubUrl,
        );

        $publishingOperations = $this->buildPublishingOperations(
            $action,
            $entityId,
            $canonicalTargetUrl,
            $contentRole,
            $hubImpact,
            $parentHubEntityId,
            $parentHubUrl,
        );

        return new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_RESOLVED,
            'confidence' => 'high',
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'matched_pattern' => $matchedPattern,
            'content_role' => $contentRole,
            'action' => $action,
            'canonical_target_url' => $canonicalTargetUrl !== '' ? $canonicalTargetUrl : null,
            'parent_hub_entity_id' => $parentHubEntityId !== '' ? $parentHubEntityId : null,
            'parent_hub_label' => $parentHubLabel !== '' ? $parentHubLabel : null,
            'parent_hub_url' => $parentHubUrl !== '' ? $parentHubUrl : null,
            'hub_impact' => $hubImpact,
            'seo_plan' => $seoPlan,
            'internal_links' => $internalLinks,
            'publishing_operations' => $publishingOperations,
            'warnings' => $warnings,
        ]);
    }

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function buildSeoPlan(
        array $seoConfig,
        array $placeholders,
        string $contentRole,
        string $entityId,
        string $entityLabel,
        string $canonicalTargetUrl,
        string $parentHubEntityId,
        string $parentHubUrl,
        array &$warnings,
    ): array {
        $searchIntent = isset($seoConfig['search_intent']) ? trim((string) $seoConfig['search_intent']) : '';
        if ($searchIntent === '') {
            $warnings[] = 'seo_search_intent_missing';
        }

        $slug = isset($seoConfig['slug']) ? trim((string) $seoConfig['slug']) : '';
        if ($slug === '') {
            $warnings[] = 'seo_slug_missing';
        }

        $seoTitle = $this->applyTemplate(
            isset($seoConfig['seo_title_template']) ? (string) $seoConfig['seo_title_template'] : '',
            $placeholders,
            $warnings,
            'seo_title_template',
        );
        if ($seoTitle === '') {
            $warnings[] = 'seo_title_missing';
        }

        $h1 = $this->applyTemplate(
            isset($seoConfig['h1_template']) ? (string) $seoConfig['h1_template'] : '',
            $placeholders,
            $warnings,
            'h1_template',
        );
        if ($h1 === '') {
            $warnings[] = 'seo_h1_missing';
        }

        $metaDescription = $this->applyTemplate(
            isset($seoConfig['meta_description_template']) ? (string) $seoConfig['meta_description_template'] : '',
            $placeholders,
            $warnings,
            'meta_description_template',
        );
        if ($metaDescription === '') {
            $warnings[] = 'seo_meta_description_missing';
        }

        $internalLinkTargets = [];
        if ($parentHubEntityId !== '' && $parentHubUrl !== '') {
            $internalLinkTargets[] = [
                'entity_id' => $parentHubEntityId,
                'url' => $parentHubUrl,
            ];
        }

        return [
            'search_intent' => $searchIntent !== '' ? $searchIntent : null,
            'seo_title' => $seoTitle !== '' ? $seoTitle : null,
            'h1' => $h1 !== '' ? $h1 : null,
            'slug' => $slug !== '' ? $slug : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'canonical_target_url' => $canonicalTargetUrl !== '' ? $canonicalTargetUrl : null,
            'content_role' => $contentRole,
            'primary_entity' => [
                'entity_id' => $entityId,
                'label' => $entityLabel,
            ],
            'parent_hub' => $parentHubEntityId !== '' ? [
                'entity_id' => $parentHubEntityId,
                'url' => $parentHubUrl !== '' ? $parentHubUrl : null,
            ] : null,
            'internal_link_targets' => $internalLinkTargets,
        ];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<int, string>  $warnings
     */
    private function applyTemplate(string $template, array $placeholders, array &$warnings, string $fieldName): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        if (preg_match_all('/\{[^}]+\}/', $template, $matches)) {
            foreach ($matches[0] as $token) {
                if (! in_array($token, self::ALLOWED_PLACEHOLDERS, true)) {
                    $warnings[] = 'unsupported_template_placeholder_in_'.$fieldName.': '.$token;

                    return '';
                }
            }
        }

        $result = $template;
        foreach ($placeholders as $placeholder => $value) {
            $result = str_replace($placeholder, $value, $result);
        }

        return trim($result);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildInternalLinks(
        string $contentRole,
        string $entityId,
        string $entityLabel,
        string $canonicalTargetUrl,
        string $parentHubEntityId,
        string $parentHubLabel,
        string $parentHubUrl,
    ): array {
        $links = [];

        if ($contentRole === 'satellite' && $parentHubEntityId !== '' && $parentHubUrl !== '') {
            $links[] = [
                'type' => 'satellite_to_hub',
                'target_entity_id' => $parentHubEntityId,
                'target_url' => $parentHubUrl,
                'anchor_text' => $parentHubLabel !== '' ? $parentHubLabel : $parentHubEntityId,
            ];
        }

        if ($contentRole === 'satellite' && $canonicalTargetUrl !== '' && $parentHubEntityId !== '' && $parentHubUrl !== '') {
            $links[] = [
                'type' => 'hub_to_satellite',
                'target_entity_id' => $entityId,
                'target_url' => $canonicalTargetUrl,
                'anchor_text' => $entityLabel,
            ];
        }

        return $links;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPublishingOperations(
        string $action,
        string $entityId,
        string $canonicalTargetUrl,
        string $contentRole,
        string $hubImpact,
        string $parentHubEntityId,
        string $parentHubUrl,
    ): array {
        if ($action === ContentIntelligencePlan::ACTION_NO_PUBLISH) {
            return [];
        }

        $operations = [];

        if ($action === ContentIntelligencePlan::ACTION_UPDATE_EXISTING && $canonicalTargetUrl !== '') {
            $operations[] = [
                'operation' => 'update_existing',
                'entity_id' => $entityId,
                'target_url' => $canonicalTargetUrl,
                'mode' => 'plan_only',
            ];
        }

        if ($action === ContentIntelligencePlan::ACTION_CREATE_NEW) {
            $operations[] = [
                'operation' => 'create_new',
                'entity_id' => $entityId,
                'target_url' => null,
                'mode' => 'plan_only',
            ];
        }

        if ($contentRole === 'satellite' && $hubImpact === ContentIntelligencePlan::HUB_IMPACT_UPDATE_REQUIRED && $parentHubEntityId !== '' && $parentHubUrl !== '') {
            $operations[] = [
                'operation' => 'update_hub',
                'entity_id' => $parentHubEntityId,
                'target_url' => $parentHubUrl,
                'mode' => 'plan_only',
            ];
        }

        return $operations;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function unresolvedPlan(array $warnings): ContentIntelligencePlan
    {
        return new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_UNRESOLVED,
            'confidence' => 'none',
            'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
            'hub_impact' => ContentIntelligencePlan::HUB_IMPACT_NONE,
            'warnings' => $warnings,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadProfile(string $organizationId, string $projectId): ?array
    {
        $entry = $this->configuration->get($organizationId, self::PROFILE_KEY, $projectId);
        if ($entry === null) {
            return null;
        }

        $value = $entry->value;
        if (is_array($value)) {
            if (isset($value['value']) && is_array($value['value'])) {
                return $value['value'];
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSourceBody(array $payload): string
    {
        foreach (['content', 'description', 'summary'] as $key) {
            if (! isset($payload[$key]) || ! is_scalar($payload[$key])) {
                continue;
            }

            $text = trim((string) $payload[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function compilePattern(string $pattern): ?string
    {
        $delimiter = '#';
        $escaped = str_replace($delimiter, '\\'.$delimiter, $pattern);

        $regex = $delimiter.$escaped.$delimiter.'iu';
        if (@preg_match($regex, '') === false) {
            return null;
        }

        return $regex;
    }

  /**
   * @param  array<mixed>  $value
   */
    private function isListArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
