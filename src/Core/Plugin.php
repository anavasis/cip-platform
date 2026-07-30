<?php

namespace StudyMentor\ContentEngine\Core;

use StudyMentor\ContentEngine\Admin\Menu;
use StudyMentor\ContentEngine\Admin\SourceActionHandler;
use StudyMentor\ContentEngine\Admin\SourceCatalogActionHandler;
use StudyMentor\ContentEngine\Admin\SourceItemActionHandler;
use StudyMentor\ContentEngine\Modules\AcquisitionModule;
use StudyMentor\ContentEngine\Modules\CorePlatformModule;
use StudyMentor\ContentEngine\Modules\SourceRegistryModule;

defined('ABSPATH') || exit;

final class Plugin
{
    private $menu;
    private $sourceActionHandler;
    private $sourceCatalogActionHandler;
    private $sourceItemActionHandler;

    public function __construct()
    {
        $container = new ServiceContainer();
        $moduleRegistry = new ModuleRegistry();
        $container->set(ModuleRegistry::class, $moduleRegistry);

        $moduleRegistry->register(new CorePlatformModule());
        $moduleRegistry->register(new SourceRegistryModule());
        $moduleRegistry->register(new AcquisitionModule());

        $moduleLoader = new ModuleLoader($moduleRegistry, $container);
        $moduleLoader->load();

        $this->menu = $container->get(Menu::class);
        $this->sourceActionHandler = $container->get(SourceActionHandler::class);
        $this->sourceCatalogActionHandler = $container->get(SourceCatalogActionHandler::class);
        $this->sourceItemActionHandler = $container->get(SourceItemActionHandler::class);
    }

    public function boot()
    {
        add_action('admin_menu', array($this->menu, 'register'));
        $this->sourceActionHandler->register();
        $this->sourceCatalogActionHandler->register();
        $this->sourceItemActionHandler->register();
    }
}
