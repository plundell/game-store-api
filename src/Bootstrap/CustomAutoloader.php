<?php

declare(strict_types=1);

namespace App\Bootstrap;


/**
 * Custom autoloader which can load classes in the "App" namespace which have a 
 * suffix in the filename, eg. SlimAppBootstrap.interface.php
 */
class CustomAutoloader
{

    /**
     * This is where we should add suffixes as more are needed
     */
    protected static array $suffixes = [
        'class',
        'interface',
        'abstract',
        'routes',
        'settings',
        'autowire'
    ];

    /**
     * @param string $class The name of the class we're looking for
     */
    public static function load(string $class): void
    {
        // Check if the class is in our namespace, else this is not our business...
        $prefix = 'App\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        //... and if it is we remove the prefix (which isn't actually a folder) 
        $cls = substr($class, strlen($prefix));
        if (!$cls) {
            return;
        }

        //The qualified class name is now has backslashes, but paths have
        //forward slashes, so we change that
        $cls = str_replace('\\', '/', $cls);

        //Now we get the dir and filename...
        $path = realpath(__DIR__ . '/../') . '/' . $cls;
        $dir = dirname($path) . '/';
        $basename = basename($path);
        //...then we try adding the suffixes to that
        foreach (self::$suffixes as $suffix) {
            $file = $dir . $basename . '.' . $suffix . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }

        //If we're still running we've most likely made a mistake since we have in fact
        //identifed our namespace ($prefix), so it is most likely our file, so we warn
        error_log("Could not find file for class '$class'. Look at your files and figure it out.\n"
            . "You'll want to name the file something like 'CoolStuff.class.php' and the class inside 'CoolStuff'\n");
    }
}

//Register the custom loader
spl_autoload_register('App\Bootstrap\CustomAutoloader::load');
