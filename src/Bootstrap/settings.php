<?php
//TODO: split this appart into files in config dir

declare(strict_types=1);



use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;



return function (ContainerBuilder $containerBuilder) {

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => $_ENV['APP_ENV'] !== 'prod', //turn off in production
                'logError'            => $_ENV['APP_ENV'] === 'prod', //turn on in production
                'logErrorDetails'     => $_ENV['APP_ENV'] === 'prod', //turn on in production
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : LOG_DIR . '/app.log',
                    'level' => Logger::DEBUG,
                ],
            ]);
        }
    ]);
};
