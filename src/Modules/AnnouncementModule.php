<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Announcement\AnnouncementIdentityService;
use StudyMentor\ContentEngine\Announcement\AnnouncementItemExtractor;
use StudyMentor\ContentEngine\Announcement\AnnouncementLifecycleService;
use StudyMentor\ContentEngine\Announcement\EditorialIngestionService;
use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceItemRepository;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\ParserRegistry;

defined('ABSPATH') || exit;

final class AnnouncementModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'announcement';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(AnnouncementIdentityService::class)) {
            $container->set(AnnouncementIdentityService::class, new AnnouncementIdentityService());
        }

        if (!$container->has(AnnouncementItemExtractor::class)) {
            $container->set(AnnouncementItemExtractor::class, new AnnouncementItemExtractor());
        }

        if (!$container->has(AnnouncementLifecycleService::class)) {
            $container->factory(
                AnnouncementLifecycleService::class,
                static function (ServiceContainer $c) {
                    return new AnnouncementLifecycleService(
                        $c->get(SourceItemRepository::class),
                        $c->get(AnnouncementIdentityService::class)
                    );
                }
            );
        }

        if (!$container->has(EditorialIngestionService::class)) {
            $container->factory(
                EditorialIngestionService::class,
                static function (ServiceContainer $c) {
                    return new EditorialIngestionService(
                        $c->get(SourceAcquisitionService::class),
                        $c->get(AnnouncementItemExtractor::class),
                        $c->get(AnnouncementLifecycleService::class),
                        $c->get(CapabilityRegistry::class),
                        $c->get(CollectorRegistry::class),
                        $c->get(ParserRegistry::class)
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
        if (!$container->has(PlatformDiagnostics::class)) {
            return;
        }

        $platformDiagnostics = $container->get(PlatformDiagnostics::class);
        $lifecycleService = $container->get(AnnouncementLifecycleService::class);
        $ingestionService = $container->get(EditorialIngestionService::class);

        if (
            $platformDiagnostics instanceof PlatformDiagnostics
            && $lifecycleService instanceof AnnouncementLifecycleService
            && $ingestionService instanceof EditorialIngestionService
        ) {
            $platformDiagnostics->bindAnnouncementSpine(
                $lifecycleService,
                $ingestionService
            );
        }
    }
}
