<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Dotenv\Dotenv;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\App;
use App\Application\Settings\SettingsInterface;

/**
 * First define some paths.
 *
 * devnote 2024-11-04:                                                                                 : i think this needs to be done before autoloading in order to make
 * them accessible everywhere... php docs says this isn't necessary after php7,
 * but I had issues earlier when I tried defining stuff after autoloading so
 * trying this for now...
 */
define('BOOT_DIR', __DIR__);
define('ROOT_DIR', realpath(__DIR__ . '/../../'));
define('SRC_DIR', ROOT_DIR . '/src/');
define('CONFIG_DIR', ROOT_DIR . '/config/');
define('VENDOR_DIR', ROOT_DIR . '/vendor/');
define('CACHE_DIR', ROOT_DIR . '/var/cache/');
define('LOG_DIR', ROOT_DIR . '/logs/');

// Enable vendor autoloading
require VENDOR_DIR . '/autoload.php';


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
    public SettingsInterface $settings;


    private function __construct()
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        /** 
         * When running in production we'll want to have caching enabled. The way Slim
         * implements caching is to write cache files on the first execution and then
         * load them on subsequent executions. That implies that if we change something
         * in the source files those changes won't be reflected in the cache, so we have 
         * to delete the cache to see those changes. As such, whenever we run in dev we
         * empty the cache on each execution.
         * 
         * NOTE we implement our own caching in routes.php which also relies on this 
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

        //No enable compilation. In dev this will happen every time thanks to the above 
        //delete (which is good cause we'll know it works), and in prod it'll only happen
        //on first run.
        $containerBuilder->enableCompilation(CACHE_DIR);

        // Prepare the container for building...
        (require __DIR__ . '/settings.php')($containerBuilder);
        (require __DIR__ . '/dependencies.php')($containerBuilder);
        (require __DIR__ . '/repositories.php')($containerBuilder);

        // Build PHP-DI Container instance
        $container = $containerBuilder->build();

        //Extract the settings so we can expose them below
        $settings = $container->get(SettingsInterface::class);
        if (!$settings instanceof SettingsInterface) {
            throw new \RuntimeException(self::wrongtype(SettingsInterface::class, $settings));
        }
        //devnote: we use if-block instead of assert() because the later can be disabled in .ini

        // Instantiate the app
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        if (!$app instanceof App) {
            throw new \RuntimeException(self::wrongtype(App::class, $app));
        }

        // Register middleware
        //palun: this adds sessions, which I think we want to run with JWTs instead
        $middleware = require __DIR__ . '/middleware.php';
        $middleware($app);

        // Register routes
        $routes = require __DIR__ . '/routes.php';
        $routes($app);

        //Finally expose everything
        $this->settings = $settings;
        $this->app = $app;
        $this->container = $container;
    }

    public static function gettype(mixed &$var): string
    {
        ob_start();
        var_dump($var);
        $str = ob_get_clean();
        return is_string($str) ? $str : 'unknown';
    }

    public static function wrongtype(string $expected, mixed &$got): string
    {
        return "Expected $expected, got " . self::gettype($got);
    }

    /** 
     * Bootstrap the app
     * 
     * This factory method serves no purpose other than the naming being 
     * more correct than 'new'
     */
    public static function run(): self
    {
        return new self();
    }
}
