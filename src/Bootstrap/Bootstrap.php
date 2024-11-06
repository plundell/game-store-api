<?php

/**
 * Bootstrap.php
 * 
 * This file contains the necessary bootstraping code for this app.
 * 
 * Just requireing this file will define path constants and load the env file.
 * 
 * Running the returned class create an instance of the Slim\App which can
 * be used both in tests and the actual api.
 */

declare(strict_types=1);

namespace App\Bootstrap;

use Dotenv\Dotenv;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Common\Settings;
use Monolog\Logger;
use App\Common\Exceptions\CustomException;

/**
 * First define some paths.
 *
 * devnote 2024-11-04:                                                                                 : i think this needs to be done before autoloading in order to make
 * them accessible everywhere... php docs says this isn't necessary after php7,
 * but I had issues earlier when I tried defining stuff after autoloading so
 * trying this for now...
 */
define('BOOT_DIR', __DIR__ . '/');
define('ROOT_DIR', realpath(__DIR__ . '/../../'));
define('BUILD_DIR', ROOT_DIR . '/build/');
define('SRC_DIR', ROOT_DIR . '/src/');
define('CONFIG_DIR', ROOT_DIR . '/config/');
define('VENDOR_DIR', ROOT_DIR . '/vendor/');
define('CACHE_DIR', ROOT_DIR . '/var/cache/');
define('LOG_DIR', ROOT_DIR . '/logs/');
define('COMMON_DIR', ROOT_DIR . '/common/');

// Enable vendor autoloading
require_once VENDOR_DIR . 'autoload.php';

// Include our custom autoloader to deal with files like SlimAppBootstrap.interface.php
require_once BOOT_DIR . 'CustomAutoloader.php';



//Then try to load the env file...
try {
    (Dotenv::createImmutable(ROOT_DIR))->load();
} catch (\Throwable $e) {
    trigger_error(sprintf("Failed to load env file: %s", $e->getMessage()), E_USER_WARNING);
}

//...and make sure APP_ENV is an accepted value
if (!in_array($_ENV['APP_ENV'], ['dev', 'prod'])) {
    trigger_error(sprintf("APP_ENV must be set to 'dev' or 'prod', got '%s'", $_ENV['APP_ENV']), E_USER_NOTICE);
    $_ENV['APP_ENV'] = 'prod';
}

class Bootstrap
{
    public App $app;
    public ContainerInterface $container;
    public Settings $settings;

    private function __construct()
    {
        /** 
         * When running in production we'll want to have caching enabled. The way Slim
         * implements caching is to write cache files on the first execution and then
         * load them on subsequent executions. That implies that if we change something
         * in the source files those changes won't be reflected in the cache, so we have 
         * to delete the cache to see those changes. As such, whenever we run in dev we
         * empty the cache on each execution.
         * 
         * NOTE we implement our own caching in DynamicLoader.php which also relies on this 
         * emptying happening.
         */
        if ($_ENV['APP_ENV'] !== 'prod') {
            $files = scandir(CACHE_DIR);
            if ($files) {
                foreach ($files as $file) {
                    if (is_file(CACHE_DIR . $file)) {
                        unlink(CACHE_DIR . $file);
                    }
                }
            }
        }

        // Build PHP-DI Container instance (true => use cache)
        $this->container = self::dependencyInjection(true);

        //Expose the settings object (we always need it, so no reason to delay fetching/autowiring)
        //palun: maybe we want to split settings up more, so when testing we don't have to load
        //the whole thing?
        $this->settings = $this->container->get(Settings::class);
        if (!$this->settings instanceof Settings) {
            throw new \RuntimeException(CustomException::wrongtype(Settings::class, $this->settings));
            //devnote: we use if-block instead of assert() because the later can be disabled in .ini
        }


        // Instantiate the app
        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();
        if (!$this->app instanceof App) {
            throw new \RuntimeException(CustomException::wrongtype(App::class, $this->app));
        }


        //Devnote: don't register routes here so we can use this bootstrap in tests
        //where we just want to load certain routes


        //Aaaaaand we're done

    }


    /**
     * Register all routes of the application. 
     * 
     * This will unitilize the DynamicLoader.php which scans the src folder and loads 
     * all the *.route.php files.
     * 
     * It also enables OPTIONS response to all routes, and sets a default route which
     * shows a list of all available routes.
     */
    public function registerRoutes()
    {
        //Activat caching of route matching expressions and arguments
        //  https://www.slimframework.com/docs/v4/objects/routing.html#route-expressions-caching
        $this->app->getRouteCollector()->setCacheFile(constant("CACHE_DIR") . 'RouteExpressions.php');

        /** 
         * Add OPTIONS response to all routes (this is important for CORS Pre-Flight requests
         * which is interested in the Access-Control-Allow-* HTTP header which is set in index.php)
         */
        $this->app->options('/.*', function (Request $request, Response $response) {
            return $response;
        });

        //Then we dynamically load all the routes defined throughout the app
        DynamicLoader::run([
            'cachefile' => 'RouteIncludes',
            'pattern' => '*.routes.php',
            'interface' => Interfaces\RoutesLoader::class,
            'args' => [$this->app]
        ]);

        // Finally we set the default route to show a list of all available routes,
        // kind of like a help page
        $this->setDefaultRouteToListRoutes();
    }



    protected function setDefaultRouteToListRoutes()
    {
        $routes = $this->app->getRouteCollector()->getRoutes();
        $this->app->get('/', function (Request $request, Response $response) use ($routes) {
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
    }


    static protected function scanForAutowireConfigs(ContainerBuilder $containerBuilder)
    {
        DynamicLoader::run([
            'cachefile' => 'AutowireIncludes',
            'pattern' => '*.autowire.php',
            'interface' => Interfaces\AutowireLoader::class,
            'args' => [$containerBuilder]
        ]);
    }


    static protected function scanForSettings(ContainerBuilder $containerBuilder)
    {
        // Global Settings Object
        $containerBuilder->addDefinitions([
            Settings::class => function () {
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
    }




    /**
     * Clears the CACHE_DIR. Won't run in production unless $force is set to true
     * 
     * @param bool $force Clear the cache even in production? Defaults to false
     */
    public static function clearCache($force = false)
    {
        if ($force || $_ENV['APP_ENV'] !== 'prod') {
            $files = scandir(CACHE_DIR);
            if ($files) {
                foreach ($files as $file) {
                    if (is_file(CACHE_DIR . $file)) {
                        unlink(CACHE_DIR . $file);
                    }
                }
            }
        }
    }

    /**
     * Setup a PHP-DI Container which provides access to settings 
     * and autowired services
     * 
     * @return ContainerInterface
     */
    public static function dependencyInjection($useCache = false): ContainerInterface
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        //Now enable compilation. In dev this will happen every time thanks to the above 
        //delete (which is good cause we'll know it works), and in prod it'll only happen
        //on first run.
        if ($useCache) {
            $containerBuilder->enableCompilation(CACHE_DIR);
        }

        //Scan file .autowire.php files
        self::scanForAutowireConfigs($containerBuilder);

        //TODO: this should scan for .settings.php files but right now it just
        //loads some settings inline in this method
        self::scanForSettings($containerBuilder);

        // Build PHP-DI Container instance
        return  $containerBuilder->build();
    }


    /** 
     * Bootstrap the app
     * 
     * This factory method serves no purpose other than the naming being 
     * more correct than 'new'
     */
    public static function app(): self
    {
        //devnote: the way tests are currently setup we can't use a singleton here
        return new self();
    }
}
