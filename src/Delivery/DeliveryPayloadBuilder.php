<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Builds connector-neutral delivery payloads.
 * Connectors must not construct payloads — they only consume this output.
 */
final class DeliveryPayloadBuilder
{
    public const SCHEMA_VERSION = '1.0.0';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function build(array $input)
    {
        $projectId = isset($input['project_id']) ? trim((string) $input['project_id']) : '';
        $announcementId = isset($input['announcement_id']) ? (int) $input['announcement_id'] : 0;
        $target = isset($input['target']) ? trim((string) $input['target']) : '';
        $revision = isset($input['revision']) ? (int) $input['revision'] : 0;
        $identityHash = isset($input['identity_hash'])
            ? trim((string) $input['identity_hash'])
            : '';
        $contentHash = isset($input['content_hash'])
            ? trim((string) $input['content_hash'])
            : '';

        $idempotencyKey = isset($input['idempotency_key'])
            ? trim((string) $input['idempotency_key'])
            : '';

        if ($idempotencyKey === '') {
            $idempotencyKey = $this->buildIdempotencyKey(
                $projectId,
                $announcementId,
                $target,
                $identityHash !== '' ? $identityHash : $contentHash
            );
        }

        $artifact = array(
            'preview_id' => isset($input['preview_id']) ? (string) $input['preview_id'] : '',
            'request_id' => isset($input['request_id']) ? (string) $input['request_id'] : '',
            'result_id' => isset($input['result_id']) ? (string) $input['result_id'] : '',
        );

        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'project_id' => $projectId,
            'announcement_id' => $announcementId,
            'target' => $target,
            'revision' => $revision,
            'idempotency_key' => $idempotencyKey,
            'title' => isset($input['title']) ? (string) $input['title'] : '',
            'body' => isset($input['body']) ? (string) $input['body'] : '',
            'identity_hash' => $identityHash,
            'content_hash' => $contentHash,
            'artifact' => $artifact,
            'metadata' => isset($input['metadata']) && is_array($input['metadata'])
                ? $input['metadata']
                : array(),
        );
    }

    /**
     * Deterministic create-key: stable across revisions for the same announcement×target identity.
     *
     * @param string $projectId
     * @param int $announcementId
     * @param string $target
     * @param string $stableIdentity
     * @return string
     */
    public function buildIdempotencyKey($projectId, $announcementId, $target, $stableIdentity)
    {
        $material = (string) $projectId
            . "\0"
            . (string) (int) $announcementId
            . "\0"
            . (string) $target
            . "\0"
            . (string) $stableIdentity;

        return hash('sha256', $material);
    }
}
