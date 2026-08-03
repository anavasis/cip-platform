<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * SEO constraint block (requirements, not generated meta).
 */
final class SeoConstraints
{
    private $primaryTopic;
    /** @var array<int, string> */
    private $mustIncludeTerms;
    /** @var array<int, string> */
    private $avoidTerms;
    private $metaDescriptionRequired;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->primaryTopic = isset($data['primary_topic']) ? (string) $data['primary_topic'] : '';
        $this->mustIncludeTerms = $this->stringList(
            isset($data['must_include_terms']) ? $data['must_include_terms'] : array()
        );
        $this->avoidTerms = $this->stringList(
            isset($data['avoid_terms']) ? $data['avoid_terms'] : array()
        );
        $this->metaDescriptionRequired = isset($data['meta_description_required'])
            && $data['meta_description_required'] === true;
    }

    /** @return string */
    public function primaryTopic()
    {
        return $this->primaryTopic;
    }

    /**
     * @return array<int, string>
     */
    public function mustIncludeTerms()
    {
        return $this->mustIncludeTerms;
    }

    /**
     * @return array<int, string>
     */
    public function avoidTerms()
    {
        return $this->avoidTerms;
    }

    /** @return bool */
    public function metaDescriptionRequired()
    {
        return $this->metaDescriptionRequired;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'primary_topic' => $this->primaryTopic,
            'must_include_terms' => $this->mustIncludeTerms,
            'avoid_terms' => $this->avoidTerms,
            'meta_description_required' => $this->metaDescriptionRequired,
        );
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringList($value)
    {
        $out = array();

        if (!is_array($value)) {
            return $out;
        }

        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }

        return $out;
    }
}
