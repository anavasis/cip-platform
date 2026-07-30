<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Admin\Pages\DashboardPage;
use StudyMentor\ContentEngine\Admin\Pages\DiagnosticsPage;
use StudyMentor\ContentEngine\Admin\Pages\SettingsPage;
use StudyMentor\ContentEngine\Audit\AuditLoggerInterface;
use StudyMentor\ContentEngine\Audit\NullAuditLogger;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityFlagMapper;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\VersionRegistry;
use StudyMentor\ContentEngine\Support\LoggerInterface;
use StudyMentor\ContentEngine\Support\NullLogger;

defined('ABSPATH') || exit;

final class CorePlatformModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'core_platform';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(FeatureFlags::class)) {
            $container->set(FeatureFlags::class, new FeatureFlags());
        }

        if (!$container->has(LoggerInterface::class)) {
            $container->set(LoggerInterface::class, new NullLogger());
        }

        if (!$container->has(AuditLoggerInterface::class)) {
            $container->set(AuditLoggerInterface::class, new NullAuditLogger());
        }

        if (!$container->has(CapabilityFlagMapper::class)) {
            $container->factory(
                CapabilityFlagMapper::class,
                static function (ServiceContainer $c) {
                    return new CapabilityFlagMapper($c->get(FeatureFlags::class));
                }
            );
        }

        if (!$container->has(CapabilityRegistry::class)) {
            $container->factory(
                CapabilityRegistry::class,
                static function (ServiceContainer $c) {
                    return new CapabilityRegistry($c->get(CapabilityFlagMapper::class));
                }
            );
        }

        if (!$container->has(VersionRegistry::class)) {
            $container->set(VersionRegistry::class, new VersionRegistry());
        }

        if (!$container->has(PlatformDiagnostics::class)) {
            $container->factory(
                PlatformDiagnostics::class,
                static function (ServiceContainer $c) {
                    return new PlatformDiagnostics(
                        $c->get(\StudyMentor\ContentEngine\Core\ModuleRegistry::class),
                        $c->get(CapabilityRegistry::class),
                        $c->get(FeatureFlags::class),
                        $c->get(VersionRegistry::class)
                    );
                }
            );
        }

        if (!$container->has(DashboardPage::class)) {
            $container->factory(
                DashboardPage::class,
                static function (ServiceContainer $c) {
                    return new DashboardPage($c->get(FeatureFlags::class));
                }
            );
        }

        if (!$container->has(SettingsPage::class)) {
            $container->factory(
                SettingsPage::class,
                static function (ServiceContainer $c) {
                    return new SettingsPage($c->get(FeatureFlags::class));
                }
            );
        }

        if (!$container->has(DiagnosticsPage::class)) {
            $container->factory(
                DiagnosticsPage::class,
                static function (ServiceContainer $c) {
                    return new DiagnosticsPage(
                        $c->get(FeatureFlags::class),
                        $c->get(PlatformDiagnostics::class)
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
