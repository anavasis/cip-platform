<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Admin\BulkConnectivityAuditService;
use StudyMentor\ContentEngine\Admin\Menu;
use StudyMentor\ContentEngine\Admin\Pages\BulkSourcesPage;
use StudyMentor\ContentEngine\Admin\Pages\ConnectivityAuditPage;
use StudyMentor\ContentEngine\Admin\Pages\ImportedItemsPage;
use StudyMentor\ContentEngine\Admin\Pages\ManualAnnouncementsPage;
use StudyMentor\ContentEngine\Admin\Pages\SourcesPage;
use StudyMentor\ContentEngine\Admin\SourceActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCatalogActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Admin\SourceItemActionHandler;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceCatalogBulkService;
use StudyMentor\ContentEngine\Data\SourceItemIntakeService;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
use StudyMentor\ContentEngine\Data\SourceItemRepository;
use StudyMentor\ContentEngine\Data\SourceRegistryService;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;
use StudyMentor\ContentEngine\Feed\FeedPreviewParser;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;
use StudyMentor\ContentEngine\Http\SafeUrlGuard;

defined('ABSPATH') || exit;

final class SourceRegistryModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'source_registry';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(SourceRepository::class)) {
            $container->factory(
                SourceRepository::class,
                static function () {
                    return new SourceRepository($GLOBALS['wpdb']);
                }
            );
        }

        if (!$container->has(SourceRegistryService::class)) {
            $container->factory(
                SourceRegistryService::class,
                static function (ServiceContainer $c) {
                    return new SourceRegistryService($c->get(SourceRepository::class));
                }
            );
        }

        if (!$container->has(SafeUrlGuard::class)) {
            $container->set(SafeUrlGuard::class, new SafeUrlGuard());
        }

        if (!$container->has(SafeFeedFetcher::class)) {
            $container->factory(
                SafeFeedFetcher::class,
                static function (ServiceContainer $c) {
                    return new SafeFeedFetcher($c->get(SafeUrlGuard::class));
                }
            );
        }

        if (!$container->has(FeedPreviewParser::class)) {
            $container->set(FeedPreviewParser::class, new FeedPreviewParser());
        }

        if (!$container->has(AsepAnnouncementsHtmlParser::class)) {
            $container->set(AsepAnnouncementsHtmlParser::class, new AsepAnnouncementsHtmlParser());
        }

        if (!$container->has(SourcesPage::class)) {
            $container->factory(
                SourcesPage::class,
                static function (ServiceContainer $c) {
                    return new SourcesPage(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceRepository::class),
                        $c->get(SourceRegistryService::class),
                        $c->get(SourceCheckService::class)
                    );
                }
            );
        }

        if (!$container->has(SourceActionHandler::class)) {
            $container->factory(
                SourceActionHandler::class,
                static function (ServiceContainer $c) {
                    return new SourceActionHandler(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceRegistryService::class)
                    );
                }
            );
        }

        if (!$container->has(SourceCatalogBulkService::class)) {
            $container->factory(
                SourceCatalogBulkService::class,
                static function (ServiceContainer $c) {
                    return new SourceCatalogBulkService($c->get(SourceRepository::class));
                }
            );
        }

        if (!$container->has(BulkSourcesPage::class)) {
            $container->factory(
                BulkSourcesPage::class,
                static function (ServiceContainer $c) {
                    return new BulkSourcesPage(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceCatalogBulkService::class),
                        SMCE_PLUGIN_DIR . 'views/admin/bulk-sources.php'
                    );
                }
            );
        }

        if (!$container->has(SourceCatalogActionHandler::class)) {
            $container->factory(
                SourceCatalogActionHandler::class,
                static function (ServiceContainer $c) {
                    return new SourceCatalogActionHandler(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceCatalogBulkService::class)
                    );
                }
            );
        }

        if (!$container->has(BulkConnectivityAuditService::class)) {
            $container->factory(
                BulkConnectivityAuditService::class,
                static function (ServiceContainer $c) {
                    return new BulkConnectivityAuditService(
                        $c->get(SourceRepository::class),
                        $c->get(SafeFeedFetcher::class)
                    );
                }
            );
        }

        if (!$container->has(ConnectivityAuditPage::class)) {
            $container->factory(
                ConnectivityAuditPage::class,
                static function (ServiceContainer $c) {
                    return new ConnectivityAuditPage(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceRepository::class),
                        $c->get(BulkConnectivityAuditService::class),
                        SMCE_PLUGIN_DIR . 'views/admin/connectivity-audit.php'
                    );
                }
            );
        }

        if (!$container->has(SourceItemRepository::class)) {
            $container->factory(
                SourceItemRepository::class,
                static function () {
                    return new SourceItemRepository($GLOBALS['wpdb']);
                }
            );
        }

        if (!$container->has(SourceItemIntakeService::class)) {
            $container->factory(
                SourceItemIntakeService::class,
                static function (ServiceContainer $c) {
                    return new SourceItemIntakeService(
                        $c->get(SourceRepository::class),
                        $c->get(SourceItemRepository::class)
                    );
                }
            );
        }

        if (!$container->has(SourceItemActionHandler::class)) {
            $container->factory(
                SourceItemActionHandler::class,
                static function (ServiceContainer $c) {
                    return new SourceItemActionHandler(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceItemIntakeService::class)
                    );
                }
            );
        }

        if (!$container->has(ManualAnnouncementsPage::class)) {
            $container->factory(
                ManualAnnouncementsPage::class,
                static function (ServiceContainer $c) {
                    return new ManualAnnouncementsPage(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceRepository::class),
                        $c->get(SourceItemIntakeService::class),
                        SMCE_PLUGIN_DIR . 'views/admin/manual-announcements.php'
                    );
                }
            );
        }

        if (!$container->has(SourceItemReadRepository::class)) {
            $container->factory(
                SourceItemReadRepository::class,
                static function () {
                    return new SourceItemReadRepository($GLOBALS['wpdb']);
                }
            );
        }

        if (!$container->has(ImportedItemsPage::class)) {
            $container->factory(
                ImportedItemsPage::class,
                static function (ServiceContainer $c) {
                    return new ImportedItemsPage(
                        $c->get(FeatureFlags::class),
                        $c->get(SourceItemReadRepository::class),
                        SMCE_PLUGIN_DIR . 'views/admin/imported-items.php'
                    );
                }
            );
        }

        if (!$container->has(Menu::class)) {
            $container->factory(
                Menu::class,
                static function (ServiceContainer $c) {
                    return new Menu(
                        $c->get(\StudyMentor\ContentEngine\Admin\Pages\DashboardPage::class),
                        $c->get(\StudyMentor\ContentEngine\Admin\Pages\SettingsPage::class),
                        $c->get(\StudyMentor\ContentEngine\Admin\Pages\DiagnosticsPage::class),
                        $c->get(FeatureFlags::class),
                        $c->get(SourcesPage::class),
                        $c->get(BulkSourcesPage::class),
                        $c->get(ConnectivityAuditPage::class),
                        $c->get(ManualAnnouncementsPage::class),
                        $c->get(ImportedItemsPage::class)
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
