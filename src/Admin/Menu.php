<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Admin\Pages\BulkSourcesPage;
use StudyMentor\ContentEngine\Admin\Pages\ConnectivityAuditPage;
use StudyMentor\ContentEngine\Admin\Pages\DashboardPage;
use StudyMentor\ContentEngine\Admin\Pages\DiagnosticsPage;
use StudyMentor\ContentEngine\Admin\Pages\EditorialAnnouncementsPage;
use StudyMentor\ContentEngine\Admin\Pages\EditorialQueuePage;
use StudyMentor\ContentEngine\Admin\Pages\EditorialWorkspacePage;
use StudyMentor\ContentEngine\Admin\Pages\ImportedItemsPage;
use StudyMentor\ContentEngine\Admin\Pages\ManualAnnouncementsPage;
use StudyMentor\ContentEngine\Admin\Pages\SettingsPage;
use StudyMentor\ContentEngine\Admin\Pages\SourcesPage;
use StudyMentor\ContentEngine\Core\FeatureFlags;

defined('ABSPATH') || exit;

final class Menu
{
    private $dashboardPage;
    private $settingsPage;
    private $diagnosticsPage;
    private $editorialWorkspacePage;
    private $editorialAnnouncementsPage;
    private $editorialQueuePage;
    private $featureFlags;
    private $sourcesPage;
    private $bulkSourcesPage;
    private $connectivityAuditPage;
    private $manualAnnouncementsPage;
    private $importedItemsPage;

    public function __construct(
        DashboardPage $dashboardPage,
        SettingsPage $settingsPage,
        DiagnosticsPage $diagnosticsPage,
        EditorialWorkspacePage $editorialWorkspacePage,
        EditorialAnnouncementsPage $editorialAnnouncementsPage,
        EditorialQueuePage $editorialQueuePage,
        FeatureFlags $featureFlags,
        SourcesPage $sourcesPage,
        BulkSourcesPage $bulkSourcesPage,
        ConnectivityAuditPage $connectivityAuditPage,
        ManualAnnouncementsPage $manualAnnouncementsPage,
        ImportedItemsPage $importedItemsPage
    ) {
        $this->dashboardPage = $dashboardPage;
        $this->settingsPage = $settingsPage;
        $this->diagnosticsPage = $diagnosticsPage;
        $this->editorialWorkspacePage = $editorialWorkspacePage;
        $this->editorialAnnouncementsPage = $editorialAnnouncementsPage;
        $this->editorialQueuePage = $editorialQueuePage;
        $this->featureFlags = $featureFlags;
        $this->sourcesPage = $sourcesPage;
        $this->bulkSourcesPage = $bulkSourcesPage;
        $this->connectivityAuditPage = $connectivityAuditPage;
        $this->manualAnnouncementsPage = $manualAnnouncementsPage;
        $this->importedItemsPage = $importedItemsPage;
    }

    public function register()
    {
        add_menu_page(
            'StudyMentor Content Engine',
            'StudyMentor Content Engine',
            'manage_options',
            'smce-dashboard',
            array($this->dashboardPage, 'render'),
            'dashicons-admin-generic'
        );

        global $submenu;
        if (isset($submenu['smce-dashboard'][0][0])) {
            $submenu['smce-dashboard'][0][0] = 'Dashboard';
        }

        add_submenu_page(
            'smce-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'smce-settings',
            array($this->settingsPage, 'render')
        );

        add_submenu_page(
            'smce-dashboard',
            'Diagnostics',
            'Diagnostics',
            'manage_options',
            'smce-diagnostics',
            array($this->diagnosticsPage, 'render')
        );

        add_submenu_page(
            'smce-dashboard',
            'Editorial Workspace',
            'Editorial Workspace',
            'manage_options',
            'smce-editorial',
            array($this->editorialWorkspacePage, 'render')
        );

        add_submenu_page(
            'smce-dashboard',
            'Announcements',
            'Announcements',
            'manage_options',
            'smce-editorial-announcements',
            array($this->editorialAnnouncementsPage, 'render')
        );

        add_submenu_page(
            'smce-dashboard',
            'Editorial Queue',
            'Editorial Queue',
            'manage_options',
            'smce-editorial-queue',
            array($this->editorialQueuePage, 'render')
        );

        if ($this->featureFlags->isEnabled('source_registry')) {
            add_submenu_page(
                'smce-dashboard',
                'Sources',
                'Sources',
                'manage_options',
                'smce-sources',
                array($this->sourcesPage, 'render')
            );

            add_submenu_page(
                'smce-dashboard',
                'Bulk Sources',
                'Bulk Sources',
                'manage_options',
                'smce-bulk-sources',
                array($this->bulkSourcesPage, 'render')
            );

            add_submenu_page(
                'smce-dashboard',
                'Connectivity Audit',
                'Connectivity Audit',
                'manage_options',
                'smce-connectivity-audit',
                array($this->connectivityAuditPage, 'render')
            );

            add_submenu_page(
                'smce-dashboard',
                'Manual Announcements',
                'Manual Intake',
                'manage_options',
                'smce-manual-announcements',
                array($this->manualAnnouncementsPage, 'render')
            );

            add_submenu_page(
                'smce-dashboard',
                'Imported Items',
                'Imported Items',
                'manage_options',
                'smce-imported-items',
                array($this->importedItemsPage, 'render')
            );
        }
    }
}
