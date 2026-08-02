<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Schema.org (or similar) requirement flag — not rendered JSON-LD.
 */
final class SchemaRequirement
{
    private $schemaType;
    private $required;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->schemaType = isset($data['schema_type']) ? (string) $data['schema_type'] : '';
        $this->required = !isset($data['required']) || $data['required'] === true;
    }

    /** @return string */
    public function schemaType()
    {
        return $this->schemaType;
    }

    /** @return bool */
    public function required()
    {
        return $this->required;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'schema_type' => $this->schemaType,
            'required' => $this->required,
        );
    }
}
