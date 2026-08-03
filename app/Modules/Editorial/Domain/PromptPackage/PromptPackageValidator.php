<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Structural validator for PromptPackage aggregates.
 * Does not render prompts, call providers, or create Generation Requests.
 */
final class PromptPackageValidator
{
    /**
     * @param PromptPackage $package
     * @return array{valid: bool, sealed: bool, errors: array<int, string>}
     */
    public function validate(PromptPackage $package)
    {
        $errors = array();

        if ($package->packageId() === '') {
            $errors[] = 'package_id_required';
        }

        if ($package->announcementId() === '') {
            $errors[] = 'announcement_id_required';
        }

        if ($package->contextId() === '') {
            $errors[] = 'context_id_required';
        }

        if ($package->contextHash() === '' || strlen($package->contextHash()) !== 64) {
            $errors[] = 'context_hash_invalid';
        }

        if (!PromptPackageStatus::isValid($package->status())) {
            $errors[] = 'status_invalid';
        }

        if ($package->packageHash() === '' || strlen($package->packageHash()) !== 64) {
            $errors[] = 'package_hash_invalid';
        }

        $blueprint = $package->blueprintReference();
        if ($blueprint->blueprintId() === '') {
            $errors[] = 'blueprint_id_required';
        }

        if ($blueprint->blueprintRevision() < 1) {
            $errors[] = 'blueprint_revision_invalid';
        }

        if (
            $blueprint->announcementId() !== ''
            && $package->announcementId() !== ''
            && $blueprint->announcementId() !== $package->announcementId()
        ) {
            $errors[] = 'announcement_id_mismatch';
        }

        $template = $package->templateReference();
        if ($template->templateId() === '') {
            $errors[] = 'template_id_required';
        }

        if ($template->templateVersion() === '') {
            $errors[] = 'template_version_required';
        }

        $errors = array_values(array_unique($errors));
        $valid = $errors === array();
        $sealed = $valid
            && $package->status() === PromptPackageStatus::SEALED
            && $package->sealedAtUtc() !== '';

        return array(
            'valid' => $valid,
            'sealed' => $sealed,
            'errors' => $errors,
        );
    }

    /**
     * @param PromptPackage $package
     * @return bool
     */
    public function isStructurallyValid(PromptPackage $package)
    {
        $result = $this->validate($package);

        return $result['valid'] === true;
    }

    /**
     * @param PromptPackage $package
     * @return bool
     */
    public function isSealed(PromptPackage $package)
    {
        $result = $this->validate($package);

        return $result['sealed'] === true;
    }

    /**
     * Recomputes expected package_hash for the binding payload shape used by the builder.
     *
     * @param PromptPackage $package
     * @return bool
     */
    public function packageHashMatchesBinding(PromptPackage $package)
    {
        if ($package->packageHash() === '' || strlen($package->packageHash()) !== 64) {
            return false;
        }

        $payload = array(
            'announcement_id' => $package->announcementId(),
            'context_id' => $package->contextId(),
            'context_hash' => $package->contextHash(),
            'blueprint_reference' => $package->blueprintReference()->toArray(),
            'template_reference' => $package->templateReference()->toArray(),
        );

        $expected = $this->hashPayload($payload);

        return hash_equals($expected, $package->packageHash());
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
