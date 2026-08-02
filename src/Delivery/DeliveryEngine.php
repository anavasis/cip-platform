<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Delivery Engine — sole orchestration authority for external synchronization.
 * No HTTP, REST, WordPress, Hub, social, or newsletter adapters live here.
 */
final class DeliveryEngine
{
    public const VERSION = '1.0.0';

    private $connectorRegistry;
    private $deliveryRegistry;
    private $payloadBuilder;
    private $diagnostics;

    public function __construct(
        DeliveryConnectorRegistry $connectorRegistry,
        DeliveryRegistry $deliveryRegistry,
        DeliveryPayloadBuilder $payloadBuilder,
        DeliveryDiagnostics $diagnostics
    ) {
        $this->connectorRegistry = $connectorRegistry;
        $this->deliveryRegistry = $deliveryRegistry;
        $this->payloadBuilder = $payloadBuilder;
        $this->diagnostics = $diagnostics;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function deliver(array $request)
    {
        $projectId = isset($request['project_id']) ? trim((string) $request['project_id']) : '';
        $announcementId = isset($request['announcement_id']) ? (int) $request['announcement_id'] : 0;
        $target = isset($request['target']) ? trim((string) $request['target']) : '';
        $revision = isset($request['revision']) ? (int) $request['revision'] : 0;

        if ($projectId === '' || $announcementId <= 0 || $target === '' || $revision <= 0) {
            return $this->finish(array(
                'ok' => false,
                'status' => 'failed',
                'error_code' => 'invalid_request',
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'target' => $target,
                'revision' => $revision,
                'external_id' => '',
                'delivery_state' => DeliveryState::FAILED,
                'idempotency_key' => '',
                'binding' => null,
            ));
        }

        $payload = $this->payloadBuilder->build($request);
        $idempotencyKey = (string) $payload['idempotency_key'];

        $existing = $this->deliveryRegistry->find($projectId, $announcementId, $target);
        if ($existing === null) {
            $existing = $this->deliveryRegistry->findByIdempotencyKey($idempotencyKey);
        }

        if (
            $existing !== null
            && $existing->deliveryState() === DeliveryState::DELIVERED
            && $existing->revision() === $revision
            && $existing->externalId() !== ''
        ) {
            $outcome = array(
                'ok' => true,
                'status' => 'skipped',
                'error_code' => '',
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'target' => $target,
                'revision' => $revision,
                'external_id' => $existing->externalId(),
                'delivery_state' => DeliveryState::SKIPPED,
                'idempotency_key' => $idempotencyKey,
                'binding' => $existing,
            );

            return $this->finish($outcome);
        }

        $connector = $this->connectorRegistry->resolve($target);
        if ($connector === null) {
            $binding = $this->upsertBinding(
                $existing,
                array(
                    'project_id' => $projectId,
                    'announcement_id' => $announcementId,
                    'target' => $target,
                    'external_id' => $existing !== null ? $existing->externalId() : '',
                    'delivery_state' => DeliveryState::FAILED,
                    'revision' => $revision,
                    'idempotency_key' => $idempotencyKey,
                    'last_sync' => $this->utcNow(),
                    'attempt_count' => ($existing !== null ? $existing->attemptCount() : 0) + 1,
                    'last_error' => 'connector_not_registered',
                )
            );

            return $this->finish(array(
                'ok' => false,
                'status' => 'failed',
                'error_code' => 'connector_not_registered',
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'target' => $target,
                'revision' => $revision,
                'external_id' => $binding->externalId(),
                'delivery_state' => DeliveryState::FAILED,
                'idempotency_key' => $idempotencyKey,
                'binding' => $binding,
            ));
        }

        $connectorResult = $connector->deliver($payload, $existing);
        $ok = isset($connectorResult['ok']) && $connectorResult['ok'] === true;
        $externalId = isset($connectorResult['external_id'])
            ? (string) $connectorResult['external_id']
            : ($existing !== null ? $existing->externalId() : '');
        $errorCode = isset($connectorResult['error_code'])
            ? (string) $connectorResult['error_code']
            : '';
        $errorMessage = isset($connectorResult['error_message'])
            ? (string) $connectorResult['error_message']
            : '';

        if ($ok && $externalId === '') {
            $ok = false;
            $errorCode = $errorCode !== '' ? $errorCode : 'missing_external_id';
        }

        $deliveryState = DeliveryState::FAILED;
        $status = 'failed';

        if ($ok) {
            $deliveryState = isset($connectorResult['delivery_state'])
                && DeliveryState::isValid((string) $connectorResult['delivery_state'])
                ? (string) $connectorResult['delivery_state']
                : DeliveryState::DELIVERED;
            $status = $deliveryState === DeliveryState::DELIVERED ? 'delivered' : $deliveryState;
            $errorCode = '';
            $errorMessage = '';
        } elseif (
            isset($connectorResult['delivery_state'])
            && (string) $connectorResult['delivery_state'] === DeliveryState::RETRY
        ) {
            $deliveryState = DeliveryState::RETRY;
            $status = 'retry';
        }

        $binding = $this->upsertBinding(
            $existing,
            array(
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'target' => $target,
                'external_id' => $externalId,
                'delivery_state' => $deliveryState,
                'revision' => $revision,
                'idempotency_key' => $idempotencyKey,
                'last_sync' => $this->utcNow(),
                'attempt_count' => ($existing !== null ? $existing->attemptCount() : 0) + 1,
                'last_error' => $ok
                    ? ''
                    : ($errorCode !== '' ? $errorCode : $errorMessage),
            )
        );

        return $this->finish(array(
            'ok' => $ok,
            'status' => $status,
            'error_code' => $errorCode,
            'project_id' => $projectId,
            'announcement_id' => $announcementId,
            'target' => $target,
            'revision' => $revision,
            'external_id' => $binding->externalId(),
            'delivery_state' => $binding->deliveryState(),
            'idempotency_key' => $idempotencyKey,
            'binding' => $binding,
        ));
    }

    /** @return string */
    public function version()
    {
        return self::VERSION;
    }

    /** @return DeliveryDiagnostics */
    public function diagnostics()
    {
        return $this->diagnostics;
    }

    /**
     * @param DeliveryBinding|null $existing
     * @param array<string, mixed> $data
     * @return DeliveryBinding
     */
    private function upsertBinding($existing, array $data)
    {
        $binding = $existing !== null ? $existing->with($data) : new DeliveryBinding($data);
        $this->deliveryRegistry->save($binding);

        return $binding;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<string, mixed>
     */
    private function finish(array $outcome)
    {
        $this->diagnostics->recordDelivery($outcome);

        return $outcome;
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
