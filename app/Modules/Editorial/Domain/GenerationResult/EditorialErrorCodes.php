<?php

namespace App\Modules\Editorial\Domain\GenerationResult;

/**
 * Stable Editorial generation error codes (no free-text classification).
 */
final class EditorialErrorCodes
{
    public const CAPABILITY_DISABLED = 'capability_disabled';

    public const INVALID_PAYLOAD = 'invalid_payload';

    public const ANNOUNCEMENT_NOT_FOUND = 'announcement_not_found';

    public const BLUEPRINT_INVALID = 'blueprint_invalid';

    public const PROMPT_CONTEXT_INVALID = 'prompt_context_invalid';

    public const PROMPT_PACKAGE_INVALID = 'prompt_package_invalid';

    public const GENERATION_REQUEST_INVALID = 'generation_request_invalid';

    public const PROVIDER_ERROR = 'provider_error';

    public const PROVIDER_EXCEPTION = 'provider_exception';

    public const PROVIDER_CONTENT_TEXT_REQUIRED = 'provider_content_text_required';

    public const PROVIDER_PAYLOAD_INVALID = 'provider_payload_invalid';

    public const GENERATION_RESULT_INVALID = 'generation_result_invalid';

    public const PREVIEW_BUILD_FAILED = 'preview_build_failed';

    public const VALIDATION_FAILED = 'validation_failed';

    public const TENANT_SCOPE_INVALID = 'tenant_scope_invalid';

    public const ANNOUNCEMENT_LOCKED = 'announcement_locked';

    public const LOCK_UNAVAILABLE = 'lock_unavailable';

    public const TRANSIENT_PERSISTENCE_FAILURE = 'transient_persistence_failure';

    public const EDITORIAL_JOB_FAILED = 'editorial_job_failed';

    /**
     * @return list<string>
     */
    public static function permanent(): array
    {
        return [
            self::CAPABILITY_DISABLED,
            self::INVALID_PAYLOAD,
            self::ANNOUNCEMENT_NOT_FOUND,
            self::BLUEPRINT_INVALID,
            self::PROMPT_CONTEXT_INVALID,
            self::PROMPT_PACKAGE_INVALID,
            self::GENERATION_REQUEST_INVALID,
            self::PROVIDER_ERROR,
            self::PROVIDER_EXCEPTION,
            self::PROVIDER_CONTENT_TEXT_REQUIRED,
            self::PROVIDER_PAYLOAD_INVALID,
            self::GENERATION_RESULT_INVALID,
            self::PREVIEW_BUILD_FAILED,
            self::VALIDATION_FAILED,
            self::TENANT_SCOPE_INVALID,
        ];
    }

    /**
     * @return list<string>
     */
    public static function retryable(): array
    {
        return [
            self::ANNOUNCEMENT_LOCKED,
            self::LOCK_UNAVAILABLE,
            self::TRANSIENT_PERSISTENCE_FAILURE,
        ];
    }

    public static function isPermanent(string $code): bool
    {
        return in_array($code, self::permanent(), true);
    }

    public static function isRetryable(string $code): bool
    {
        return in_array($code, self::retryable(), true);
    }

    /**
     * Map exception messages / assertValid prefixes to a stable code.
     */
    public static function fromMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return self::EDITORIAL_JOB_FAILED;
        }

        if (self::isPermanent($message) || self::isRetryable($message) || $message === self::EDITORIAL_JOB_FAILED) {
            return $message;
        }

        foreach (array_merge(self::permanent(), self::retryable()) as $code) {
            if (str_starts_with($message, $code.':')) {
                return $code;
            }
        }

        // Legacy assertValid / provider throwables with exact known tokens.
        if ($message === 'provider_response_invalid') {
            return self::PROVIDER_PAYLOAD_INVALID;
        }

        return self::EDITORIAL_JOB_FAILED;
    }
}
