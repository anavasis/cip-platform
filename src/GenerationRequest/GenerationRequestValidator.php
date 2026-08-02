<?php

namespace StudyMentor\ContentEngine\GenerationRequest;

defined('ABSPATH') || exit;

/**
 * Structural validator for GenerationRequest aggregates.
 * Does not execute providers, enqueue jobs, or produce Generation Results.
 */
final class GenerationRequestValidator
{
    /**
     * @param GenerationRequest $request
     * @return array{valid: bool, ready: bool, errors: array<int, string>}
     */
    public function validate(GenerationRequest $request)
    {
        $errors = array();

        if ($request->requestId() === '') {
            $errors[] = 'request_id_required';
        }

        if ($request->announcementId() <= 0) {
            $errors[] = 'announcement_id_required';
        }

        if ($request->packageId() === '') {
            $errors[] = 'package_id_required';
        }

        if ($request->packageHash() === '' || strlen($request->packageHash()) !== 64) {
            $errors[] = 'package_hash_invalid';
        }

        if (!GenerationRequestStatus::isValid($request->status())) {
            $errors[] = 'status_invalid';
        }

        if ($request->requestHash() === '' || strlen($request->requestHash()) !== 64) {
            $errors[] = 'request_hash_invalid';
        }

        $model = $request->modelReference();
        if ($model->modelId() === '') {
            $errors[] = 'model_id_required';
        }

        $parameters = $request->parameters();
        $format = $parameters->responseFormat();
        if (
            $format !== GenerationParameters::FORMAT_TEXT
            && $format !== GenerationParameters::FORMAT_JSON
        ) {
            $errors[] = 'response_format_invalid';
        }

        if ($parameters->temperature() !== null) {
            $temperature = $parameters->temperature();
            if ($temperature < 0.0 || $temperature > 2.0) {
                $errors[] = 'temperature_out_of_range';
            }
        }

        if ($parameters->topP() !== null) {
            $topP = $parameters->topP();
            if ($topP < 0.0 || $topP > 1.0) {
                $errors[] = 'top_p_out_of_range';
            }
        }

        if ($parameters->maxOutputTokens() !== null && $parameters->maxOutputTokens() < 1) {
            $errors[] = 'max_output_tokens_invalid';
        }

        $errors = array_values(array_unique($errors));
        $valid = $errors === array();
        $ready = $valid
            && $request->status() === GenerationRequestStatus::READY
            && $request->createdAtUtc() !== '';

        return array(
            'valid' => $valid,
            'ready' => $ready,
            'errors' => $errors,
        );
    }

    /**
     * @param GenerationRequest $request
     * @return bool
     */
    public function isStructurallyValid(GenerationRequest $request)
    {
        $result = $this->validate($request);

        return $result['valid'] === true;
    }

    /**
     * @param GenerationRequest $request
     * @return bool
     */
    public function isReady(GenerationRequest $request)
    {
        $result = $this->validate($request);

        return $result['ready'] === true;
    }

    /**
     * Recomputes expected request_hash for the binding payload shape used by the builder.
     *
     * @param GenerationRequest $request
     * @return bool
     */
    public function requestHashMatchesBinding(GenerationRequest $request)
    {
        if ($request->requestHash() === '' || strlen($request->requestHash()) !== 64) {
            return false;
        }

        $payload = array(
            'announcement_id' => $request->announcementId(),
            'lineage_id' => $request->lineageId(),
            'package_id' => $request->packageId(),
            'package_hash' => $request->packageHash(),
            'model_reference' => $request->modelReference()->toArray(),
            'parameters' => $request->parameters()->toArray(),
        );

        $expected = $this->hashPayload($payload);

        return hash_equals($expected, $request->requestHash());
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    private function hashPayload(array $payload)
    {
        $canonical = $this->canonicalize($payload);
        $encoded = json_encode($canonical);

        if (!is_string($encoded) || $encoded === '') {
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value !== array() && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = $this->canonicalize($item);
        }

        return $out;
    }
}
