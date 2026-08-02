<?php

namespace StudyMentor\ContentEngine\GenerationRequest;

defined('ABSPATH') || exit;

/**
 * Provider-agnostic generation options at the domain boundary (ADR-001).
 * No vendor-specific keys, HTTP, or SDK options.
 */
final class GenerationParameters
{
    public const FORMAT_TEXT = 'text';
    public const FORMAT_JSON = 'json';

    private $temperature;
    private $maxOutputTokens;
    private $topP;
    private $seed;
    private $responseFormat;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = array())
    {
        $this->temperature = array_key_exists('temperature', $data) && $data['temperature'] !== null
            ? (float) $data['temperature']
            : null;
        $this->maxOutputTokens = array_key_exists('max_output_tokens', $data)
            && $data['max_output_tokens'] !== null
            ? (int) $data['max_output_tokens']
            : null;
        $this->topP = array_key_exists('top_p', $data) && $data['top_p'] !== null
            ? (float) $data['top_p']
            : null;
        $this->seed = array_key_exists('seed', $data) && $data['seed'] !== null
            ? (int) $data['seed']
            : null;
        $this->responseFormat = isset($data['response_format'])
            ? trim((string) $data['response_format'])
            : self::FORMAT_TEXT;
    }

    /** @return float|null */
    public function temperature()
    {
        return $this->temperature;
    }

    /** @return int|null */
    public function maxOutputTokens()
    {
        return $this->maxOutputTokens;
    }

    /** @return float|null */
    public function topP()
    {
        return $this->topP;
    }

    /** @return int|null */
    public function seed()
    {
        return $this->seed;
    }

    /** @return string */
    public function responseFormat()
    {
        return $this->responseFormat;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'temperature' => $this->temperature,
            'max_output_tokens' => $this->maxOutputTokens,
            'top_p' => $this->topP,
            'seed' => $this->seed,
            'response_format' => $this->responseFormat,
        );
    }
}
