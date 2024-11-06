<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Bootstrap\Interfaces\AutowireLoader;
use DI\ContainerBuilder;
use \App\Persistence\Persistence;

return new class implements AutowireLoader {

    public function __invoke(ContainerBuilder $containerBuilder): void
    {
        // get the persistence type from an environment variable and map it to a class
        $type = strtolower($_ENV['PERSISTENCE_TYPE'] ?? 'sqlite');
        switch ($type) {
            case 'mysql':
                $cls = \App\Persistence\MySQL::class;
                break;
            case 'sqlite':
                $cls = \App\Persistence\SQLite::class;
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
