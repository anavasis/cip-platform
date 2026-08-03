<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Opaque Prompt Template catalog reference (id + version only).
 * Not template body, prose, or rendering — ADR-001 package binding key.
 */
final class PromptTemplateReference
{
    private $templateId;
    private $templateVersion;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->templateId = isset($data['template_id']) ? trim((string) $data['template_id']) : '';
        $this->templateVersion = isset($data['template_version'])
            ? trim((string) $data['template_version'])
            : '';
    }

    /** @return string */
    public function templateId()
    {
        return $this->templateId;
    }

    /** @return string */
    public function templateVersion()
    {
        return $this->templateVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'template_id' => $this->templateId,
            'template_version' => $this->templateVersion,
        );
    }
}
