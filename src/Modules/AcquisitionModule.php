<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;
use StudyMentor\ContentEngine\Feed\FeedPreviewParser;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;

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
        if (!$container->has(SourceCheckService::class)) {
            $container->factory(
                SourceCheckService::class,
                static function (ServiceContainer $c) {
                    return new SourceCheckService(
                        $c->get(SourceRepository::class),
                        $c->get(SafeFeedFetcher::class),
                        $c->get(FeedPreviewParser::class),
                        $c->get(AsepAnnouncementsHtmlParser::class)
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
