<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Request-scoped Delivery Engine diagnostics.
 */
final class DeliveryDiagnostics
{
    private $connectorRegistry;
    private $deliveryRegistry;
    /** @var array<string, mixed>|null */
    private $lastDelivery;
    private $deliveryCount;
    private $successCount;
    private $failureCount;
    private $skippedCount;

    public function __construct(
        DeliveryConnectorRegistry $connectorRegistry,
        DeliveryRegistry $deliveryRegistry
    ) {
        $this->connectorRegistry = $connectorRegistry;
        $this->deliveryRegistry = $deliveryRegistry;
        $this->lastDelivery = null;
        $this->deliveryCount = 0;
        $this->successCount = 0;
        $this->failureCount = 0;
        $this->skippedCount = 0;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return void
     */
    public function recordDelivery(array $outcome)
    {
        $status = isset($outcome['status']) ? (string) $outcome['status'] : '';

        $this->lastDelivery = array(
            'status' => $status,
            'project_id' => isset($outcome['project_id']) ? (string) $outcome['project_id'] : '',
            'announcement_id' => isset($outcome['announcement_id'])
                ? (int) $outcome['announcement_id']
                : 0,
            'target' => isset($outcome['target']) ? (string) $outcome['target'] : '',
            'external_id' => isset($outcome['external_id']) ? (string) $outcome['external_id'] : '',
            'delivery_state' => isset($outcome['delivery_state'])
                ? (string) $outcome['delivery_state']
                : '',
            'idempotency_key' => isset($outcome['idempotency_key'])
                ? (string) $outcome['idempotency_key']
                : '',
            'error_code' => isset($outcome['error_code']) ? (string) $outcome['error_code'] : '',
            'revision' => isset($outcome['revision']) ? (int) $outcome['revision'] : 0,
        );

        $this->deliveryCount++;

        if ($status === 'delivered') {
            $this->successCount++;
        } elseif ($status === 'skipped') {
            $this->skippedCount++;
        } elseif ($status === 'failed' || $status === 'retry') {
            $this->failureCount++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function collect()
    {
        return array(
            'engine' => 'delivery',
            'version' => DeliveryEngine::VERSION,
            'connector_ids' => $this->connectorRegistry->ids(),
            'connector_count' => count($this->connectorRegistry->all()),
            'binding_count' => $this->deliveryRegistry->count(),
            'delivery_count' => $this->deliveryCount,
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount,
            'skipped_count' => $this->skippedCount,
            'last_delivery' => $this->lastDelivery,
        );
    }
}
