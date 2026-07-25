<?php

namespace StudyMentor\ContentEngine\Core;

use StudyMentor\ContentEngine\Admin\BulkConnectivityAuditService;
use StudyMentor\ContentEngine\Admin\Menu;
use StudyMentor\ContentEngine\Admin\Pages\BulkSourcesPage;
use StudyMentor\ContentEngine\Admin\Pages\ConnectivityAuditPage;
use StudyMentor\ContentEngine\Admin\Pages\DashboardPage;
use StudyMentor\ContentEngine\Admin\Pages\DiagnosticsPage;
use StudyMentor\ContentEngine\Admin\Pages\ImportedItemsPage;
use StudyMentor\ContentEngine\Admin\Pages\ManualAnnouncementsPage;
use StudyMentor\ContentEngine\Admin\Pages\SettingsPage;
use StudyMentor\ContentEngine\Admin\Pages\SourcesPage;
use StudyMentor\ContentEngine\Admin\SourceActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCatalogActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Admin\SourceItemActionHandler;
use StudyMentor\ContentEngine\Audit\NullAuditLogger;
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
use StudyMentor\ContentEngine\Support\NullLogger;

defined('ABSPATH') || exit;

final class Plugin
{
    private $featureFlags;
    private $logger;
    private $auditLogger;
    private $menu;
    private $sourceActionHandler;
    private $sourceCatalogActionHandler;
    private $sourceItemActionHandler;

    public function __construct()
    {
        $this->featureFlags = new FeatureFlags();
        $this->logger = new NullLogger();
        $this->auditLogger = new NullAuditLogger();

        $dashboardPage = new DashboardPage($this->featureFlags);
        $settingsPage = new SettingsPage($this->featureFlags);
        $diagnosticsPage = new DiagnosticsPage($this->featureFlags);

        $sourceRepository = new SourceRepository($GLOBALS['wpdb']);
        $sourceRegistryService = new SourceRegistryService($sourceRepository);
        $safeUrlGuard = new SafeUrlGuard();
        $safeFeedFetcher = new SafeFeedFetcher($safeUrlGuard);
        $feedPreviewParser = new FeedPreviewParser();
        $asepAnnouncementsHtmlParser = new AsepAnnouncementsHtmlParser();
        $sourceCheckService = new SourceCheckService(
            $sourceRepository,
            $safeFeedFetcher,
            $feedPreviewParser,
            $asepAnnouncementsHtmlParser
        );
        $sourcesPage = new SourcesPage(
            $this->featureFlags,
            $sourceRepository,
            $sourceRegistryService,
            $sourceCheckService
        );
        $this->sourceActionHandler = new SourceActionHandler(
            $this->featureFlags,
            $sourceRegistryService
        );

        $sourceCatalogBulkService = new SourceCatalogBulkService($sourceRepository);
        $bulkSourcesPage = new BulkSourcesPage(
            $this->featureFlags,
            $sourceCatalogBulkService,
            SMCE_PLUGIN_DIR . 'views/admin/bulk-sources.php'
        );
        $this->sourceCatalogActionHandler = new SourceCatalogActionHandler(
            $this->featureFlags,
            $sourceCatalogBulkService
        );
        $bulkConnectivityAuditService = new BulkConnectivityAuditService(
            $sourceRepository,
            $safeFeedFetcher
        );
        $connectivityAuditPage = new ConnectivityAuditPage(
            $this->featureFlags,
            $sourceRepository,
            $bulkConnectivityAuditService,
            SMCE_PLUGIN_DIR . 'views/admin/connectivity-audit.php'
        );

        $sourceItemRepository = new SourceItemRepository($GLOBALS['wpdb']);
        $sourceItemIntakeService = new SourceItemIntakeService(
            $sourceRepository,
            $sourceItemRepository
        );
        $this->sourceItemActionHandler = new SourceItemActionHandler(
            $this->featureFlags,
            $sourceItemIntakeService
        );
        $manualAnnouncementsPage = new ManualAnnouncementsPage(
            $this->featureFlags,
            $sourceRepository,
            $sourceItemIntakeService,
            SMCE_PLUGIN_DIR . 'views/admin/manual-announcements.php'
        );
        $sourceItemReadRepository = new SourceItemReadRepository($GLOBALS['wpdb']);
        $importedItemsPage = new ImportedItemsPage(
            $this->featureFlags,
            $sourceItemReadRepository,
            SMCE_PLUGIN_DIR . 'views/admin/imported-items.php'
        );

        $this->menu = new Menu(
            $dashboardPage,
            $settingsPage,
            $diagnosticsPage,
            $this->featureFlags,
            $sourcesPage,
            $bulkSourcesPage,
            $connectivityAuditPage,
            $manualAnnouncementsPage,
            $importedItemsPage
        );
    }

    public function boot()
    {
        add_action('admin_menu', array($this->menu, 'register'));
        $this->sourceActionHandler->register();
        $this->sourceCatalogActionHandler->register();
        $this->sourceItemActionHandler->register();
    }
}
