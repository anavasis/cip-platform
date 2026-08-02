<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Delivery\DeliveryConnectorRegistry;
use StudyMentor\ContentEngine\Delivery\DeliveryDiagnostics;
use StudyMentor\ContentEngine\Delivery\DeliveryEngine;
use StudyMentor\ContentEngine\Delivery\DeliveryPayloadBuilder;
use StudyMentor\ContentEngine\Delivery\DeliveryRegistry;

defined('ABSPATH') || exit;

/**
 * Delivery Core wiring: engine, registries, payload builder, diagnostics.
 * No concrete external connectors are registered in this phase.
 */
final class DeliveryModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'delivery';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(DeliveryConnectorRegistry::class)) {
            $container->set(DeliveryConnectorRegistry::class, new DeliveryConnectorRegistry());
        }

        if (!$container->has(DeliveryRegistry::class)) {
            $container->set(DeliveryRegistry::class, new DeliveryRegistry());
        }

        if (!$container->has(DeliveryPayloadBuilder::class)) {
            $container->set(DeliveryPayloadBuilder::class, new DeliveryPayloadBuilder());
        }

        if (!$container->has(DeliveryDiagnostics::class)) {
            $container->factory(
                DeliveryDiagnostics::class,
                static function (ServiceContainer $c) {
                    return new DeliveryDiagnostics(
                        $c->get(DeliveryConnectorRegistry::class),
                        $c->get(DeliveryRegistry::class)
                    );
                }
            );
        }

        if (!$container->has(DeliveryEngine::class)) {
            $container->factory(
                DeliveryEngine::class,
                static function (ServiceContainer $c) {
                    return new DeliveryEngine(
                        $c->get(DeliveryConnectorRegistry::class),
                        $c->get(DeliveryRegistry::class),
                        $c->get(DeliveryPayloadBuilder::class),
                        $c->get(DeliveryDiagnostics::class)
                    );
                }
            );
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
