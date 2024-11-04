<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Slim\App;

/**
 * This interface should be implemented by all routes files which
 * are dynamically loaded by routes.php 
 */

interface RoutesFilesInterface
{
    public function __invoke(App $app): void;
}
