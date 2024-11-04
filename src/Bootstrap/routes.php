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


    /**
     * Scans the filesystem for all *.routes.php files under the SRC_DIR, caches
     * that list in a file in CACHE_DIR, then loads said file and returns the
     * resulting closure which expects a Slim\App instance. 
     * @param App $app The Slim app instance we want to register our routes with
     * @return void
     */
    $dynamicallyLoadRoutes = function (App $app): void {
        //Choose any name for the resulting file, just make sure it's in the cache dir since that gets
        //emptied by bootstrap.php
        $cachefile = constant("CACHE_DIR") . 'RouteIncludes.php';

        // If it doesn't exist we'll create it now 
        if (!file_exists($cachefile)) {
            //It's not really a cache , it's just a single file with multiple require statements, so 
            //it's not that efficient but at least we don't have to scan the filesystem on every request.
            //When we start getting hundreds of routes files we can look at actually caching the contents 
            //of each file, but for now this will do. So, we  start building the contents here.. 
            $cls = App::class;
            $contents = "<?php\n\nreturn function ($cls \$app) {\n";

            //So, we simply scan for all *.routes.php files under the SRC_DIR...
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(constant('SRC_DIR')));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo instanceof \SplFileInfo && $fileInfo->isFile()) {
                    $filepath = $fileInfo->getPathname();
                    if (substr_compare($filepath, '.routes.php', -strlen('.routes.php')) === 0) {
                        // echo "Found route file $filepath\n";
                        try {
                            //Try loading the file now, making sure it returns a RoutesFilesInterface object
                            $closure = require $filepath;
                            if (!is_object($closure) || !($closure instanceof RoutesFilesInterface)) {
                                throw new \Exception("File didn't return a RoutesFilesInterface object");
                            }
                            try {
                                //Load the file now to make sure it works
                                $closure($app);
                                $relpath = substr($filepath, strlen(constant('SRC_DIR')));
                                //If we get this far we know we have a good file, but despite that
                                //we play it safe and wrap it in a try-catch block...
                                $contents .= "\n\ttry {";
                                $contents .= "\n\t\t\$filepath = constant('SRC_DIR') . '$relpath';";
                                $contents .= "\n\t\t(require \$filepath)(\$app);";
                                $contents .= "\n\t} catch (\\Throwable \$e) {";
                                $contents .= "\n\t\terror_log(\"Error registering routes in '$relpath' : {\$e->getMessage()}\");\n\t}";
                            } catch (\Throwable $e) {
                                throw new \Exception("Error registering routes. " . $e->getMessage());
                            }
                        } catch (\Throwable $e) {
                            error_log("Error in route file $filepath : " . $e->getMessage());
                        }
                    }
                }
            }
            //...and finally we end the file with a closing "}" and write it to the cache.
            $contents .= "\n\n};";
            file_put_contents($cachefile, $contents);
            //Now we're done. All the files have already been loaded inside the loop above so 
            //no need to load again
        } else {
            //If we already have a "cache" file, we'll load it now
            (require $cachefile)($app);
        }
    };



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


    //Then we load all the routes defined throughout the app
    $dynamicallyLoadRoutes($app);


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
