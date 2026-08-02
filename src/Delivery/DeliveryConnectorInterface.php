<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Port for external delivery targets.
 * Implementations live outside Delivery Core (WordPress, Hub, social, etc.).
 * Connectors never build payloads — they consume DeliveryPayloadBuilder output.
 */
interface DeliveryConnectorInterface
{
    /**
     * Stable connector / target id (e.g. wordpress, hub).
     *
     * @return string
     */
    public function id();

    /**
     * Synchronize one connector-neutral payload with the remote target.
     *
     * @param array<string, mixed> $payload
     * @param DeliveryBinding|null $existingBinding
     * @return array{
     *   ok: bool,
     *   external_id?: string,
     *   delivery_state?: string,
     *   error_code?: string,
     *   error_message?: string
     * }
     */
    public function deliver(array $payload, $existingBinding);
}
