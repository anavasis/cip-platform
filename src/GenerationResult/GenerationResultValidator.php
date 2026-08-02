<?php

namespace StudyMentor\ContentEngine\GenerationResult;

defined('ABSPATH') || exit;

/**
 * Structural validator for GenerationResult aggregates.
 * Does not call providers, enqueue jobs, or mutate outcomes.
 */
final class GenerationResultValidator
{
    /**
     * @param GenerationResult $result
     * @return array{valid: bool, complete: bool, errors: array<int, string>}
     */
    public function validate(GenerationResult $result)
    {
        $errors = array();

        if ($result->resultId() === '') {
            $errors[] = 'result_id_required';
        }

        if ($result->requestId() === '') {
            $errors[] = 'request_id_required';
        }

        if ($result->requestHash() === '' || strlen($result->requestHash()) !== 64) {
            $errors[] = 'request_hash_invalid';
        }

        if ($result->announcementId() <= 0) {
            $errors[] = 'announcement_id_required';
        }

        if ($result->packageId() === '') {
            $errors[] = 'package_id_required';
        }

        if ($result->packageHash() === '' || strlen($result->packageHash()) !== 64) {
            $errors[] = 'package_hash_invalid';
        }

        if (!GenerationResultStatus::isValid($result->status())) {
            $errors[] = 'status_invalid';
        }

        if ($result->resultHash() === '' || strlen($result->resultHash()) !== 64) {
            $errors[] = 'result_hash_invalid';
        }

        if ($result->durationMs() < 0) {
            $errors[] = 'duration_ms_invalid';
        }

        $execution = $result->providerExecution();
        if ($execution->executionId() === '') {
            $errors[] = 'execution_id_required';
        }

        if ($execution->providerCode() === '') {
            $errors[] = 'provider_code_required';
        }

        if ($result->status() === GenerationResultStatus::SUCCESS) {
            if ($result->artifacts() === array()) {
                $errors[] = 'artifacts_required';
            }

            foreach ($result->artifacts() as $artifact) {
                if (!$artifact instanceof GeneratedArtifactReference) {
                    $errors[] = 'artifact_invalid';
                    continue;
                }

                if ($artifact->artifactId() === '') {
                    $errors[] = 'artifact_id_required';
                }

                if (
                    $artifact->artifactKind() !== GeneratedArtifactReference::KIND_CONTENT_CANDIDATE
                    && $artifact->artifactKind() !== GeneratedArtifactReference::KIND_STRUCTURED_CANDIDATE
                ) {
                    $errors[] = 'artifact_kind_invalid';
                }
            }

            if ($result->errorCode() !== '') {
                $errors[] = 'error_code_not_allowed_on_success';
            }
        }

        if ($result->status() === GenerationResultStatus::ERROR) {
            if (trim($result->errorCode()) === '') {
                $errors[] = 'error_code_required';
            }

            if ($result->artifacts() !== array()) {
                $errors[] = 'artifacts_not_allowed_on_error';
            }
        }

        $errors = array_values(array_unique($errors));
        $valid = $errors === array();
        $complete = $valid && $result->createdAtUtc() !== '';

        return array(
            'valid' => $valid,
            'complete' => $complete,
            'errors' => $errors,
        );
    }

    /**
     * @param GenerationResult $result
     * @return bool
     */
    public function isStructurallyValid(GenerationResult $result)
    {
        $outcome = $this->validate($result);

        return $outcome['valid'] === true;
    }

    /**
     * @param GenerationResult $result
     * @return bool
     */
    public function isComplete(GenerationResult $result)
    {
        $outcome = $this->validate($result);

        return $outcome['complete'] === true;
    }

    /**
     * Recomputes expected result_hash for the binding payload shape used by the builder.
     *
     * @param GenerationResult $result
     * @return bool
     */
    public function resultHashMatchesBinding(GenerationResult $result)
    {
        if ($result->resultHash() === '' || strlen($result->resultHash()) !== 64) {
            return false;
        }

        $artifacts = array();
        foreach ($result->artifacts() as $artifact) {
            $artifacts[] = $artifact->toArray();
        }

        $payload = array(
            'request_id' => $result->requestId(),
            'request_hash' => $result->requestHash(),
            'announcement_id' => $result->announcementId(),
            'package_id' => $result->packageId(),
            'package_hash' => $result->packageHash(),
            'status' => $result->status(),
            'provider_execution' => $result->providerExecution()->toArray(),
            'artifacts' => $artifacts,
            'error_code' => $result->errorCode(),
            'error_message' => $result->errorMessage(),
            'duration_ms' => $result->durationMs(),
        );

        $expected = $this->hashPayload($payload);

        return hash_equals($expected, $result->resultHash());
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
