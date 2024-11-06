<?php

declare(strict_types=1);

namespace App\Bootstrap\Interfaces;

use DI\ContainerBuilder;

/**
 * This interface should be implemented by all any bootstrapping which needs
 * the DI ContainerBuilder. 
 * 
 * TODO: find a better name for this. what's the defining difference between what is registered on the
 * container and what is registered on the app??
 */

interface AutowireLoader

{
    public function __invoke(ContainerBuilder $containerBuilder): void;
}
