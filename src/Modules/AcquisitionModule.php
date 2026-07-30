<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Acquisition\AcquisitionDiagnostics;
use StudyMentor\ContentEngine\Acquisition\AcquisitionEngine;
use StudyMentor\ContentEngine\Acquisition\AcquisitionManager;
use StudyMentor\ContentEngine\Acquisition\DownloadManager;
use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Collectors\SafeFeedCollector;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Evidence\InMemoryEvidenceRepository;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;
use StudyMentor\ContentEngine\Feed\FeedPreviewParser;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;
use StudyMentor\ContentEngine\Registry\AsepHtmlParserHandler;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\FeedPreviewParserHandler;
use StudyMentor\ContentEngine\Registry\ParserRegistry;
use StudyMentor\ContentEngine\Registry\VersionRegistry;

defined('ABSPATH') || exit;

final class AcquisitionModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'acquisition';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(SourceAcquisitionService::class)) {
            $container->factory(
                SourceAcquisitionService::class,
                static function (ServiceContainer $c) {
                    return new SourceAcquisitionService(
                        $c->get(SourceRepository::class),
                        $c->get(AcquisitionEngine::class)
                    );
                }
            );
        }

        if (!$container->has(SourceCheckService::class)) {
            $container->factory(
                SourceCheckService::class,
                static function (ServiceContainer $c) {
                    return new SourceCheckService(
                        $c->get(SourceAcquisitionService::class)
                    );
                }
            );
        }

        if (!$container->has(CollectorRegistry::class)) {
            $container->factory(
                CollectorRegistry::class,
                static function (ServiceContainer $c) {
                    $registry = new CollectorRegistry();
                    $registry->register(new SafeFeedCollector($c->get(SafeFeedFetcher::class)));

                    return $registry;
                }
            );
        }

        if (!$container->has(ParserRegistry::class)) {
            $container->factory(
                ParserRegistry::class,
                static function (ServiceContainer $c) {
                    $registry = new ParserRegistry();
                    $registry->register(
                        new FeedPreviewParserHandler($c->get(FeedPreviewParser::class))
                    );
                    $registry->register(
                        new AsepHtmlParserHandler($c->get(AsepAnnouncementsHtmlParser::class))
                    );

                    return $registry;
                }
            );
        }

        if (!$container->has(FingerprintService::class)) {
            $container->set(FingerprintService::class, new FingerprintService());
        }

        if (!$container->has(DownloadManager::class)) {
            $container->set(DownloadManager::class, new DownloadManager());
        }

        if (!$container->has(EvidenceRepositoryInterface::class)) {
            $container->set(
                EvidenceRepositoryInterface::class,
                new InMemoryEvidenceRepository()
            );
        }

        if (!$container->has(AcquisitionDiagnostics::class)) {
            $container->factory(
                AcquisitionDiagnostics::class,
                static function (ServiceContainer $c) {
                    return new AcquisitionDiagnostics(
                        $c->get(CollectorRegistry::class),
                        $c->get(ParserRegistry::class),
                        $c->get(EvidenceRepositoryInterface::class),
                        $c->get(CapabilityRegistry::class),
                        $c->get(VersionRegistry::class),
                        $c->get(FingerprintService::class)
                    );
                }
            );
        }

        if (!$container->has(AcquisitionManager::class)) {
            $container->factory(
                AcquisitionManager::class,
                static function (ServiceContainer $c) {
                    return new AcquisitionManager(
                        $c->get(CollectorRegistry::class),
                        $c->get(DownloadManager::class),
                        $c->get(FingerprintService::class),
                        $c->get(EvidenceRepositoryInterface::class),
                        $c->get(ParserRegistry::class)
                    );
                }
            );
        }

        if (!$container->has(AcquisitionEngine::class)) {
            $container->factory(
                AcquisitionEngine::class,
                static function (ServiceContainer $c) {
                    return new AcquisitionEngine(
                        $c->get(AcquisitionManager::class),
                        $c->get(AcquisitionDiagnostics::class)
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
        $collectorRegistry = $container->get(CollectorRegistry::class);

        if ($collectorRegistry instanceof CollectorRegistry) {
            $collectorRegistry->mapSourceType('rss', 'safe_feed');
            $collectorRegistry->mapSourceType('atom', 'safe_feed');
            $collectorRegistry->mapSourceType('html', 'safe_feed');
            $collectorRegistry->mapSourceType('json', 'safe_feed');
            $collectorRegistry->mapSourceType('xml', 'safe_feed');
            $collectorRegistry->mapSourceType('pdf', 'safe_feed');
        }
    }
}
