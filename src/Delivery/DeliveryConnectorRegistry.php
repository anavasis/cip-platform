<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Resolves Delivery connectors by target id.
 * Delivery Core ships with zero concrete connectors registered.
 */
final class DeliveryConnectorRegistry
{
    /** @var array<string, DeliveryConnectorInterface> */
    private $connectors = array();

    /**
     * @return void
     */
    public function register(DeliveryConnectorInterface $connector)
    {
        $id = trim((string) $connector->id());

        if ($id === '') {
            return;
        }

        $this->connectors[$id] = $connector;
    }

    /**
     * @param string $target
     * @return bool
     */
    public function has($target)
    {
        return isset($this->connectors[(string) $target]);
    }

    /**
     * @param string $target
     * @return DeliveryConnectorInterface|null
     */
    public function get($target)
    {
        $key = (string) $target;

        return isset($this->connectors[$key]) ? $this->connectors[$key] : null;
    }

    /**
     * @param string $target
     * @return DeliveryConnectorInterface|null
     */
    public function resolve($target)
    {
        return $this->get($target);
    }

    /**
     * @return array<string, DeliveryConnectorInterface>
     */
    public function all()
    {
        return $this->connectors;
    }

    /**
     * @return array<int, string>
     */
    public function ids()
    {
        $ids = array_keys($this->connectors);
        sort($ids);

        return array_values($ids);
    }
}
