<?php

use App\Providers\AnnouncementAcquisitionServiceProvider;
use App\Providers\EditorialServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PlatformServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    AnnouncementAcquisitionServiceProvider::class,
    EditorialServiceProvider::class,
];
