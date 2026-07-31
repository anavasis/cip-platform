<?php

namespace StudyMentor\ContentEngine\GenerationRequest;

defined('ABSPATH') || exit;

/**
 * Opaque, provider-independent model catalog reference.
 * Not a vendor SDK binding — id/version only.
 */
final class GenerationModelReference
{
    private $modelId;
    private $modelVersion;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->modelId = isset($data['model_id']) ? trim((string) $data['model_id']) : '';
        $this->modelVersion = isset($data['model_version'])
            ? trim((string) $data['model_version'])
            : '';
    }

    /** @return string */
    public function modelId()
    {
        return $this->modelId;
    }

    /** @return string */
    public function modelVersion()
    {
        return $this->modelVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'model_id' => $this->modelId,
            'model_version' => $this->modelVersion,
        );
    }
}
