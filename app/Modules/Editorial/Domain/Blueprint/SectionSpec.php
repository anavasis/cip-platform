<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Required section specification.
 */
final class SectionSpec
{
    private $sectionKey;
    private $purpose;
    private $required;
    private $minWords;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->sectionKey = isset($data['section_key']) ? (string) $data['section_key'] : '';
        $this->purpose = isset($data['purpose']) ? (string) $data['purpose'] : '';
        $this->required = !isset($data['required']) || $data['required'] === true;
        $this->minWords = isset($data['min_words']) ? (int) $data['min_words'] : 0;
    }

    /** @return string */
    public function sectionKey()
    {
        return $this->sectionKey;
    }

    /** @return string */
    public function purpose()
    {
        return $this->purpose;
    }

    /** @return bool */
    public function required()
    {
        return $this->required;
    }

    /** @return string */
    public function minWords()
    {
        return $this->minWords;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'section_key' => $this->sectionKey,
            'purpose' => $this->purpose,
            'required' => $this->required,
            'min_words' => $this->minWords,
        );
    }
}
