<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Bootstrap\ContainerBootstrap;
use DI\ContainerBuilder;
use \App\Persistence\MySQL;
use \App\Persistence\SQLite;
use \App\Persistence\Persistence;

return new class implements ContainerBootstrap {

    public function __invoke(ContainerBuilder $containerBuilder): void
    {
        // get the persistence type from an environment variable and map it to a class
        $type = strtolower($_ENV['PERSISTENCE_TYPE'] ?? 'sqlite');
        switch ($type) {
            case 'mysql':
                $cls = MySQL::class;
                break;
            case 'sqlite':
                $cls = SQLite::class;
                break;
            default:
                throw new \LogicException(sprintf('Unknown PERSISTENCE_TYPE "%s"', $type));
        }
        echo "Autowiring persistence class $cls\n";
        // Use the PersistenceInterface as key and map it to the autowired provider class.
        $containerBuilder->addDefinitions([
            Persistence::class => \DI\autowire($cls),
        ]);
    }
};
