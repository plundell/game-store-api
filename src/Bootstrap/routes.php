<?php

/**
 * This file sets up all the routes for the application, however the routes aren't
 * defined here, instead they're defined in *.routes.php files in various folders.
 * 
 * This file then searches for all those files and loads them dynamically. Since
 * we don't want to do that on every request we'll cache the paths to those files
 * on the first request and then load that cache on subsequent requests (see 
 * the comment in bootstrap.php by enableCompilation() for more info). See bootstrap.php
 * which empties explains more about emptying the cache etc.
 * 
 * devnote: we tried using the caching feature of Slim, but it only caches the routes
 *          matching expressions and arguments, nothing about the handlers, which is why
 *          we're doing it this way. Read more here:
 *            https://www.slimframework.com/docs/v4/objects/routing.html#route-expressions-caching
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Bootstrap\SlimAppBootstrap;


return function (App $app) {


    //Start by activating caching of route matching expressions and arguments
    //  https://www.slimframework.com/docs/v4/objects/routing.html#route-expressions-caching
    $app->getRouteCollector()->setCacheFile(constant("CACHE_DIR") . 'RouteExpressions.php');



    //Then we load a few bit and bobs right here

    /** 
     * CORS Pre-Flight OPTIONS Request Handler. All the client is interested in
     * here is the Access-Control-Allow-* HTTP header which is being set on all
     * responses by ResponseEmitter.php
     * 
     * TODO: when moving ^ make sure to change this comment
     */
    $app->options('/.*', function (Request $request, Response $response) {
        return $response;
    });


    //Then we dynamically load all the routes defined throughout the app
    (new DynamicLoader('RouteIncludes.php'))
        ->findFiles('.routes.php', SlimAppBootstrap::class)
        ->setArgs($app)
        ->createCacheFile()
        ->load();



    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });


    // Finally we set the default route to show a list of all available routes, kind of like a help page
    $routes = $app->getRouteCollector()->getRoutes();
    $app->get('/', function (Request $request, Response $response) use ($routes) {
        $response = $response->withHeader('Content-Type', 'text/html');
        $body = $response->getBody();
        $body->write('<pre>');
        foreach ($routes as $route) {
            foreach ($route->getMethods() as $method) {
                $body->write(sprintf("%s %s\n", $method, $route->getPattern()));
            }
        }
        $body->write('</pre>');
        return $response;
    });
};
