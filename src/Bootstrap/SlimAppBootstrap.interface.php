<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Slim\App;

/**
 * This interface should be implemented by all any bootstrapping which needs
 * the Slim\App to register on. 
 */
interface SlimAppBootstrap
{
    public function __invoke(App $app): void;
}
