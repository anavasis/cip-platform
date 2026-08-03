<?php

namespace App\Modules\Editorial\Domain\GenerationResult;


/**
 * Reference to a generated artifact (not the artifact payload).
 * Provider-independent pointer for later lineage/draft binding.
 */
final class GeneratedArtifactReference
{
    public const KIND_CONTENT_CANDIDATE = 'content_candidate';
    public const KIND_STRUCTURED_CANDIDATE = 'structured_candidate';

    private $artifactId;
    private $artifactKind;
    private $contentHash;
    private $mimeType;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->artifactId = isset($data['artifact_id'])
            ? trim((string) $data['artifact_id'])
            : '';
        $this->artifactKind = isset($data['artifact_kind'])
            ? trim((string) $data['artifact_kind'])
            : self::KIND_CONTENT_CANDIDATE;
        $this->contentHash = isset($data['content_hash'])
            ? (string) $data['content_hash']
            : '';
        $this->mimeType = isset($data['mime_type'])
            ? trim((string) $data['mime_type'])
            : 'text/plain';
    }

    /** @return string */
    public function artifactId()
    {
        return $this->artifactId;
    }

    /** @return string */
    public function artifactKind()
    {
        return $this->artifactKind;
    }

    /** @return string */
    public function contentHash()
    {
        return $this->contentHash;
    }

    /** @return string */
    public function mimeType()
    {
        return $this->mimeType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'artifact_id' => $this->artifactId,
            'artifact_kind' => $this->artifactKind,
            'content_hash' => $this->contentHash,
            'mime_type' => $this->mimeType,
        );
    }
}
