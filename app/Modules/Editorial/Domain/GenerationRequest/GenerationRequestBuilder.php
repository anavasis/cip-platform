<?php

namespace App\Modules\Editorial\Domain\GenerationRequest;

use App\Modules\Editorial\Domain\PromptPackage\PromptPackage;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageStatus;


/**
 * Builds a provider-independent Generation Request from a sealed Prompt Package.
 * No HTTP, providers, queues, workers, retries, or Generation Result.
 */
final class GenerationRequestBuilder
{
    /**
     * @param PromptPackage $package
     * @param GenerationModelReference $modelReference
     * @param GenerationParameters|null $parameters
     * @param array<string, mixed> $overrides
     * @return GenerationRequest
     *
     * @throws \InvalidArgumentException when package is not sealed or identity is incomplete
     */
    public function buildFromPackage(
        PromptPackage $package,
        GenerationModelReference $modelReference,
        GenerationParameters $parameters = null,
        array $overrides = array()
    ) {
        $this->assertPackageReady($package);
        $this->assertModelReference($modelReference);

        if ($parameters === null) {
            $parameters = new GenerationParameters(array());
        }

        $this->assertParameters($parameters);

        $lineageId = isset($overrides['lineage_id'])
            ? (string) $overrides['lineage_id']
            : '';

        $bindingPayload = $this->bindingPayload(
            $package,
            $modelReference,
            $parameters,
            $lineageId
        );
        $requestHash = $this->hashPayload($bindingPayload);
        $now = $this->utcNow();

        $data = array(
            'request_id' => isset($overrides['request_id'])
                ? (string) $overrides['request_id']
                : $this->newRequestId($package->announcementId(), $requestHash),
            'announcement_id' => $package->announcementId(),
            'lineage_id' => $lineageId,
            'package_id' => $package->packageId(),
            'package_hash' => $package->packageHash(),
            'model_reference' => $modelReference,
            'parameters' => $parameters,
            'status' => GenerationRequestStatus::READY,
            'request_hash' => $requestHash,
            'created_at_utc' => $now,
        );

        return new GenerationRequest($data);
    }

    /**
     * @param PromptPackage $package
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertPackageReady(PromptPackage $package)
    {
        if ($package->packageId() === '') {
            throw new \InvalidArgumentException('package_id_required');
        }

        if ($package->announcementId() === '') {
            throw new \InvalidArgumentException('announcement_id_required');
        }

        if ($package->packageHash() === '' || strlen($package->packageHash()) !== 64) {
            throw new \InvalidArgumentException('package_hash_invalid');
        }

        if ($package->status() !== PromptPackageStatus::SEALED) {
            throw new \InvalidArgumentException('package_not_sealed');
        }
    }

    /**
     * @param GenerationModelReference $modelReference
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertModelReference(GenerationModelReference $modelReference)
    {
        if ($modelReference->modelId() === '') {
            throw new \InvalidArgumentException('model_id_required');
        }
    }

    /**
     * @param GenerationParameters $parameters
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertParameters(GenerationParameters $parameters)
    {
        $format = $parameters->responseFormat();
        if (
            $format !== GenerationParameters::FORMAT_TEXT
            && $format !== GenerationParameters::FORMAT_JSON
        ) {
            throw new \InvalidArgumentException('response_format_invalid');
        }

        if ($parameters->temperature() !== null) {
            $temperature = $parameters->temperature();
            if ($temperature < 0.0 || $temperature > 2.0) {
                throw new \InvalidArgumentException('temperature_out_of_range');
            }
        }

        if ($parameters->topP() !== null) {
            $topP = $parameters->topP();
            if ($topP < 0.0 || $topP > 1.0) {
                throw new \InvalidArgumentException('top_p_out_of_range');
            }
        }

        if ($parameters->maxOutputTokens() !== null && $parameters->maxOutputTokens() < 1) {
            throw new \InvalidArgumentException('max_output_tokens_invalid');
        }
    }

    /**
     * Canonical binding payload for deterministic request_hash.
     * Excludes request_id and timestamps.
     *
     * @param PromptPackage $package
     * @param GenerationModelReference $modelReference
     * @param GenerationParameters $parameters
     * @param string $lineageId
     * @return array<string, mixed>
     */
    private function bindingPayload(
        PromptPackage $package,
        GenerationModelReference $modelReference,
        GenerationParameters $parameters,
        $lineageId
    ) {
        return array(
            'announcement_id' => $package->announcementId(),
            'lineage_id' => (string) $lineageId,
            'package_id' => $package->packageId(),
            'package_hash' => $package->packageHash(),
            'model_reference' => $modelReference->toArray(),
            'parameters' => $parameters->toArray(),
        );
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

    /**
     * @param string $announcementId
     * @param string $requestHash
     * @return string
     */
    private function newRequestId($announcementId, $requestHash)
    {
        return 'gr_' . $announcementId . '_' . substr((string) $requestHash, 0, 12);
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
