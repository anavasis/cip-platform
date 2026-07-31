<?php

$pluginDirectory = dirname(__DIR__);
$failures = array();
$scannerRelativePath = 'tests/static-safety-check.php';
$schemaManagerRelativePath = 'src/Core/SchemaManager.php';
$sourceRepositoryRelativePath = 'src/Data/SourceRepository.php';
$sourceItemRepositoryRelativePath = 'src/Data/SourceItemRepository.php';
$sourceItemReadRepositoryRelativePath = 'src/Data/SourceItemReadRepository.php';
$sourceItemActionHandlerRelativePath = 'src/Admin/SourceItemActionHandler.php';
$manualAnnouncementsPageRelativePath = 'src/Admin/Pages/ManualAnnouncementsPage.php';
$importedItemsPageRelativePath = 'src/Admin/Pages/ImportedItemsPage.php';
$importedItemsViewRelativePath = 'views/admin/imported-items.php';
$sourceCatalogBulkServiceRelativePath = 'src/Data/SourceCatalogBulkService.php';
$sourceCatalogActionHandlerRelativePath = 'src/Admin/SourceCatalogActionHandler.php';
$bulkSourcesPageRelativePath = 'src/Admin/Pages/BulkSourcesPage.php';
$bulkSourcesViewRelativePath = 'views/admin/bulk-sources.php';
$bulkConnectivityAuditServiceRelativePath = 'src/Admin/BulkConnectivityAuditService.php';
$connectivityAuditPageRelativePath = 'src/Admin/Pages/ConnectivityAuditPage.php';
$connectivityAuditViewRelativePath = 'views/admin/connectivity-audit.php';
$menuRelativePath = 'src/Admin/Menu.php';
$activatorRelativePath = 'src/Core/Activator.php';
$deactivatorRelativePath = 'src/Core/Deactivator.php';
$featureFlagsRelativePath = 'src/Core/FeatureFlags.php';
$asepParserRelativePath = 'src/Feed/AsepAnnouncementsHtmlParser.php';

$approvedDirectories = array(
    'src',
    'src/Core',
    'src/Admin',
    'src/Admin/Pages',
    'src/Acquisition',
    'src/Audit',
    'src/Collectors',
    'src/Contracts',
    'src/Data',
    'src/Evidence',
    'src/Fingerprint',
    'src/Http',
    'src/Feed',
    'src/Announcement',
    'src/Blueprint',
    'src/PromptContext',
    'src/PromptPackage',
    'src/GenerationRequest',
    'src/Modules',
    'src/Platform',
    'src/Registry',
    'src/Support',
    'views',
    'views/admin',
    'tests',
    '.github',
    '.github/architecture-guard',
    '.github/workflows',
);

$approvedFiles = array(
    '.gitignore',
    'studymentor-content-engine.php',
    'readme.txt',
    'src/Core/Autoloader.php',
    'src/Core/Plugin.php',
    'src/Core/Requirements.php',
    'src/Core/Activator.php',
    'src/Core/Deactivator.php',
    'src/Core/FeatureFlags.php',
    'src/Core/SchemaManager.php',
    'src/Admin/Menu.php',
    'src/Admin/SourceActionHandler.php',
    'src/Admin/SourceCheckService.php',
    'src/Admin/Pages/DashboardPage.php',
    'src/Admin/Pages/SettingsPage.php',
    'src/Admin/Pages/DiagnosticsPage.php',
    'src/Admin/Pages/SourcesPage.php',
    'src/Data/SourceRepository.php',
    'src/Data/SourceRegistryService.php',
    'src/Http/SafeUrlGuard.php',
    'src/Http/SafeFeedFetcher.php',
    'src/Feed/FeedPreviewParser.php',
    'src/Feed/AsepAnnouncementsHtmlParser.php',
    'src/Audit/AuditLoggerInterface.php',
    'src/Audit/NullAuditLogger.php',
    'src/Support/LoggerInterface.php',
    'src/Support/NullLogger.php',
    'views/admin/dashboard.php',
    'views/admin/settings.php',
    'views/admin/diagnostics.php',
    'views/admin/sources.php',
    'src/Data/SourceItemRepository.php',
    'src/Data/SourceItemIntakeService.php',
    'src/Admin/SourceItemActionHandler.php',
    'src/Admin/Pages/ManualAnnouncementsPage.php',
    'views/admin/manual-announcements.php',
    'src/Data/SourceItemReadRepository.php',
    'src/Admin/Pages/ImportedItemsPage.php',
    'views/admin/imported-items.php',
    'src/Data/SourceCatalogBulkService.php',
    'src/Admin/SourceCatalogActionHandler.php',
    'src/Admin/Pages/BulkSourcesPage.php',
    'views/admin/bulk-sources.php',
    'src/Admin/BulkConnectivityAuditService.php',
    'src/Admin/Pages/ConnectivityAuditPage.php',
    'views/admin/connectivity-audit.php',
    'src/Contracts/ModuleInterface.php',
    'src/Core/ServiceContainer.php',
    'src/Core/ModuleRegistry.php',
    'src/Core/ModuleLoader.php',
    'src/Modules/CorePlatformModule.php',
    'src/Modules/SourceRegistryModule.php',
    'src/Modules/AcquisitionModule.php',
    'src/Modules/AnnouncementModule.php',
    'src/Modules/BlueprintModule.php',
    'src/Modules/PromptContextModule.php',
    'src/Modules/PromptPackageModule.php',
    'src/Modules/GenerationRequestModule.php',
    'src/Blueprint/ArticleType.php',
    'src/Blueprint/BlueprintStatus.php',
    'src/Blueprint/Tone.php',
    'src/Blueprint/LengthTarget.php',
    'src/Blueprint/HeadingNode.php',
    'src/Blueprint/SectionSpec.php',
    'src/Blueprint/FaqRequirements.php',
    'src/Blueprint/CtaRequirement.php',
    'src/Blueprint/LinkRequirement.php',
    'src/Blueprint/SchemaRequirement.php',
    'src/Blueprint/SeoConstraints.php',
    'src/Blueprint/ValidationRuleSpec.php',
    'src/Blueprint/ContentBlueprint.php',
    'src/Blueprint/ContentBlueprintRepositoryInterface.php',
    'src/Blueprint/ContentBlueprintBuilder.php',
    'src/Blueprint/ContentBlueprintValidator.php',
    'tests/build001-content-blueprint-smoke.php',
    'src/PromptContext/PromptContextStatus.php',
    'src/PromptContext/AnnouncementFacts.php',
    'src/PromptContext/BlueprintProjection.php',
    'src/PromptContext/PromptContext.php',
    'src/PromptContext/PromptContextBuilder.php',
    'src/PromptContext/PromptContextValidator.php',
    'src/PromptContext/PromptContextRepositoryInterface.php',
    'tests/build002-prompt-context-smoke.php',
    'src/PromptPackage/PromptPackageStatus.php',
    'src/PromptPackage/BlueprintReference.php',
    'src/PromptPackage/PromptTemplateReference.php',
    'src/PromptPackage/PromptPackage.php',
    'src/PromptPackage/PromptPackageBuilder.php',
    'src/PromptPackage/PromptPackageValidator.php',
    'src/PromptPackage/PromptPackageRepositoryInterface.php',
    'tests/build003-prompt-package-smoke.php',
    'src/GenerationRequest/GenerationRequestStatus.php',
    'src/GenerationRequest/GenerationModelReference.php',
    'src/GenerationRequest/GenerationParameters.php',
    'src/GenerationRequest/GenerationRequest.php',
    'src/GenerationRequest/GenerationRequestBuilder.php',
    'src/GenerationRequest/GenerationRequestValidator.php',
    'src/GenerationRequest/GenerationRequestRepositoryInterface.php',
    'tests/build004-generation-request-smoke.php',
    'src/Announcement/AnnouncementCandidate.php',
    'src/Announcement/AnnouncementIdentityService.php',
    'src/Announcement/AnnouncementItemExtractor.php',
    'src/Announcement/AnnouncementLifecycleService.php',
    'src/Announcement/EditorialIngestionService.php',
    'src/Announcement/LifecycleBatchResult.php',
    'src/Announcement/LifecycleDecision.php',
    'src/Announcement/LifecycleOutcome.php',
    'src/Acquisition/SourceAcquisitionService.php',
    'src/Acquisition/AcquisitionDiagnostics.php',
    'src/Acquisition/AcquisitionEngine.php',
    'src/Acquisition/AcquisitionManager.php',
    'src/Acquisition/AcquisitionResult.php',
    'src/Acquisition/AcquisitionRunResult.php',
    'src/Acquisition/CollectorMetrics.php',
    'src/Acquisition/DownloadManager.php',
    'src/Acquisition/ProductionAcquisitionOrchestrator.php',
    'src/Collectors/CollectorInterface.php',
    'src/Collectors/CollectorRegistry.php',
    'src/Collectors/SafeFeedCollector.php',
    'src/Contracts/ParserHandlerInterface.php',
    'src/Evidence/Evidence.php',
    'src/Evidence/EvidenceRepositoryInterface.php',
    'src/Evidence/InMemoryEvidenceRepository.php',
    'src/Fingerprint/FingerprintService.php',
    'src/Registry/ParserRegistry.php',
    'src/Registry/FeedPreviewParserHandler.php',
    'src/Registry/AsepHtmlParserHandler.php',
    'src/Registry/CapabilityRegistry.php',
    'src/Registry/CapabilityFlagMapper.php',
    'src/Registry/VersionRegistry.php',
    'src/Platform/PlatformDiagnostics.php',
    'tests/cip002-foundation-smoke.php',
    'tests/cip003a-acquisition-platform-smoke.php',
    'tests/cip003b-acquisition-engine-smoke.php',
    'tests/cip003c-source-check-integration-smoke.php',
    'tests/cip003d-collector-activation-smoke.php',
    'tests/cip003e-evidence-diagnostics-smoke.php',
    'tests/cip004-acquisition-capability-smoke.php',
    'tests/cip005-production-orchestrator-smoke.php',
    'src/Admin/Pages/EditorialWorkspacePage.php',
    'src/Admin/Pages/EditorialAnnouncementsPage.php',
    'src/Admin/Pages/EditorialQueuePage.php',
    'views/admin/editorial-workspace.php',
    'views/admin/editorial-announcements.php',
    'views/admin/editorial-queue.php',
    'src/Announcement/EditorialWorkspaceQueryService.php',
    'tests/editorial-workspace-phase2-smoke.php',
    'tests/editorial-spine-phase1-smoke.php',
    '.github/architecture-guard/policy.txt',
    '.github/architecture-guard/check.php',
    '.github/workflows/architecture-guard.yml',
    'tests/static-safety-check.php',
);

$blockedDirectoryNames = array(
    'assets',
    'vendor',
    'migrations',
    'database',
    'schema',
    'storage',
    'uploads',
    'logs',
    'cache',
    'tmp',
    'adapters',
    'sources',
    'jobs',
    'ai',
    'newsletter',
    'social',
);

$blockedExtensions = array(
    'css',
    'js',
    'sql',
    'json',
    'yaml',
    'yml',
);

$forbiddenPatternChecks = array(
    array('pattern' => 'register_post_type', 'case_insensitive' => true),
    array('pattern' => 'register_taxonomy', 'case_insensitive' => true),
    array('pattern' => 'add_rewrite_rule', 'case_insensitive' => true),
    array('pattern' => 'flush_rewrite_rules', 'case_insensitive' => true),
    array('pattern' => 'register_rest_route', 'case_insensitive' => true),
    array('pattern' => 'wp_ajax_', 'case_insensitive' => true),
    array('pattern' => 'wp_ajax_nopriv_', 'case_insensitive' => true),
    array('pattern' => 'wp_schedule_event', 'case_insensitive' => true),
    array('pattern' => 'wp_next_scheduled', 'case_insensitive' => true),
    array('pattern' => 'wp_remote_', 'case_insensitive' => true),
    array('pattern' => 'curl_', 'case_insensitive' => true),
    array('pattern' => 'SMCP', 'case_insensitive' => true),
    array('pattern' => 'SM_Asep', 'case_insensitive' => true),
    array('pattern' => 'studymentor-client-panel', 'case_insensitive' => true),
    array('pattern' => 'add_option', 'case_insensitive' => true),
    array('pattern' => 'delete_option', 'case_insensitive' => true),
    array('pattern' => 'add_cap', 'case_insensitive' => true),
    array('pattern' => 'remove_cap', 'case_insensitive' => true),
    array('pattern' => 'add_role', 'case_insensitive' => true),
    array('pattern' => 'remove_role', 'case_insensitive' => true),
    array('pattern' => 'wp_insert_post', 'case_insensitive' => true),
    array('pattern' => 'media_handle_', 'case_insensitive' => true),
    array('pattern' => 'wp_mail', 'case_insensitive' => true),
    array('pattern' => 'set_transient', 'case_insensitive' => true),
    array('pattern' => 'delete_transient', 'case_insensitive' => true),
    array('pattern' => 'wp_update_post', 'case_insensitive' => true),
    array('pattern' => 'wp_delete_post', 'case_insensitive' => true),
    array('pattern' => 'add_post_meta', 'case_insensitive' => true),
    array('pattern' => 'update_post_meta', 'case_insensitive' => true),
    array('pattern' => 'delete_post_meta', 'case_insensitive' => true),
    array('pattern' => 'add_term_meta', 'case_insensitive' => true),
    array('pattern' => 'update_term_meta', 'case_insensitive' => true),
    array('pattern' => 'delete_term_meta', 'case_insensitive' => true),
    array('pattern' => 'add_user_meta', 'case_insensitive' => true),
    array('pattern' => 'update_user_meta', 'case_insensitive' => true),
    array('pattern' => 'delete_user_meta', 'case_insensitive' => true),
    array('pattern' => 'wp_insert_term', 'case_insensitive' => true),
    array('pattern' => 'wp_update_term', 'case_insensitive' => true),
    array('pattern' => 'wp_delete_term', 'case_insensitive' => true),
    array('pattern' => 'wp_clear_scheduled_hook', 'case_insensitive' => true),
    array('pattern' => 'wp_unschedule_event', 'case_insensitive' => true),
    array('pattern' => 'add_shortcode', 'case_insensitive' => true),
    array('pattern' => 'register_widget', 'case_insensitive' => true),
    array('pattern' => 'file_put_contents', 'case_insensitive' => true),
    array('pattern' => 'fopen', 'case_insensitive' => true),
    array('pattern' => 'fwrite', 'case_insensitive' => true),
    array('pattern' => 'error_log', 'case_insensitive' => true),
    array('pattern' => 'wp_mkdir_p', 'case_insensitive' => true),
    array('pattern' => 'mkdir', 'case_insensitive' => true),
    array('pattern' => 'fsockopen', 'case_insensitive' => true),
    array('pattern' => 'stream_socket_client', 'case_insensitive' => true),
    array('pattern' => 'info.asep.gr', 'case_insensitive' => true),
    array('pattern' => 'feed.xml', 'case_insensitive' => true),
    array('pattern' => 'rankmath', 'case_insensitive' => true),
);

$databasePatternsOutsideSchemaManager = array(
    '$wpdb',
    'dbDelta',
    'get_option',
    'update_option',
    'SHOW TABLES',
    'CREATE TABLE',
);

$databasePatternsAllowedInSourceRepository = array(
    '$wpdb',
    '->prepare',
    '->get_results',
    '->get_row',
    '->get_var',
    '->insert',
    '->update',
    'insert_id',
);

$databasePatternsForbiddenInSourceRepository = array(
    'dbDelta',
    'get_option',
    'update_option',
    'add_option',
    'delete_option',
    '->delete',
    '->replace',
    'SHOW TABLES',
    'CREATE TABLE',
    '->query',
);

$requiredSourceItemRepositorySnippets = array(
    '$wpdb',
    '->prepare',
    '->get_row',
    '->insert',
    '->update',
    'existsBySourceAndIdentityHash',
    'findBySourceAndIdentityHash',
    'markUnchanged',
    'applyContentUpdate',
    'identity_hash',
    'smce_source_items',
);

$databasePatternsForbiddenInSourceItemRepository = array(
    'dbDelta',
    'get_option',
    'add_option',
    'update_option',
    'delete_option',
    '->delete(',
    '->replace(',
    '->query(',
    'CREATE TABLE',
    'ALTER TABLE',
    'DROP TABLE',
    'TRUNCATE TABLE',
    'set_transient',
    'wp_schedule_',
    'wp_remote_',
    'wp_safe_remote_get',
    'curl_',
    'dns_get_record',
    'gethostbynamel',
    'fsockopen',
    'stream_socket_client',
    'file_put_contents',
    'fopen',
    'fwrite',
);

$requiredSourceItemReadRepositorySnippets = array(
    'class SourceItemReadRepository',
    'findPage',
    'findById',
    'findSourceOptions',
    '$this->wpdb->prepare',
    '$this->wpdb->esc_like',
    '$this->wpdb->get_results',
    'smce_source_items',
    'smce_sources',
    'LEFT JOIN',
    'LIMIT %d',
    'OFFSET %d',
    'WHERE i.id = %d',
    'PAGE_SIZE = 25',
    'QUERY_LIMIT = 26',
    'MAX_PAGE = 200',
);

$databasePatternsForbiddenInSourceItemReadRepository = array(
    'dbDelta',
    'get_option',
    'add_option',
    'update_option',
    'delete_option',
    '->insert(',
    '->update(',
    '->delete(',
    '->replace(',
    '->query(',
    'CREATE TABLE',
    'set_transient',
    'wp_schedule_',
    'wp_remote_',
    'curl_',
    'dns_get_record',
    'gethostbyname',
    'gethostbynamel',
    'fsockopen',
    'stream_socket_client',
);

$phase1gNewFiles = array(
    'src/Data/SourceItemReadRepository.php',
    'src/Admin/Pages/ImportedItemsPage.php',
    'views/admin/imported-items.php',
);

$phase1hNewFiles = array(
    'src/Data/SourceCatalogBulkService.php',
    'src/Admin/SourceCatalogActionHandler.php',
    'src/Admin/Pages/BulkSourcesPage.php',
    'views/admin/bulk-sources.php',
);

$phase1hForbiddenRegexes = array(
    '/\$wpdb\b/',
    '/->\s*(update|setEnabled|delete|replace|query)\s*\(/i',
    '/\b(INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|REPLACE\s+INTO|UPSERT|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
    '/\bCREATE\s+TABLE\b/i',
    '/\bdbDelta\b/i',
    '/\bwp_remote_/i',
    '/\bcurl_/i',
    '/\b(dns_get_record|gethostbyname|gethostbynamel|checkdnsrr|getmxrr)\b/i',
    '/\b(wp_schedule_|wp_next_scheduled|wp_clear_scheduled_hook|wp_unschedule_event)/i',
    '/\bwp_ajax_/i',
    '/\b(rest_api_init|register_rest_route)\b/i',
    '/\b(wp_enqueue_scripts|template_redirect|wp_head|wp_footer|the_content|add_shortcode|register_post_type|add_rewrite_rule)\b/i',
    '/\b(add|update|delete)_(?:site_)?option\s*\(/i',
    '/\bset_(?:site_)?transient\s*\(/i',
    '/\bdelete_(?:site_)?transient\s*\(/i',
    '/\b(add|update|delete)_user_meta\s*\(/i',
    '/\b(session_start|setcookie)\s*\(/i',
    '/\$_(?:SESSION|COOKIE)\b/',
    '/\b(file_put_contents|fopen|fwrite|move_uploaded_file)\b/i',
    '/\bstudymentor-client-panel\b/i',
    '/\bSMCP\b/i',
    '/\bSM_Asep\b/i',
    '#/asep/#i',
    '/assets\/admin\.js/i',
    '/\bwp_enqueue_/i',
);

$phase1iNewFiles = array(
    'src/Admin/BulkConnectivityAuditService.php',
    'src/Admin/Pages/ConnectivityAuditPage.php',
    'views/admin/connectivity-audit.php',
);

$phase1iAllowedChangedPaths = array(
    'src/Admin/BulkConnectivityAuditService.php',
    'src/Admin/Pages/ConnectivityAuditPage.php',
    'views/admin/connectivity-audit.php',
    'studymentor-content-engine.php',
    'readme.txt',
    'src/Core/Plugin.php',
    'src/Admin/Menu.php',
    'src/Http/SafeFeedFetcher.php',
    'tests/static-safety-check.php',
);

$phase1iForbiddenRegexes = array(
    '/\$wpdb\b/',
    '/\$GLOBALS\s*\[\s*[\'"]wpdb[\'"]\s*\]/i',
    '/->\s*(insert|update|setEnabled|delete|replace|query)\s*\(/i',
    '/\b(INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|REPLACE\s+INTO|UPSERT|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|CREATE\s+TABLE)\b/i',
    '/\bdbDelta\b/i',
    '/\bSourceItemRepository\b/',
    '/\b(wp_insert_post|wp_update_post)\s*\(/i',
    '/\b(FeedPreviewParser|AsepAnnouncementsHtmlParser|DOMDocument|SimpleXML)\b/i',
    '/\b(add|update|delete)_(?:site_)?option\s*\(/i',
    '/\bset_(?:site_)?transient\s*\(/i',
    '/\bdelete_(?:site_)?transient\s*\(/i',
    '/\b(add|update|delete)_user_meta\s*\(/i',
    '/\b(session_start|setcookie)\s*\(/i',
    '/\$_(?:SESSION|COOKIE)\b/',
    '/\b(file_put_contents|fopen|fwrite|move_uploaded_file)\s*\(/i',
    '/\b(error_log|trigger_error)\s*\(/i',
    '/\b(wp_schedule_|wp_next_scheduled|wp_clear_scheduled_hook|wp_unschedule_event)/i',
    '/\bwp_ajax_/i',
    '/\b(rest_api_init|register_rest_route)\b/i',
    '/\bWP_CLI\b/i',
    '/\b(wp_enqueue_scripts|template_redirect|wp_head|wp_footer|the_content|add_shortcode|register_post_type|add_rewrite_rule)\b/i',
    '/\badmin_post_/i',
    '/\bstudymentor-client-panel\b/i',
    '/\bSMCP\b/i',
    '/\bSM_Asep\b/i',
    '#/asep/#i',
    '/assets\/admin\.js/i',
    '/\bwp_enqueue_/i',
);

$phase1fNewFiles = array(
    'src/Data/SourceItemRepository.php',
    'src/Data/SourceItemIntakeService.php',
    'src/Admin/SourceItemActionHandler.php',
    'src/Admin/Pages/ManualAnnouncementsPage.php',
    'views/admin/manual-announcements.php',
);

$phase1fForbiddenPatterns = array(
    'wp_remote_',
    'wp_safe_remote_get',
    'curl_',
    'fsockopen',
    'stream_socket_client',
    'dns_get_record',
    'gethostbynamel',
    'checkdnsrr',
    'getmxrr',
    'wp_schedule_',
    'wp_next_scheduled',
    'wp_clear_scheduled_hook',
    'wp_unschedule_event',
    'register_rest_route',
    'wp_ajax_',
    'wp_ajax_nopriv_',
    'wp_enqueue_scripts',
    'template_redirect',
    'wp_head',
    'wp_footer',
    'the_content',
    'add_shortcode',
    'set_transient',
    'add_user_meta',
    'update_user_meta',
    'delete_user_meta',
    'file_put_contents',
    'fopen(',
    'fwrite(',
    'studymentor-client-panel',
    'SM_Asep',
    '/asep/',
);

$phase1gForbiddenRegexes = array(
    '/->\s*(insert|update|delete|replace|query)\s*\(/i',
    '/\b(INSERT|UPDATE|DELETE|REPLACE|UPSERT|ALTER|DROP|TRUNCATE)\b/i',
    '/\bdbDelta\b/i',
    '/\bCREATE\s+TABLE\b/i',
    '/\$_POST\b/',
    '/\badmin_post_/i',
    '/\bwp_ajax_/i',
    '/\brest_api_init\b/i',
    '/\bregister_rest_route\b/i',
    '/\bwp_(schedule|next_scheduled|clear_scheduled_hook|unschedule_event)\b/i',
    '/\bwp_remote_/i',
    '/\bcurl_/i',
    '/\bdns_get_record\b/i',
    '/\bgethostbyname(?:l)?\b/i',
    '/\bfsockopen\b/i',
    '/\bstream_socket_client\b/i',
    '/\b(add|update|delete)_(?:site_)?option\s*\(/i',
    '/\bset_(?:site_)?transient\s*\(/i',
    '/\bdelete_(?:site_)?transient\s*\(/i',
    '/\b(add|update|delete)_user_meta\s*\(/i',
    '/\b(session_start|setcookie)\s*\(/i',
    '/\$_(?:SESSION|COOKIE)\b/',
    '/\b(wp_enqueue_scripts|template_redirect|wp_head|wp_footer|the_content|add_shortcode|register_post_type|add_rewrite_rule)\b/i',
    '/\bstudymentor-client-panel\b/i',
    '/\bSMCP\b/i',
    '/\bSM_Asep\b/i',
    '#/asep/#i',
);

$immutableNormalizedHashes = array(
    'src/Core/SchemaManager.php' => 'df0eb54171881fab45ccab6b206bca4839439cc162e2871a4a4ca1296042aece',
    'src/Core/Activator.php' => 'e1baf8147c46521dd22fd57a2e85c00bf373493d6342922988324257798b7d98',
    'src/Core/Deactivator.php' => '9eec76c184d78539b48b42140242136e80bef75550e7d47fbb9c6ae401929d94',
    'src/Core/FeatureFlags.php' => '897f89a2116f5ce1c4ef4b2594f330d0c8d435d06c5f0de33da9336821012210',
);

$destructiveSqlPatterns = array(
    '\bDELETE\b',
    '\bDROP\b',
    '\bALTER\b',
    '\bTRUNCATE\b',
);

$phase1cNewFiles = array(
    'src/Http/SafeUrlGuard.php',
    'src/Http/SafeFeedFetcher.php',
    'src/Feed/FeedPreviewParser.php',
    'src/Admin/SourceCheckService.php',
);

$phase1cZeroPersistencePatterns = array(
    '$wpdb',
    'smce_source_items',
    '->insert(',
    '->update(',
    '->delete(',
    'add_option',
    'update_option',
    'set_transient',
    'set_site_transient',
    'update_user_meta',
    'wp_cache_set',
    'wp_insert_post',
    'wp_schedule_',
    'file_put_contents',
    'fopen',
    'fwrite',
);

$safeFeedFetcherRelativePath = 'src/Http/SafeFeedFetcher.php';
$feedPreviewParserRelativePath = 'src/Feed/FeedPreviewParser.php';

$phase1dNewFiles = array(
    'src/Feed/AsepAnnouncementsHtmlParser.php',
);

$phase1dZeroPersistencePatterns = array(
    '$wpdb',
    'smce_source_items',
    '->insert(',
    '->update(',
    '->delete(',
    'add_option',
    'update_option',
    'set_transient',
    'set_site_transient',
    'update_user_meta',
    'wp_cache_set',
    'wp_insert_post',
    'wp_schedule_',
    'file_put_contents',
    'fopen',
    'fwrite',
    'wp_safe_remote_get',
    'wp_remote_',
    'curl_',
    'fsockopen',
    'stream_socket_client',
);

$requiredAsepParserSnippets = array(
    'LIBXML_NONET',
    'DOMDocument',
    'DOMXPath',
    'loadHTML',
    'asep_announcements_v1',
    'view-id-contest_announcements_view',
    'view-content',
    'views-row',
    'views-field-field-announcement-type',
    'views-field-title',
    'views-field-field-issue-date',
    'views-field-view-node',
    '<!DOCTYPE',
    '<!ENTITY',
    'structure_not_found',
    'structure_too_large',
    'unsupported_parser_profile',
);

$phase1bNewFiles = array(
    'src/Data/SourceRepository.php',
    'src/Data/SourceRegistryService.php',
    'src/Admin/Pages/SourcesPage.php',
    'src/Admin/SourceActionHandler.php',
    'views/admin/sources.php',
);

$databasePatternsForbiddenInActivator = array(
    'dbDelta',
    'get_option',
    'update_option',
    'add_option',
    'delete_option',
    '->insert(',
    '->update(',
    '->delete(',
    '->replace(',
    'SHOW TABLES',
    'CREATE TABLE',
    '->prepare',
    '->get_var',
    '->query',
);

function containsForbiddenPattern($contents, $pattern, $isCaseInsensitive)
{
    if ($isCaseInsensitive) {
        return stripos($contents, $pattern) !== false;
    }

    return strpos($contents, $pattern) !== false;
}

function normalizedContentHash($contents)
{
    return hash('sha256', $contents);
}

function normalizeLintOutputLine($line, $pluginDirectory)
{
    $normalizedLine = str_replace('\\', '/', $line);
    $normalizedRoot = str_replace('\\', '/', $pluginDirectory);

    return str_replace($normalizedRoot . '/', '', $normalizedLine);
}

$actualDirectories = array();
$actualFiles = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $pluginDirectory,
        FilesystemIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relativePath = substr(
        $item->getPathname(),
        strlen($pluginDirectory) + 1
    );
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

    if ($relativePath === '.git' || strpos($relativePath, '.git/') === 0) {
        continue;
    }

    if ($item->isDir()) {
        $actualDirectories[] = $relativePath;

        if (in_array(strtolower($item->getBasename()), $blockedDirectoryNames, true)) {
            $failures[] = 'Forbidden directory found: ' . $relativePath;
        }

        continue;
    }

    if (!$item->isFile()) {
        $failures[] = 'Unexpected non-file entry found: ' . $relativePath;
        continue;
    }

    $actualFiles[] = $relativePath;
    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

    if (in_array($extension, $blockedExtensions, true)) {
        if ($relativePath !== '.github/workflows/architecture-guard.yml') {
            $failures[] = 'Forbidden file extension found: ' . $relativePath;
        }
    }

    $contents = file_get_contents($item->getPathname());

    if ($contents === false) {
        $failures[] = 'Unable to read: ' . $relativePath;
        continue;
    }

    if ($relativePath !== $scannerRelativePath) {
        foreach ($forbiddenPatternChecks as $check) {
            if (
                $relativePath === $safeFeedFetcherRelativePath
                && $check['pattern'] === 'wp_remote_'
            ) {
                continue;
            }

            if (
                $check['pattern'] === 'fwrite'
                && (
                    $relativePath === '.github/architecture-guard/check.php'
                    || $relativePath === 'tests/cip002-foundation-smoke.php'
                    || $relativePath === 'tests/cip003a-acquisition-platform-smoke.php'
                    || $relativePath === 'tests/cip003b-acquisition-engine-smoke.php'
                    || $relativePath === 'tests/cip003c-source-check-integration-smoke.php'
                    || $relativePath === 'tests/cip003d-collector-activation-smoke.php'
                    || $relativePath === 'tests/cip003e-evidence-diagnostics-smoke.php'
                    || $relativePath === 'tests/cip004-acquisition-capability-smoke.php'
                    || $relativePath === 'tests/cip005-production-orchestrator-smoke.php'
                    || $relativePath === 'tests/editorial-spine-phase1-smoke.php'
                    || $relativePath === 'tests/editorial-workspace-phase2-smoke.php'
                    || $relativePath === 'tests/build001-content-blueprint-smoke.php'
                    || $relativePath === 'tests/build002-prompt-context-smoke.php'
                    || $relativePath === 'tests/build003-prompt-package-smoke.php'
                    || $relativePath === 'tests/build004-generation-request-smoke.php'
                )
            ) {
                continue;
            }

            if (
                containsForbiddenPattern(
                    $contents,
                    $check['pattern'],
                    $check['case_insensitive']
                )
            ) {
                $failures[] = 'Forbidden pattern found in '
                    . $relativePath
                    . ': '
                    . $check['pattern'];
            }
        }

        if ($relativePath === $activatorRelativePath) {
            foreach ($databasePatternsForbiddenInActivator as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Forbidden database pattern found in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        } elseif ($relativePath === $sourceRepositoryRelativePath) {
            foreach ($databasePatternsForbiddenInSourceRepository as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Forbidden database pattern found in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        } elseif ($relativePath === $sourceItemRepositoryRelativePath) {
            foreach ($databasePatternsForbiddenInSourceItemRepository as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Forbidden database pattern found in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        } elseif ($relativePath === $sourceItemReadRepositoryRelativePath) {
            foreach ($databasePatternsForbiddenInSourceItemReadRepository as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Forbidden database pattern found in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        } elseif ($relativePath !== $schemaManagerRelativePath) {
            foreach ($databasePatternsOutsideSchemaManager as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Database access is only allowed in '
                        . $schemaManagerRelativePath
                        . ', '
                        . $sourceRepositoryRelativePath
                        . ', and '
                        . $sourceItemRepositoryRelativePath
                        . ', or read-only access in '
                        . $sourceItemReadRepositoryRelativePath
                        . '; found in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (
            !in_array($relativePath, array($sourceRepositoryRelativePath, $sourceItemRepositoryRelativePath), true)
            && containsForbiddenPattern($contents, 'wpdb->insert(', true)
        ) {
            $failures[] = '$wpdb->insert() is only allowed in '
                . $sourceRepositoryRelativePath
                . ' or '
                . $sourceItemRepositoryRelativePath
                . '; found in '
                . $relativePath;
        }

        if (
            !in_array($relativePath, array($sourceRepositoryRelativePath, $sourceItemRepositoryRelativePath), true)
            && containsForbiddenPattern($contents, 'wpdb->update(', true)
        ) {
            $failures[] = '$wpdb->update() is only allowed in '
                . $sourceRepositoryRelativePath
                . ' or '
                . $sourceItemRepositoryRelativePath
                . '; found in '
                . $relativePath;
        }

        if (containsForbiddenPattern($contents, 'wpdb->delete(', true)) {
            $failures[] = '$wpdb->delete() is forbidden; found in ' . $relativePath;
        }

        if (containsForbiddenPattern($contents, 'wpdb->replace(', true)) {
            $failures[] = '$wpdb->replace() is forbidden; found in ' . $relativePath;
        }

        foreach ($destructiveSqlPatterns as $pattern) {
            if (
                strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'php'
                && preg_match('/' . $pattern . '/i', $contents) === 1
            ) {
                $failures[] = 'Destructive SQL keyword found in '
                    . $relativePath
                    . ': '
                    . $pattern;
            }
        }

        if (in_array($relativePath, $phase1bNewFiles, true)) {
            if (containsForbiddenPattern($contents, 'smce_source_items', true)) {
                $failures[] = 'Phase 1B file must not reference smce_source_items: '
                    . $relativePath;
            }
        }

        if (in_array($relativePath, $phase1cNewFiles, true)) {
            foreach ($phase1cZeroPersistencePatterns as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Phase 1C file must not contain persistence pattern in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (in_array($relativePath, $phase1dNewFiles, true)) {
            foreach ($phase1dZeroPersistencePatterns as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Phase 1D file must not contain persistence pattern in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (in_array($relativePath, $phase1fNewFiles, true)) {
            foreach ($phase1fForbiddenPatterns as $pattern) {
                if (containsForbiddenPattern($contents, $pattern, true)) {
                    $failures[] = 'Phase 1F file must not contain forbidden pattern in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (in_array($relativePath, $phase1gNewFiles, true)) {
            foreach ($phase1gForbiddenRegexes as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $failures[] = 'Phase 1G file contains forbidden behavior in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (in_array($relativePath, $phase1hNewFiles, true)) {
            foreach ($phase1hForbiddenRegexes as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $failures[] = 'Phase 1H file contains forbidden behavior in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if (in_array($relativePath, $phase1iNewFiles, true)) {
            foreach ($phase1iForbiddenRegexes as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $failures[] = 'Phase 1I file contains forbidden behavior in '
                        . $relativePath
                        . ': '
                        . $pattern;
                }
            }
        }

        if ($relativePath === $sourceItemReadRepositoryRelativePath) {
            if (
                preg_match(
                    '/\b(public|protected|private)\s+function\s+(insert|update|delete|replace|query)\s*\(/i',
                    $contents
                ) === 1
            ) {
                $failures[] = 'SourceItemReadRepository must not expose mutation or generic query methods.';
            }

            if (preg_match('/SELECT\s+(?:\*|i\.\*)/i', $contents) === 1) {
                $failures[] = 'SourceItemReadRepository must use explicit SELECT columns.';
            }
        }

        if ($relativePath === $importedItemsViewRelativePath) {
            $forbiddenViewRegexes = array(
                '/method\s*=\s*["\']post["\']/i',
                '/admin-post\.php/i',
                '/\bbulk\b/i',
                '/\b(Edit|Delete|Retry|Reimport|Publish|Approve|Reject|Restore)\b/i',
                '/<script\b/i',
                '/assets\/admin\.js/i',
                '/studymentor-client-panel/i',
                '#/asep/#i',
                '/\bon(click|change|submit|load|keydown|keyup|keypress|focus|blur|input)\s*=/i',
            );

            foreach ($forbiddenViewRegexes as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $failures[] = 'Imported Items view contains a forbidden mutation control: '
                        . $pattern;
                }
            }
        }

        if (
            $relativePath !== $sourceItemActionHandlerRelativePath
            && containsForbiddenPattern($contents, 'admin_post_smce_source_item_confirm', true)
        ) {
            $failures[] = 'admin_post_smce_source_item_confirm is only allowed in '
                . $sourceItemActionHandlerRelativePath
                . '; found in '
                . $relativePath;
        }

        if (
            $relativePath !== $sourceCatalogActionHandlerRelativePath
            && containsForbiddenPattern($contents, 'admin_post_smce_source_catalog_confirm', true)
        ) {
            $failures[] = 'admin_post_smce_source_catalog_confirm is only allowed in '
                . $sourceCatalogActionHandlerRelativePath
                . '; found in '
                . $relativePath;
        }

        if (preg_match('/admin_post_[a-z0-9_]*preview/i', $contents) === 1) {
            $failures[] = 'No admin-post preview action is permitted; found matching pattern in '
                . $relativePath;
        }

        if ($relativePath === $asepParserRelativePath) {
            foreach ($requiredAsepParserSnippets as $snippet) {
                if (!containsForbiddenPattern($contents, $snippet, true)) {
                    $failures[] = 'AsepAnnouncementsHtmlParser missing required control: ' . $snippet;
                }
            }
        }

        if ($relativePath === $safeFeedFetcherRelativePath) {
            if (!containsForbiddenPattern($contents, 'wp_safe_remote_get', true)) {
                $failures[] = 'SafeFeedFetcher must call wp_safe_remote_get().';
            }

            if (preg_match('/\bwp_remote_(?!retrieve_)/i', $contents) === 1) {
                $failures[] = 'SafeFeedFetcher must not call wp_remote_get() or wp_remote_request().';
            }

            $requiredFetcherSnippets = array(
                'redirection',
                'limit_response_size',
                'timeout',
                'MAX_REDIRECT_HOPS',
            );

            foreach ($requiredFetcherSnippets as $snippet) {
                if (!containsForbiddenPattern($contents, $snippet, true)) {
                    $failures[] = 'SafeFeedFetcher missing required fetch control: ' . $snippet;
                }
            }

            if (!containsForbiddenPattern($contents, 'ALLOWED_REDIRECT_STATUSES', true)) {
                $failures[] = 'SafeFeedFetcher must define an explicit redirect status allowlist.';
            }

            foreach (array(301, 302, 303, 307, 308) as $redirectStatus) {
                if (!containsForbiddenPattern($contents, (string) $redirectStatus, true)) {
                    $failures[] = 'SafeFeedFetcher redirect allowlist must include status '
                        . $redirectStatus
                        . '.';
                }
            }

            if (!containsForbiddenPattern($contents, 'in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)', true)) {
                $failures[] = 'SafeFeedFetcher must use strict redirect-status membership checking.';
            }

            if (preg_match('/\$statusCode\s*>=\s*300\s*&&\s*\$statusCode\s*<\s*400/', $contents) === 1) {
                $failures[] = 'SafeFeedFetcher must not use broad 3xx redirect logic.';
            }

            if (preg_match(
                "/errorResult\s*\(\s*'redirect_blocked'\s*,\s*\\\$requestedUrl\s*,\s*\\\$resolvedLocation/s",
                $contents
            ) === 1) {
                $failures[] = 'SafeFeedFetcher redirect_blocked must not pass rejected redirect target as final_url.';
            }

            if (!preg_match(
                "/errorResult\s*\(\s*'redirect_blocked'\s*,\s*\\\$requestedUrl\s*,\s*\\\$currentUrl/s",
                $contents
            )) {
                $failures[] = 'SafeFeedFetcher redirect_blocked must pass $currentUrl as final_url.';
            }
        } elseif (containsForbiddenPattern($contents, 'wp_safe_remote_get', true)) {
            $failures[] = 'wp_safe_remote_get() is only allowed in '
                . $safeFeedFetcherRelativePath
                . '; found in '
                . $relativePath;
        }

        if ($relativePath === $feedPreviewParserRelativePath) {
            $requiredParserSnippets = array(
                'LIBXML_NONET',
                '<!DOCTYPE',
                '<!ENTITY',
            );

            foreach ($requiredParserSnippets as $snippet) {
                if (!containsForbiddenPattern($contents, $snippet, true)) {
                    $failures[] = 'FeedPreviewParser missing required parser control: ' . $snippet;
                }
            }

            if (!containsForbiddenPattern($contents, "class_exists('\\DOMDocument')", true)) {
                $failures[] = 'FeedPreviewParser must guard DOMDocument availability with class_exists().';
            }

            if (!containsForbiddenPattern($contents, "class_exists('\\DOMXPath')", true)) {
                $failures[] = 'FeedPreviewParser must guard DOMXPath availability with class_exists().';
            }

            if (!containsForbiddenPattern($contents, 'parser_unavailable', true)) {
                $failures[] = 'FeedPreviewParser must return parser_unavailable when DOM classes are missing.';
            }
        }
    }
}

sort($approvedDirectories);
sort($actualDirectories);
sort($approvedFiles);
sort($actualFiles);

foreach (array_diff($approvedDirectories, $actualDirectories) as $path) {
    $failures[] = 'Approved directory missing: ' . $path;
}

foreach (array_diff($actualDirectories, $approvedDirectories) as $path) {
    $failures[] = 'Unapproved directory found: ' . $path;
}

foreach (array_diff($approvedFiles, $actualFiles) as $path) {
    $failures[] = 'Approved file missing: ' . $path;
}

foreach (array_diff($actualFiles, $approvedFiles) as $path) {
    $failures[] = 'Unapproved file found: ' . $path;
}

foreach ($immutableNormalizedHashes as $relativePath => $expectedHash) {
    $immutableFile = $pluginDirectory
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $immutableContents = file_get_contents($immutableFile);

    if (
        $immutableContents === false
        || normalizedContentHash($immutableContents) !== $expectedHash
    ) {
        $failures[] = 'Immutable normalized-content hash mismatch: ' . $relativePath;
    }
}

$explicitlyForbiddenPaths = array(
    'composer.json',
    'vendor',
    'uninstall.php',
);

foreach ($explicitlyForbiddenPaths as $relativePath) {
    if (file_exists($pluginDirectory . DIRECTORY_SEPARATOR . $relativePath)) {
        $failures[] = 'Explicitly forbidden path found: ' . $relativePath;
    }
}

$mainFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . 'studymentor-content-engine.php';
$mainContents = file_get_contents($mainFile);

if (
    $mainContents === false
    || preg_match('/^\s*\*\s*Version:\s*0\.9\.1\s*$/m', $mainContents) !== 1
    || preg_match(
        '/define\(\s*[\'"]SMCE_VERSION[\'"]\s*,\s*[\'"]0\.9\.1[\'"]\s*\)/',
        $mainContents
    ) !== 1
    || preg_match(
        '/define\(\s*[\'"]SMCE_DB_VERSION[\'"]\s*,\s*[\'"]1\.0\.0[\'"]\s*\)/',
        $mainContents
    ) !== 1
) {
    $failures[] = 'Plugin or database schema version metadata is incorrect.';
}

$readmeFile = $pluginDirectory . DIRECTORY_SEPARATOR . 'readme.txt';
$readmeContents = file_get_contents($readmeFile);

if (
    $readmeContents === false
    || preg_match('/^\s*Stable tag:\s*0\.9\.1\s*$/m', $readmeContents) !== 1
) {
    $failures[] = 'readme.txt stable tag must be 0.9.1.';
}

$schemaManagerFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $schemaManagerRelativePath);
$schemaManagerContents = file_get_contents($schemaManagerFile);

if ($schemaManagerContents === false) {
    $failures[] = 'SchemaManager is missing or unreadable.';
} else {
    $requiredSchemaSnippets = array(
        'smce_sources',
        'smce_source_items',
        "get_option(self::OPTION_NAME, '')",
        'update_option(self::OPTION_NAME',
        '->prepare',
        '->esc_like',
        '->get_charset_collate',
        'SHOW TABLES LIKE %s',
    );

    foreach ($requiredSchemaSnippets as $snippet) {
        if (strpos($schemaManagerContents, $snippet) === false) {
            $failures[] = 'SchemaManager missing required behavior: ' . $snippet;
        }
    }

    $createTableMatches = array();
    preg_match_all('/CREATE\s+TABLE/i', $schemaManagerContents, $createTableMatches);

    if (count($createTableMatches[0]) !== 2) {
        $failures[] = 'SchemaManager must define exactly two CREATE TABLE statements.';
    }

    $smceTokenMatches = array();
    preg_match_all('/smce_[a-z0-9_]+/i', $schemaManagerContents, $smceTokenMatches);
    $allowedSmceTokens = array(
        'smce_db_version',
        'smce_sources',
        'smce_source_items',
    );

    foreach (array_unique($smceTokenMatches[0]) as $token) {
        if (!in_array(strtolower($token), $allowedSmceTokens, true)) {
            $failures[] = 'SchemaManager contains unapproved smce_ token: ' . $token;
        }
    }

    if (
        preg_match(
            '/\b(INSERT|REPLACE|DELETE|DROP|TRUNCATE|ALTER|CREATE\s+DATABASE|GRANT|REVOKE)\b/i',
            $schemaManagerContents
        ) === 1
    ) {
        $failures[] = 'SchemaManager contains forbidden SQL operations.';
    }

    if (
        preg_match('/\bUPDATE\s+[`"]?[a-z0-9_]+[`"]?/i', $schemaManagerContents) === 1
    ) {
        $failures[] = 'SchemaManager contains forbidden table UPDATE SQL.';
    }
}

$activatorFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $activatorRelativePath);
$activatorContents = file_get_contents($activatorFile);

if ($activatorContents === false) {
    $failures[] = 'Activator is missing or unreadable.';
} else {
    if (strpos($activatorContents, 'new SchemaManager') === false) {
        $failures[] = 'Activator must instantiate SchemaManager.';
    }

    if (strpos($activatorContents, '->migrate()') === false) {
        $failures[] = 'Activator must execute SchemaManager::migrate().';
    }
}

$sourceRepositoryFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceRepositoryRelativePath);
$sourceRepositoryContents = file_get_contents($sourceRepositoryFile);

if ($sourceRepositoryContents === false) {
    $failures[] = 'SourceRepository is missing or unreadable.';
} else {
    if (strpos($sourceRepositoryContents, 'smce_sources') === false) {
        $failures[] = 'SourceRepository must reference the smce_sources table suffix.';
    }

    if (strpos($sourceRepositoryContents, 'smce_source_items') !== false) {
        $failures[] = 'SourceRepository must not reference smce_source_items.';
    }

    foreach ($databasePatternsAllowedInSourceRepository as $pattern) {
        if (!containsForbiddenPattern($sourceRepositoryContents, $pattern, true)) {
            $failures[] = 'SourceRepository missing required database access: ' . $pattern;
        }
    }

    if (containsForbiddenPattern($sourceRepositoryContents, 'wpdb->delete(', true)) {
        $failures[] = 'SourceRepository must not call $wpdb->delete().';
    }
}

$sourceItemRepositoryFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceItemRepositoryRelativePath);
$sourceItemRepositoryContents = file_get_contents($sourceItemRepositoryFile);

if ($sourceItemRepositoryContents === false) {
    $failures[] = 'SourceItemRepository is missing or unreadable.';
} else {
    foreach ($requiredSourceItemRepositorySnippets as $snippet) {
        if (strpos($sourceItemRepositoryContents, $snippet) === false) {
            $failures[] = 'SourceItemRepository missing required behavior: ' . $snippet;
        }
    }

    if (containsForbiddenPattern($sourceItemRepositoryContents, 'wpdb->delete(', true)) {
        $failures[] = 'SourceItemRepository must not call $wpdb->delete().';
    }

    if (strpos($sourceItemRepositoryContents, 'markUnchanged') === false) {
        $failures[] = 'SourceItemRepository must provide markUnchanged for lifecycle.';
    }

    if (strpos($sourceItemRepositoryContents, 'applyContentUpdate') === false) {
        $failures[] = 'SourceItemRepository must provide applyContentUpdate for lifecycle.';
    }
}

$sourceItemReadRepositoryFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceItemReadRepositoryRelativePath);
$sourceItemReadRepositoryContents = file_get_contents($sourceItemReadRepositoryFile);

if ($sourceItemReadRepositoryContents === false) {
    $failures[] = 'SourceItemReadRepository is missing or unreadable.';
} else {
    foreach ($requiredSourceItemReadRepositorySnippets as $snippet) {
        if (strpos($sourceItemReadRepositoryContents, $snippet) === false) {
            $failures[] = 'SourceItemReadRepository missing required behavior: ' . $snippet;
        }
    }

    if (
        preg_match(
            '/ORDER BY\s+[\'"]?\s*\.\s*\$_GET|ORDER BY[^;\n]*\$_GET/i',
            $sourceItemReadRepositoryContents
        ) === 1
    ) {
        $failures[] = 'SourceItemReadRepository must not interpolate request values into ordering.';
    }

    $fetchHelperRequiredSnippets = array(
        'private function fetchRowsOrNull' => 'SourceItemReadRepository must define the private fetchRowsOrNull helper.',
        '->suppress_errors(true)' => 'SourceItemReadRepository must save error suppression with suppress_errors(true).',
        '->suppress_errors($previousSuppression)' => 'SourceItemReadRepository must restore the previous error suppression state.',
        'try {' => 'SourceItemReadRepository fetch helper must use try.',
        'finally {' => 'SourceItemReadRepository fetch helper must use finally.',
        '->last_error !== \'\'' => 'SourceItemReadRepository fetch helper must inspect last_error as a boolean condition.',
        'return null;' => 'SourceItemReadRepository fetch helper must return null on failure.',
    );

    foreach ($fetchHelperRequiredSnippets as $snippet => $message) {
        if (strpos($sourceItemReadRepositoryContents, $snippet) === false) {
            $failures[] = $message;
        }
    }

    $getResultsMatches = array();
    preg_match_all(
        '/\$this->wpdb->get_results\s*\(/',
        $sourceItemReadRepositoryContents,
        $getResultsMatches
    );

    if (count($getResultsMatches[0]) !== 1) {
        $failures[] = 'SourceItemReadRepository must contain exactly one $this->wpdb->get_results() call.';
    }

    $fetchHelperInvocationMatches = array();
    preg_match_all(
        '/\$rows\s*=\s*\$this->fetchRowsOrNull\s*\(/',
        $sourceItemReadRepositoryContents,
        $fetchHelperInvocationMatches
    );

    if (count($fetchHelperInvocationMatches[0]) !== 3) {
        $failures[] = 'SourceItemReadRepository must invoke fetchRowsOrNull exactly three times from public methods.';
    }

    if (preg_match('/return\s+[^;]*last_error/i', $sourceItemReadRepositoryContents) === 1) {
        $failures[] = 'SourceItemReadRepository must not return last_error.';
    }

    $lastErrorDisclosurePatterns = array(
        '/error_log\s*\([^)]*last_error/i',
        '/trigger_error\s*\([^)]*last_error/i',
        '/wp_die\s*\([^)]*last_error/i',
        '/(?:echo|print|printf)\s*[^;]*last_error/i',
    );

    foreach ($lastErrorDisclosurePatterns as $pattern) {
        if (preg_match($pattern, $sourceItemReadRepositoryContents) === 1) {
            $failures[] = 'SourceItemReadRepository must not log or display last_error.';
            break;
        }
    }

    $fetchFailureResultChecks = array(
        'pageFailureResult' => 'SourceItemReadRepository findPage must retain pageFailureResult failure path.',
        'itemFailureResult' => 'SourceItemReadRepository findById must retain itemFailureResult failure path.',
        'sourceOptionsFailureResult' => 'SourceItemReadRepository findSourceOptions must retain sourceOptionsFailureResult failure path.',
    );

    foreach ($fetchFailureResultChecks as $snippet => $message) {
        if (strpos($sourceItemReadRepositoryContents, $snippet) === false) {
            $failures[] = $message;
        }
    }
}

$manualAnnouncementsPageFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $manualAnnouncementsPageRelativePath);
$manualAnnouncementsPageContents = file_get_contents($manualAnnouncementsPageFile);

if (
    $manualAnnouncementsPageContents === false
    || strpos($manualAnnouncementsPageContents, 'smce_manual_preview') === false
) {
    $failures[] = 'ManualAnnouncementsPage must reference the smce_manual_preview marker.';
}

$sourceItemActionHandlerFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceItemActionHandlerRelativePath);
$sourceItemActionHandlerContents = file_get_contents($sourceItemActionHandlerFile);

if (
    $sourceItemActionHandlerContents === false
    || strpos($sourceItemActionHandlerContents, 'admin_post_smce_source_item_confirm') === false
) {
    $failures[] = 'SourceItemActionHandler must register admin_post_smce_source_item_confirm.';
}

$sourceCatalogBulkServiceFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceCatalogBulkServiceRelativePath);
$sourceCatalogBulkServiceContents = file_get_contents($sourceCatalogBulkServiceFile);

if ($sourceCatalogBulkServiceContents === false) {
    $failures[] = 'SourceCatalogBulkService is missing or unreadable.';
} else {
    $requiredBulkServiceSnippets = array(
        'class SourceCatalogBulkService',
        'function preview',
        'function confirm',
        'MAX_RECORDS = 80',
        'MAX_PAYLOAD_BYTES = 102400',
        'SourceRepository $repository',
        'slugExists',
        'feedHashExists',
        '->insert(',
        "'enabled' => 0",
        "'manual_only' => 1",
        'host_not_allowed',
        'invalid_allowed_domains',
        'SOURCE_TYPES',
        'feed_url_hash',
        'hash(\'sha256\'',
        'json_decode($rawJson, false)',
        'not_array',
        'get_object_vars',
        'instanceof \\stdClass',
    );

    foreach ($requiredBulkServiceSnippets as $snippet) {
        if (strpos($sourceCatalogBulkServiceContents, $snippet) === false) {
            $failures[] = 'SourceCatalogBulkService missing required Phase 1H behavior: ' . $snippet;
        }
    }

    if (strpos($sourceCatalogBulkServiceContents, 'json_decode($rawJson, true)') !== false) {
        $failures[] = 'SourceCatalogBulkService must not decode the bulk payload with json_decode($rawJson, true).';
    }

    if (
        preg_match(
            '/\$decoded\s*=\s*json_decode\(\s*\$rawJson\s*,\s*false\s*\)\s*;.*!is_array\(\s*\$decoded\s*\)/s',
            $sourceCatalogBulkServiceContents
        ) !== 1
    ) {
        $failures[] = 'SourceCatalogBulkService must require is_array($decoded) after object-preserving json_decode.';
    }

    if (strpos($sourceCatalogBulkServiceContents, '$wpdb') !== false) {
        $failures[] = 'SourceCatalogBulkService must not access $wpdb directly.';
    }
}

$bulkSourcesPageFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $bulkSourcesPageRelativePath);
$bulkSourcesPageContents = file_get_contents($bulkSourcesPageFile);

if (
    $bulkSourcesPageContents === false
    || strpos($bulkSourcesPageContents, 'smce_bulk_sources_preview') === false
) {
    $failures[] = 'BulkSourcesPage must reference the smce_bulk_sources_preview marker.';
} else {
    if (preg_match('/->\s*confirm\s*\(/', $bulkSourcesPageContents) === 1) {
        $failures[] = 'BulkSourcesPage Preview flow must not call service confirm().';
    }

    if (preg_match('/->\s*insert\s*\(/', $bulkSourcesPageContents) === 1) {
        $failures[] = 'BulkSourcesPage Preview flow must not call insert().';
    }
}

$sourceCatalogActionHandlerFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sourceCatalogActionHandlerRelativePath);
$sourceCatalogActionHandlerContents = file_get_contents($sourceCatalogActionHandlerFile);

if (
    $sourceCatalogActionHandlerContents === false
    || strpos($sourceCatalogActionHandlerContents, 'admin_post_smce_source_catalog_confirm') === false
) {
    $failures[] = 'SourceCatalogActionHandler must register admin_post_smce_source_catalog_confirm.';
} else {
    $requiredConfirmHandlerSnippets = array(
        'REQUEST_METHOD',
        'POST',
        'manage_options',
        'source_registry',
        'smce_source_catalog_confirm',
        'smce_bulk_notice',
        'smce_bulk_inserted',
        'smce_bulk_duplicate',
        'smce_bulk_invalid',
        'wp_safe_redirect',
    );

    foreach ($requiredConfirmHandlerSnippets as $snippet) {
        if (strpos($sourceCatalogActionHandlerContents, $snippet) === false) {
            $failures[] = 'SourceCatalogActionHandler missing required Confirm control: ' . $snippet;
        }
    }
}

$bulkSourcesViewFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $bulkSourcesViewRelativePath);
$bulkSourcesViewContents = file_get_contents($bulkSourcesViewFile);

if ($bulkSourcesViewContents === false) {
    $failures[] = 'Bulk Sources view is missing or unreadable.';
} else {
    if (strpos($bulkSourcesViewContents, 'method="post"') === false) {
        $failures[] = 'Bulk Sources Preview form must use same-page POST.';
    }

    if (strpos($bulkSourcesViewContents, 'admin-post.php') === false) {
        $failures[] = 'Bulk Sources Confirm form must post to admin-post.php.';
    }

    if (strpos($bulkSourcesViewContents, 'smce_bulk_json') === false) {
        $failures[] = 'Bulk Sources view must re-post the original JSON field.';
    }

    $forbiddenBulkViewRegexes = array(
        '/type\s*=\s*["\']file["\']/i',
        '/<script\b/i',
        '/assets\/admin\.js/i',
        '/\b(Edit|Delete|Retry|Reimport|Publish|Approve|Reject|Restore)\b/',
        '/name\s*=\s*["\']enabled["\']/i',
        '/setEnabled/i',
        '/smce_source_check/i',
        '/name\s*=\s*["\'].*(normalized|feed_url_hash|insert_data).*["\']/i',
    );

    foreach ($forbiddenBulkViewRegexes as $pattern) {
        if (preg_match($pattern, $bulkSourcesViewContents) === 1) {
            $failures[] = 'Bulk Sources view contains a forbidden control or trusted hidden field: '
                . $pattern;
        }
    }
}

$bulkConnectivityAuditServiceFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $bulkConnectivityAuditServiceRelativePath);
$bulkConnectivityAuditServiceContents = file_get_contents($bulkConnectivityAuditServiceFile);

if ($bulkConnectivityAuditServiceContents === false) {
    $failures[] = 'BulkConnectivityAuditService is missing or unreadable.';
} else {
    $requiredConnectivityServiceSnippets = array(
        'class BulkConnectivityAuditService',
        'SourceRepository $repository',
        'SafeFeedFetcher $fetcher',
        'function audit(array $sourceIds): array',
        'foreach ($sourceIds as $sourceId)',
        '$this->repository->findById($id)',
        '$this->fetcher->fetchForConnectivityAudit(',
        'CLASSIFICATION_PREFIX_BYTES = 16384',
        'substr($body, 0, self::CLASSIFICATION_PREFIX_BYTES)',
        '!in_array($host, $allowedDomains, true)',
        "'display_host' => \$this->buildSafeDisplayHost(\$host)",
        "'host' => (string) \$configuration['display_host']",
        "'invalid_source_configuration'",
        "'network_error'",
    );

    foreach ($requiredConnectivityServiceSnippets as $snippet) {
        if (strpos($bulkConnectivityAuditServiceContents, $snippet) === false) {
            $failures[] = 'BulkConnectivityAuditService missing required Phase 1I behavior: '
                . $snippet;
        }
    }

    $requiredConnectivityTruncationSnippets = array(
        "\$truncated = isset(\$fetchResult['truncated']) && \$fetchResult['truncated'] === true",
        "\$candidate === 'response_too_large' && \$truncated",
        "array('reachable_rss', 'reachable_atom', 'reachable_html')",
        "'response_too_large'",
        "'truncated' => \$truncated",
        "'truncated' => false",
    );

    foreach ($requiredConnectivityTruncationSnippets as $snippet) {
        if (strpos($bulkConnectivityAuditServiceContents, $snippet) === false) {
            $failures[] = 'BulkConnectivityAuditService missing required truncation-classification control: '
                . $snippet;
        }
    }

    if (strpos($bulkConnectivityAuditServiceContents, 'CLASSIFICATION_PREFIX_BYTES = 16384') === false) {
        $failures[] = 'BulkConnectivityAuditService must keep the 16384-byte classification prefix.';
    }

    $requiredConnectivityResultCodes = array(
        'reachable_rss',
        'reachable_atom',
        'reachable_html',
        'reachable_other',
        'unauthorized_401',
        'forbidden_403',
        'not_found_404',
        'rate_limited_429',
        'server_error',
        'http_error',
        'timeout',
        'dns_failure',
        'tls_failure',
        'unsafe_url',
        'unsafe_redirect',
        'redirect_domain_blocked',
        'redirect_limit_exceeded',
        'response_too_large',
        'invalid_source_configuration',
        'network_error',
    );

    foreach ($requiredConnectivityResultCodes as $resultCode) {
        if (
            strpos(
                $bulkConnectivityAuditServiceContents,
                "'" . $resultCode . "'"
            ) === false
        ) {
            $failures[] = 'BulkConnectivityAuditService result-code allowlist is missing: '
                . $resultCode;
        }
    }

    $resultCodeBlock = array();
    $declaredResultCodes = array();

    if (
        preg_match(
            '/private const RESULT_CODES\s*=\s*array\((.*?)\);/s',
            $bulkConnectivityAuditServiceContents,
            $resultCodeBlock
        ) === 1
    ) {
        preg_match_all(
            '/[\'"]([a-z0-9_]+)[\'"]/',
            $resultCodeBlock[1],
            $declaredResultCodeMatches
        );
        $declaredResultCodes = $declaredResultCodeMatches[1];
    }

    if ($declaredResultCodes !== $requiredConnectivityResultCodes) {
        $failures[] = 'BulkConnectivityAuditService must use the exact fixed result-code allowlist.';
    }

    $connectivityFetchCalls = array();
    preg_match_all(
        '/->fetchForConnectivityAudit\s*\(/',
        $bulkConnectivityAuditServiceContents,
        $connectivityFetchCalls
    );

    if (count($connectivityFetchCalls[0]) !== 1) {
        $failures[] = 'BulkConnectivityAuditService must contain exactly one sequential audit fetch call.';
    }

    if (
        preg_match(
            '/\b(FeedPreviewParser|AsepAnnouncementsHtmlParser|DOMDocument|SimpleXML)\b/',
            $bulkConnectivityAuditServiceContents
        ) === 1
    ) {
        $failures[] = 'BulkConnectivityAuditService must not execute or reference a parser.';
    }

    $requiredServiceDisplayHostSnippets = array(
        'private function buildSafeDisplayHost($host): string',
        '$ipCandidate = $normalized',
        "\$ipCandidate[0] === '['",
        "substr(\$ipCandidate, -1) === ']'",
        '$ipCandidate = substr($ipCandidate, 1, -1)',
        'filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false',
        'FILTER_VALIDATE_DOMAIN',
        'FILTER_FLAG_HOSTNAME',
    );

    foreach ($requiredServiceDisplayHostSnippets as $snippet) {
        if (strpos($bulkConnectivityAuditServiceContents, $snippet) === false) {
            $failures[] = 'BulkConnectivityAuditService missing literal-IP display suppression: '
                . $snippet;
        }
    }

    $serviceDisplayHostReferences = array();
    preg_match_all(
        '/\bbuildSafeDisplayHost\s*\(/',
        $bulkConnectivityAuditServiceContents,
        $serviceDisplayHostReferences
    );

    if (count($serviceDisplayHostReferences[0]) !== 2) {
        $failures[] = 'BulkConnectivityAuditService must define and use one isolated display-host helper.';
    }

    if (
        preg_match(
            '/[\'"]host[\'"]\s*=>\s*(?:\(string\)\s*)?\$configuration\s*\[\s*[\'"]host[\'"]\s*\]/',
            $bulkConnectivityAuditServiceContents
        ) === 1
        || preg_match(
            '/[\'"]host[\'"]\s*=>\s*\$host\b/',
            $bulkConnectivityAuditServiceContents
        ) === 1
    ) {
        $failures[] = 'BulkConnectivityAuditService result host must not expose the internal host.';
    }
}

$connectivityAuditPageFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $connectivityAuditPageRelativePath);
$connectivityAuditPageContents = file_get_contents($connectivityAuditPageFile);

if ($connectivityAuditPageContents === false) {
    $failures[] = 'ConnectivityAuditPage is missing or unreadable.';
} else {
    $requiredConnectivityPageSnippets = array(
        'class ConnectivityAuditPage',
        'MAX_SELECTED_SOURCES = 3',
        'FeatureFlags $featureFlags',
        'SourceRepository $sourceRepository',
        'BulkConnectivityAuditService $auditService',
        "'title' => 'Connectivity Audit'",
        '$this->sourceRepository->findAll()',
        "REQUEST_METHOD'] === 'POST'",
        "'manage_options'",
        "'source_registry'",
        "'smce_connectivity_audit'",
        "'smce_connectivity_audit_nonce'",
        "array_key_exists('source_ids', \$_POST)",
        "'source_ids'",
        '/^[1-9][0-9]*$/',
        'PHP_INT_MAX',
        'PHP_URL_HOST',
        "'host' => \$this->buildSafeDisplayHost(\$host)",
        '$this->auditService->audit($selectedIds)',
    );

    foreach ($requiredConnectivityPageSnippets as $snippet) {
        if (strpos($connectivityAuditPageContents, $snippet) === false) {
            $failures[] = 'ConnectivityAuditPage missing required Phase 1I behavior: '
                . $snippet;
        }
    }

    if (strpos($connectivityAuditPageContents, 'fetchForConnectivityAudit') !== false) {
        $failures[] = 'ConnectivityAuditPage must not invoke the network fetcher directly.';
    }

    if (
        strpos(
            $connectivityAuditPageContents,
            "'truncated' => isset(\$result['truncated'])"
        ) === false
    ) {
        $failures[] = 'ConnectivityAuditPage must pass through a normalized boolean truncated field.';
    }

    if (strpos($connectivityAuditPageContents, 'truncated_prefix') !== false) {
        $failures[] = 'ConnectivityAuditPage must not pass truncated prefix content.';
    }

    if (strpos($connectivityAuditPageContents, "\$result['body']") !== false) {
        $failures[] = 'ConnectivityAuditPage must not pass response body content.';
    }

    if (
        preg_match(
            '/if\s*\(\s*\$isPost\s*\)\s*\{.*\$this->auditService->audit\(\$selectedIds\)/s',
            $connectivityAuditPageContents
        ) !== 1
    ) {
        $failures[] = 'ConnectivityAuditPage audit invocation must remain inside the POST branch.';
    }

    $auditCallPosition = strpos(
        $connectivityAuditPageContents,
        '$this->auditService->audit($selectedIds)'
    );
    $postBranchPosition = strpos($connectivityAuditPageContents, 'if ($isPost)');

    if (
        $auditCallPosition === false
        || $postBranchPosition === false
        || $auditCallPosition < $postBranchPosition
    ) {
        $failures[] = 'ConnectivityAuditPage GET rendering must not invoke the audit service.';
    }

    if (
        preg_match(
            '/\$_POST\s*\[\s*[\'"](?:url|feed_url|domain|allowed_domains|name|source_type|parser_profile|enabled|manual_only)[\'"]\s*\]/i',
            $connectivityAuditPageContents
        ) === 1
    ) {
        $failures[] = 'ConnectivityAuditPage must accept source IDs only, not submitted source configuration.';
    }

    $requiredPageDisplayHostSnippets = array(
        'private function buildSafeDisplayHost($host): string',
        '$ipCandidate = $normalized',
        "\$ipCandidate[0] === '['",
        "substr(\$ipCandidate, -1) === ']'",
        '$ipCandidate = substr($ipCandidate, 1, -1)',
        'filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false',
        'FILTER_VALIDATE_DOMAIN',
        'FILTER_FLAG_HOSTNAME',
        "isset(\$result['host']) ? \$result['host'] : ''",
    );

    foreach ($requiredPageDisplayHostSnippets as $snippet) {
        if (strpos($connectivityAuditPageContents, $snippet) === false) {
            $failures[] = 'ConnectivityAuditPage missing literal-IP display suppression: '
                . $snippet;
        }
    }

    $pageDisplayHostReferences = array();
    preg_match_all(
        '/\bbuildSafeDisplayHost\s*\(/',
        $connectivityAuditPageContents,
        $pageDisplayHostReferences
    );

    if (count($pageDisplayHostReferences[0]) !== 3) {
        $failures[] = 'ConnectivityAuditPage must apply its display-host helper to checklist and result rows.';
    }

    if (
        preg_match(
            '/[\'"]host[\'"]\s*=>\s*is_string\s*\(\s*\$host\s*\)/',
            $connectivityAuditPageContents
        ) === 1
        || preg_match(
            '/[\'"]host[\'"]\s*=>\s*\$this->boundedText\s*\(\s*isset\s*\(\s*\$result\s*\[\s*[\'"]host[\'"]\s*\]/',
            $connectivityAuditPageContents
        ) === 1
    ) {
        $failures[] = 'ConnectivityAuditPage host fields must not bypass display-host suppression.';
    }
}

$connectivityAuditViewFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $connectivityAuditViewRelativePath);
$connectivityAuditViewContents = file_get_contents($connectivityAuditViewFile);

if ($connectivityAuditViewContents === false) {
    $failures[] = 'Connectivity Audit view is missing or unreadable.';
} else {
    $requiredConnectivityViewSnippets = array(
        '$data[\'title\']',
        'method="post"',
        'smce_connectivity_audit',
        'wp_nonce_field',
        'name="source_ids[]"',
        'Run Audit',
        'Current Request Results',
        'esc_html',
        'esc_attr',
    );

    foreach ($requiredConnectivityViewSnippets as $snippet) {
        if (strpos($connectivityAuditViewContents, $snippet) === false) {
            $failures[] = 'Connectivity Audit view missing required Phase 1I control: '
                . $snippet;
        }
    }

    $forbiddenConnectivityViewRegexes = array(
        '/\bSelect\s+All\b/i',
        '/admin-post\.php/i',
        '/type\s*=\s*["\'](?:url|file)["\']/i',
        '/name\s*=\s*["\'](?:url|feed_url|domain|allowed_domains|source_type|parser_profile|enabled|manual_only)["\']/i',
        '/<script\b/i',
        '/\bon(click|change|submit|load|keydown|keyup|keypress|focus|blur|input)\s*=/i',
        '/\b(Edit|Check|Enable|Disable|Delete|Import)\b/',
        '/assets\/admin\.js/i',
    );

    foreach ($forbiddenConnectivityViewRegexes as $pattern) {
        if (preg_match($pattern, $connectivityAuditViewContents) === 1) {
            $failures[] = 'Connectivity Audit view contains a forbidden control: ' . $pattern;
        }
    }

    if (strpos($connectivityAuditViewContents, 'Truncated response') === false) {
        $failures[] = 'Connectivity Audit view must render the truncated-response annotation.';
    }

    if (strpos($connectivityAuditViewContents, "\$result['truncated']") === false) {
        $failures[] = 'Connectivity Audit view must gate the annotation on the truncated flag.';
    }

    if (strpos($connectivityAuditViewContents, 'truncated_prefix') !== false) {
        $failures[] = 'Connectivity Audit view must not render truncated prefix content.';
    }
}

$safeFeedFetcherFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $safeFeedFetcherRelativePath);
$safeFeedFetcherContents = file_get_contents($safeFeedFetcherFile);

if ($safeFeedFetcherContents === false) {
    $failures[] = 'SafeFeedFetcher is missing or unreadable for Phase 1I checks.';
} else {
    $legacyFetcherEvidence = array(
        'private const TIMEOUT_SECONDS = 8',
        'private const MAX_REDIRECT_HOPS = 3',
        'private const MAX_BODY_BYTES = 2097152',
        'private const LIMIT_RESPONSE_SIZE = 2097153',
        'public function fetch($feedUrl, array $allowedDomains)',
        '$response = $this->performRequest($currentUrl)',
        "'error_code' =>",
        "'requested_url' =>",
        "'final_url' =>",
        "'response_size' =>",
        "'body' =>",
    );

    foreach ($legacyFetcherEvidence as $snippet) {
        if (strpos($safeFeedFetcherContents, $snippet) === false) {
            $failures[] = 'Existing SafeFeedFetcher::fetch behavior evidence changed or is missing: '
                . $snippet;
        }
    }

    $requiredAuditFetcherEvidence = array(
        'function fetchForConnectivityAudit(',
        'AUDIT_TIMEOUT_SECONDS = 3',
        'AUDIT_MAX_REDIRECT_HOPS = 2',
        'AUDIT_MAX_BODY_BYTES = 131072',
        'AUDIT_LIMIT_RESPONSE_SIZE = 131073',
        "'timeout' => self::AUDIT_TIMEOUT_SECONDS",
        "'redirection' => 0",
        "'reject_unsafe_urls' => true",
        "'sslverify' => true",
        "'cookies' => array()",
        "'limit_response_size' => self::AUDIT_LIMIT_RESPONSE_SIZE",
        'wp_safe_remote_get($url, $args)',
        'in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)',
        '$this->urlGuard->validate($requestedUrl, $allowedDomains)',
        '$this->urlGuard->validate(',
        'resolveAuditRedirectLocation',
        'redirect_domain_blocked',
        'redirect_limit_exceeded',
        'response_too_large',
        'auditElapsedMilliseconds',
    );

    foreach ($requiredAuditFetcherEvidence as $snippet) {
        if (strpos($safeFeedFetcherContents, $snippet) === false) {
            $failures[] = 'SafeFeedFetcher missing required connectivity-audit control: '
                . $snippet;
        }
    }

    $requiredAuditTruncationEvidence = array(
        'AUDIT_CLASSIFICATION_PREFIX_BYTES = 16384',
        'substr($body, 0, self::AUDIT_CLASSIFICATION_PREFIX_BYTES)',
        "'truncated_prefix' => \$truncatedPrefix",
        "'truncated' => true",
        "'truncated' => false",
        "'result_code' => 'response_too_large'",
        'unset($body)',
    );

    foreach ($requiredAuditTruncationEvidence as $snippet) {
        if (strpos($safeFeedFetcherContents, $snippet) === false) {
            $failures[] = 'SafeFeedFetcher missing required truncated-prefix control: '
                . $snippet;
        }
    }

    if (
        preg_match(
            '/AUDIT_CLASSIFICATION_PREFIX_BYTES\s*=\s*(\d+)/',
            $safeFeedFetcherContents,
            $auditPrefixBoundMatch
        ) !== 1
        || (int) $auditPrefixBoundMatch[1] > 16384
    ) {
        $failures[] = 'SafeFeedFetcher audit prefix bound must not exceed 16384 bytes.';
    }

    if (preg_match('/fetchForConnectivityAudit.*\$this->fetch\s*\(/s', $safeFeedFetcherContents) === 1) {
        $failures[] = 'fetchForConnectivityAudit must not call the existing public fetch method.';
    }
}

sort($phase1iNewFiles);
$expectedPhase1iNewFiles = array(
    'src/Admin/BulkConnectivityAuditService.php',
    'src/Admin/Pages/ConnectivityAuditPage.php',
    'views/admin/connectivity-audit.php',
);
sort($expectedPhase1iNewFiles);

if ($phase1iNewFiles !== $expectedPhase1iNewFiles || count($phase1iNewFiles) !== 3) {
    $failures[] = 'Phase 1I must add exactly the three approved files.';
}

sort($phase1iAllowedChangedPaths);
$expectedPhase1iAllowedChangedPaths = array(
    'src/Admin/BulkConnectivityAuditService.php',
    'src/Admin/Pages/ConnectivityAuditPage.php',
    'views/admin/connectivity-audit.php',
    'studymentor-content-engine.php',
    'readme.txt',
    'src/Core/Plugin.php',
    'src/Admin/Menu.php',
    'src/Http/SafeFeedFetcher.php',
    'tests/static-safety-check.php',
);
sort($expectedPhase1iAllowedChangedPaths);

if (
    $phase1iAllowedChangedPaths !== $expectedPhase1iAllowedChangedPaths
    || count($phase1iAllowedChangedPaths) !== 9
) {
    $failures[] = 'Phase 1I change-control list must contain exactly three new and six modified paths.';
}

$menuFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $menuRelativePath);
$menuContents = file_get_contents($menuFile);
$requiredImportedItemsSubmenuPattern = '/if\s*\(\s*\$this->featureFlags->isEnabled\(\s*[\'"]source_registry[\'"]\s*\)\s*\)'
    . '\s*\{.*add_submenu_page\(\s*[\'"]smce-dashboard[\'"]\s*,'
    . '\s*[\'"]Imported Items[\'"]\s*,\s*[\'"]Imported Items[\'"]\s*,'
    . '\s*[\'"]manage_options[\'"]\s*,\s*[\'"]smce-imported-items[\'"]\s*,'
    . '\s*array\(\s*\$this->importedItemsPage\s*,\s*[\'"]render[\'"]\s*\)\s*\)/s';

$requiredBulkSourcesSubmenuPattern = '/if\s*\(\s*\$this->featureFlags->isEnabled\(\s*[\'"]source_registry[\'"]\s*\)\s*\)'
    . '\s*\{.*add_submenu_page\(\s*[\'"]smce-dashboard[\'"]\s*,'
    . '\s*[\'"]Bulk Sources[\'"]\s*,\s*[\'"]Bulk Sources[\'"]\s*,'
    . '\s*[\'"]manage_options[\'"]\s*,\s*[\'"]smce-bulk-sources[\'"]\s*,'
    . '\s*array\(\s*\$this->bulkSourcesPage\s*,\s*[\'"]render[\'"]\s*\)\s*\)/s';

$requiredConnectivityAuditSubmenuPattern = '/if\s*\(\s*\$this->featureFlags->isEnabled\(\s*[\'"]source_registry[\'"]\s*\)\s*\)'
    . '\s*\{.*add_submenu_page\(\s*[\'"]smce-dashboard[\'"]\s*,'
    . '\s*[\'"]Connectivity Audit[\'"]\s*,\s*[\'"]Connectivity Audit[\'"]\s*,'
    . '\s*[\'"]manage_options[\'"]\s*,\s*[\'"]smce-connectivity-audit[\'"]\s*,'
    . '\s*array\(\s*\$this->connectivityAuditPage\s*,\s*[\'"]render[\'"]\s*\)\s*\)/s';

if (
    $menuContents === false
    || preg_match($requiredImportedItemsSubmenuPattern, $menuContents) !== 1
) {
    $failures[] = 'Imported Items submenu registration is missing or outside the source_registry branch.';
} elseif (
    strpos($menuContents, "'smce-manual-announcements'") === false
    || strpos($menuContents, "'smce-imported-items'")
        < strpos($menuContents, "'smce-manual-announcements'")
) {
    $failures[] = 'Imported Items submenu must be registered after Manual Intake.';
}

if (
    $menuContents === false
    || preg_match($requiredBulkSourcesSubmenuPattern, $menuContents) !== 1
) {
    $failures[] = 'Bulk Sources submenu registration is missing or outside the source_registry branch.';
} elseif (
    strpos($menuContents, "'smce-sources'") === false
    || strpos($menuContents, "'smce-bulk-sources'") === false
    || strpos($menuContents, "'smce-manual-announcements'") === false
    || strpos($menuContents, "'smce-bulk-sources'")
        < strpos($menuContents, "'smce-sources'")
    || strpos($menuContents, "'smce-manual-announcements'")
        < strpos($menuContents, "'smce-bulk-sources'")
) {
    $failures[] = 'Bulk Sources submenu must be registered after Sources and before Manual Intake.';
}

if (
    $menuContents === false
    || preg_match($requiredConnectivityAuditSubmenuPattern, $menuContents) !== 1
) {
    $failures[] = 'Connectivity Audit submenu registration is missing or outside the source_registry branch.';
} elseif (
    strpos($menuContents, "'smce-bulk-sources'") === false
    || strpos($menuContents, "'smce-connectivity-audit'") === false
    || strpos($menuContents, "'smce-manual-announcements'") === false
    || strpos($menuContents, "'smce-connectivity-audit'")
        < strpos($menuContents, "'smce-bulk-sources'")
    || strpos($menuContents, "'smce-manual-announcements'")
        < strpos($menuContents, "'smce-connectivity-audit'")
) {
    $failures[] = 'Connectivity Audit submenu must be registered after Bulk Sources and before Manual Intake.';
}

if (
    $menuContents === false
    || strpos($menuContents, "'smce-editorial'") === false
    || strpos($menuContents, "'smce-editorial-announcements'") === false
    || strpos($menuContents, "'smce-editorial-queue'") === false
) {
    $failures[] = 'Editorial Workspace submenu pages are missing from Menu registration.';
} elseif (
    strpos($menuContents, "'smce-diagnostics'") === false
    || strpos($menuContents, "'smce-editorial'")
        < strpos($menuContents, "'smce-diagnostics'")
    || strpos($menuContents, "'smce-editorial-announcements'")
        < strpos($menuContents, "'smce-editorial'")
    || strpos($menuContents, "'smce-editorial-queue'")
        < strpos($menuContents, "'smce-editorial-announcements'")
) {
    $failures[] = 'Editorial Workspace submenus must be registered after Diagnostics in workspace order.';
}

if (count($approvedFiles) !== 152) {
    $failures[] = 'Approved file inventory must contain exactly 152 files.';
}

$phpFilesToLint = array();
foreach ($approvedFiles as $relativePath) {
    if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'php') {
        $phpFilesToLint[] = $relativePath;
    }
}

if (!function_exists('exec')) {
    $failures[] = 'PHP CLI is unavailable; syntax lint could not run.';
} else {
    $phpBinary = defined('PHP_BINARY') && PHP_BINARY !== ''
        ? PHP_BINARY
        : 'php';

    $probeOutput = array();
    $probeExitCode = 0;
    $probeCommand = escapeshellarg($phpBinary) . ' -v';
    exec($probeCommand . ' 2>&1', $probeOutput, $probeExitCode);

    if ($probeExitCode !== 0 && $phpBinary !== 'php') {
        $phpBinary = 'php';
        $probeOutput = array();
        $probeExitCode = 0;
        $probeCommand = escapeshellarg($phpBinary) . ' -v';
        exec($probeCommand . ' 2>&1', $probeOutput, $probeExitCode);
    }

    if ($probeExitCode !== 0) {
        $failures[] = 'PHP CLI is unavailable; syntax lint could not run.';
    } else {
        foreach ($phpFilesToLint as $relativePath) {
            $absolutePath = $pluginDirectory
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (!is_file($absolutePath)) {
                $failures[] = 'PHP lint target missing: ' . $relativePath;
                continue;
            }

            $lintOutput = array();
            $lintExitCode = 0;
            $lintCommand = escapeshellarg($phpBinary)
                . ' -l '
                . escapeshellarg($absolutePath);
            exec($lintCommand . ' 2>&1', $lintOutput, $lintExitCode);

            if ($lintExitCode !== 0) {
                $failures[] = 'PHP lint failed: ' . $relativePath;

                foreach ($lintOutput as $line) {
                    $failures[] = 'PHP lint output: '
                        . normalizeLintOutputLine($line, $pluginDirectory);
                }
            }
        }
    }
}

$expectedFlags = array(
    'source_registry',
    'source_collection',
    'document_collection',
    'extraction',
    'ai_providers',
    'deduplication',
    'article_generation',
    'image_generation',
    'approval_workflow',
    'wordpress_publishing',
    'social_distribution',
    'newsletter_distribution',
    'scheduling',
);
$featureFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . 'src'
    . DIRECTORY_SEPARATOR
    . 'Core'
    . DIRECTORY_SEPARATOR
    . 'FeatureFlags.php';
$featureContents = file_get_contents($featureFile);
$foundFlags = array();

if ($featureContents !== false) {
    preg_match_all(
        '/[\'"]([a-z_]+)[\'"]\s*=>\s*(true|false)/',
        $featureContents,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $match) {
        $foundFlags[$match[1]] = $match[2];
    }
}

sort($expectedFlags);
$foundFlagNames = array_keys($foundFlags);
sort($foundFlagNames);

if ($featureContents === false || $foundFlagNames !== $expectedFlags) {
    $failures[] = 'Feature flag map is incomplete or contains unexpected flags.';
} else {
    foreach ($foundFlags as $name => $value) {
        if ($name === 'source_registry') {
            if ($value !== 'true') {
                $failures[] = 'source_registry feature flag must be true.';
            }
            continue;
        }

        if ($name === 'source_collection') {
            if ($value !== 'true') {
                $failures[] = 'source_collection feature flag must be true.';
            }
            continue;
        }

        if ($value !== 'false') {
            $failures[] = 'Feature flag is not false: ' . $name;
        }
    }
}

if (
    $featureContents === false
    || strpos($featureContents, 'public function isEnabled') === false
    || strpos($featureContents, '=== true') === false
) {
    $failures[] = 'Unknown feature flag fallback could not be verified.';
}

$cip002FoundationFiles = array(
    'src/Core/ServiceContainer.php' => array(
        'final class ServiceContainer',
        'public function has(',
        'public function get(',
        'public function set(',
        'public function factory(',
        'Service not registered:',
        'Duplicate service registration:',
    ),
    'src/Core/ModuleRegistry.php' => array(
        'final class ModuleRegistry',
        'Duplicate module registration:',
    ),
    'src/Core/ModuleLoader.php' => array(
        'final class ModuleLoader',
        "const STATE_NOT_STARTED = 'not_started'",
        "const STATE_LOADING = 'loading'",
        "const STATE_LOADED = 'loaded'",
        "const STATE_FAILED = 'failed'",
        'ModuleLoader load already in progress',
        'ModuleLoader already loaded',
        'ModuleLoader load previously failed',
    ),
    'src/Contracts/ModuleInterface.php' => array(
        'interface ModuleInterface',
        'function register(ServiceContainer $container)',
        'function boot(ServiceContainer $container)',
    ),
    'src/Modules/CorePlatformModule.php' => array(
        'final class CorePlatformModule',
        "return 'core_platform'",
    ),
    'src/Modules/SourceRegistryModule.php' => array(
        'final class SourceRegistryModule',
        "return 'source_registry'",
    ),
    'src/Modules/AcquisitionModule.php' => array(
        'final class AcquisitionModule',
        "return 'acquisition'",
    ),
    'src/Modules/AnnouncementModule.php' => array(
        'final class AnnouncementModule',
        "return 'announcement'",
    ),
    'src/Modules/BlueprintModule.php' => array(
        'final class BlueprintModule',
        "return 'blueprint'",
    ),
    'src/Modules/PromptContextModule.php' => array(
        'final class PromptContextModule',
        "return 'prompt_context'",
    ),
    'src/Modules/PromptPackageModule.php' => array(
        'final class PromptPackageModule',
        "return 'prompt_package'",
    ),
    'src/Modules/GenerationRequestModule.php' => array(
        'final class GenerationRequestModule',
        "return 'generation_request'",
    ),
    'src/Registry/CapabilityRegistry.php' => array(
        'final class CapabilityRegistry',
        'SOURCE_REGISTRY',
        'ACQUISITION',
        'PUBLISHING',
        'AI_PROVIDERS',
    ),
    'src/Registry/CapabilityFlagMapper.php' => array(
        'final class CapabilityFlagMapper',
        'isCapabilityEnabledByFlags',
    ),
    'src/Registry/VersionRegistry.php' => array(
        'final class VersionRegistry',
        'editorial-workspace-phase2',
    ),
    'src/Platform/PlatformDiagnostics.php' => array(
        'final class PlatformDiagnostics',
        'public function collect(',
    ),
);

foreach ($cip002FoundationFiles as $relativePath => $snippets) {
    $absolutePath = $pluginDirectory
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $contents = is_file($absolutePath) ? file_get_contents($absolutePath) : false;

    if ($contents === false) {
        $failures[] = 'CIP-002 foundation file missing: ' . $relativePath;
        continue;
    }

    foreach ($snippets as $snippet) {
        if (strpos($contents, $snippet) === false) {
            $failures[] = 'CIP-002 foundation file missing required snippet in '
                . $relativePath
                . ': '
                . $snippet;
        }
    }
}

$cip002ForbiddenRuntimePaths = array(
    'src/Publish/PublisherInterface.php',
    'src/Governance/ApprovalGateInterface.php',
    'src/Workflow/WorkflowStageInterface.php',
);

foreach ($cip002ForbiddenRuntimePaths as $relativePath) {
    if (is_file($pluginDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $failures[] = 'CIP-002 forbidden foundation file found: ' . $relativePath;
    }
}

$pluginBootstrapFile = $pluginDirectory
    . DIRECTORY_SEPARATOR
    . 'src'
    . DIRECTORY_SEPARATOR
    . 'Core'
    . DIRECTORY_SEPARATOR
    . 'Plugin.php';
$pluginBootstrapContents = file_get_contents($pluginBootstrapFile);

if ($pluginBootstrapContents === false) {
    $failures[] = 'Plugin.php is missing or unreadable for CIP-002 foundation checks.';
} else {
    $requiredPluginSnippets = array(
        'ServiceContainer',
        'ModuleRegistry',
        'ModuleLoader',
        'CorePlatformModule',
        'SourceRegistryModule',
        'AcquisitionModule',
        'AnnouncementModule',
        'BlueprintModule',
        'PromptContextModule',
        'PromptPackageModule',
        'GenerationRequestModule',
    );

    foreach ($requiredPluginSnippets as $snippet) {
        if (strpos($pluginBootstrapContents, $snippet) === false) {
            $failures[] = 'Plugin.php missing CIP-002 foundation wiring: ' . $snippet;
        }
    }

    if (
        strpos($pluginBootstrapContents, 'wp_schedule_') !== false
        || strpos($pluginBootstrapContents, 'register_rest_route') !== false
        || strpos($pluginBootstrapContents, 'wp_insert_post') !== false
    ) {
        $failures[] = 'Plugin.php must not introduce scheduling, REST or publishing.';
    }
}

if ($failures === array()) {
    $importedItemsViewFile = $pluginDirectory
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $importedItemsViewRelativePath);
    $importedItemsViewContents = file_get_contents($importedItemsViewFile);

    if ($importedItemsViewContents === false) {
        $failures[] = 'Imported Items view is missing or unreadable for Phase 1G.1 UI checks.';
    } else {
        if (strpos($importedItemsViewContents, 'class="widefat fixed striped"') !== false) {
            $failures[] = 'Imported Items list table must not use the fixed table layout class.';
        }

        $phase1g1UiChecks = array(
            'smce-imported-items-table-wrap' => 'Imported Items list table must use a dedicated horizontal-overflow wrapper.',
            'overflow-x' => 'Imported Items scoped styles must define horizontal overflow behavior.',
            'smce-imported-items' => 'Imported Items list UI must use a scoped SMCE wrapper.',
            'method="get"' => 'Imported Items filter form must remain GET-only.',
            'smce-imported-items-filters' => 'Imported Items must use a compact scoped filter layout.',
            'preg_match(\'/^(\\d{4}-\\d{2}-\\d{2})/\'' => 'Imported Items list must format publication dates as day-only when applicable.',
            'preg_match(\'/^(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}):\\d{2}$/\'' => 'Imported Items list must format Created timestamps without seconds when applicable.',
            'target="_blank"' => 'Imported Items canonical URL links must retain target="_blank".',
            'rel="noopener noreferrer"' => 'Imported Items canonical URL links must retain rel="noopener noreferrer".',
            'Pretty JSON' => 'Imported Items detail view must retain Pretty JSON rendering.',
            'Stored Payload Representation' => 'Imported Items detail view must retain Stored Payload Representation rendering.',
        );

        foreach ($phase1g1UiChecks as $snippet => $message) {
            if (strpos($importedItemsViewContents, $snippet) === false) {
                $failures[] = $message;
            }
        }

        if (preg_match('/class="widefat\s+striped/i', $importedItemsViewContents) !== 1) {
            $failures[] = 'Imported Items list table must retain widefat striped WordPress classes.';
        }

        if (preg_match('/href="<\?php\s+echo\s+esc_url\(\$canonicalHref\)/', $importedItemsViewContents) !== 1) {
            $failures[] = 'Imported Items list must keep the full canonical URL as the link href.';
        }

        if (preg_match('/href="<\?php\s+echo\s+esc_url\(\$item\[\'details_url\'\]\)/', $importedItemsViewContents) !== 1) {
            $failures[] = 'Imported Items Details link must remain a GET link based on details_url.';
        }

        if (preg_match('/method\s*=\s*["\']post["\']/i', $importedItemsViewContents) === 1) {
            $failures[] = 'Imported Items view must not contain POST forms.';
        }
    }
}

if ($failures !== array()) {
    echo "Static safety check failed.\n";

    foreach ($failures as $failure) {
        echo '- ' . $failure . "\n";
    }

    exit(1);
}

echo "Static safety check passed.\n";
exit(0);
