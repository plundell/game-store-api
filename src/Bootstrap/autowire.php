<?php

/**
 * This file sets up all dependency injection for the application.
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Bootstrap\ContainerBootstrap;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {

    //Then we dynamically load all the routes defined throughout the app
    (new DynamicLoader('AutowireIncludes.php'))
        ->findFiles('.autowire.php', ContainerBootstrap::class)
        ->setArgs($containerBuilder)
        ->createCacheFile()
        ->load();
};
