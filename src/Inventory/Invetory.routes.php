<?php

declare(strict_types=1);

namespace App\Inventory;

// require_once constant('BOOT_DIR') . '/RoutesFiles.interface.php';

use App\Bootstrap\Interfaces\RoutesLoader;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;



return new class implements RoutesLoader {

    public function __invoke(App $app): void
    {
        $app->group('/inventory', function (Group $group) {
            $group->get('/{id}', function (Request $request, Response $response, array $args) {
                $response->getBody()->write($args['id'] ?? 'i got nothing');
                return $response;
            });
        });
    }
};
