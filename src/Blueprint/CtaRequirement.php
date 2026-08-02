<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Call-to-action requirement.
 */
final class CtaRequirement
{
    private $ctaKey;
    private $placement;
    private $required;
    private $labelHint;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->ctaKey = isset($data['cta_key']) ? (string) $data['cta_key'] : '';
        $this->placement = isset($data['placement']) ? (string) $data['placement'] : '';
        $this->required = !isset($data['required']) || $data['required'] === true;
        $this->labelHint = isset($data['label_hint']) ? (string) $data['label_hint'] : '';
    }

    /** @return string */
    public function ctaKey()
    {
        return $this->ctaKey;
    }

    /** @return string */
    public function placement()
    {
        return $this->placement;
    }

    /** @return bool */
    public function required()
    {
        return $this->required;
    }

    /** @return string */
    public function labelHint()
    {
        return $this->labelHint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'cta_key' => $this->ctaKey,
            'placement' => $this->placement,
            'required' => $this->required,
            'label_hint' => $this->labelHint,
        );
    }
}
