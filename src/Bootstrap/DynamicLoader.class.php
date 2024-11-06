<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Common\Exceptions\CustomException;
use Traversable;

/**
 * This class provides a way to load all files in the search directory which 
 * match search criteria. What is does with those files depends on which static
 * method you use:
 *   - {@see App\Bootstrap\DynamicLoader::run()} 
 *   - {@see App\Bootstrap\DynamicLoader::load()}
 * 
 */
class DynamicLoader
{

    /**
     * If not null, the filename where the cache should be written or has been written.
     * If null, no caching will be done.
     * @var string|null
     */
    protected ?string $cachefile = null;


    /**
     * True if `$this->cachefile` exists, else false. 
     * @var bool
     */
    protected bool $cached = false;

    /**
     * The interface that the objects returned by the dynamically loaded files 
     * should implement
     * @var string|null
     */
    protected ?string $interface = null;

    /**
     * The files which were found in the search directory. They implement
     * `$this->interface` and they have the extension specified by 
     * {@see App\Bootstrap\DynamicLoader::findFiles()}.
     * 
     * Keys are file paths, values are closures
     */
    protected ?array $files = null;

    /**
     * Taken from `$this->interface`. Keys are arg names, values are arg type and name like so:
     *      `["foo" => "int \$foo", "bar" => "string \$bar"]`
     */
    protected array $argNames = [];

    /**
     * Taken from `$this->interface`. Keys are arg names, values are arg types
     */
    protected ?array $interfaceArgs = null;

    /**
     * The arguments which will be passed to each closure
     */
    protected ?array $args = null;

    /**
     * The root dir to start the search. Defaults to SRC_DIR.
     */
    protected ?string $searchDir = null;

    /**
     * The glob pattern to use when searching for files in the search directory
     */
    protected ?string $pattern = null;


    protected function __construct() {}

    /**
     * Scan for files which return a class with an __invoke method and then run them.
     * 
     * Example:
     * ```
     * DynamicLoader::run([
     *    'cachefile'=>'AutowireIncludes'
     *    ,'pattern'=>'*.autowire.php'
     *    ,'interface'=>ContainerBootstrap::class
     * ]);
     * ```
     * 
     * @param array $config             Assoc array with the following keys:
     * @param string $config.searchDir  The directory to search for files. Defaults to SRC_DIR.
     *                                  Can be relative to SRC_DIR.
     * @param string $config.pattern    A glob pattern which matches the files you want to load.  
     * @param string $config.interface  An interface which the required object should implement.
     *                                  You should get it by doing `MyInterface::class`. The interface
     *                                  should have a public __invoke method.
     * @param string $config.cachefile  An absolute or relative path to the cache file. Does not 
     *                                  need .php extension.
     * @param array $config.args        The arguments to pass to each closure
     * 
     * 
     * @return void
     */
    static public function run($config): void
    {
        extract($config, EXTR_SKIP);

        //To run we need an interface, so make sure one has been configured
        if (!isset($interface)) {
            throw new \InvalidArgumentException("DynamicLoader: missing 'interface'");
        }
        (new self())
            ->configure($config)
            ->scanForFiles()
            ->loadFromFilesOrCache()
            ->createCacheFile();
    }

    /**
     * Scan for files which match a pattern or implement an interface, then require those
     * files and return what they return.
     * 
     * Example:
     * ```
     * $files = DynamicLoader::scan(['pattern'=>'*.autowire.php']);
     * ```
     * 
     * @param array $config The same config array as {@see App\Bootstrap\DynamicLoader::run()}.
     *                      Keys 'cachefile' and 'args' are not used.
     * 
     * @return array Keys are file paths relative to $config['searchDir'] and values are the 
     *               values returned by those files.
     */
    static public function load($config): array
    {
        return (new self())
            ->configure($config)
            ->scanForFiles()
            ->files
        ;
    }

    /**
     * Parse all the args used by the loader
     * @param array $args See {@see App\Bootstrap\DynamicLoader::run()}
     * @return DynamicLoader
     */
    protected function configure(array $config): self
    {
        extract($config, EXTR_SKIP);

        if (isset($interface)) {
            $this->configureInterface($interface, $args);
        }

        //If we want to use cache...
        if (isset($cachefile)) {
            $this->configureCachefile($cachefile);
        }

        //If we havn't already cached then these are parsed as well
        if (!$this->cached) {
            $this->configureSearch(@$searchDir, @$pattern);
        }

        return $this;
    }

    protected function configureCachefile($cachefile): void
    {
        if (!is_string($cachefile)) {
            throw new \InvalidArgumentException("DynamicLoader: 'cachefile' must be a string");
        }
        $cachefile = rtrim($cachefile, '.php') . '.php';
        if (strpos($cachefile, '/') !== 0) {
            $cachefile = constant("CACHE_DIR") . $cachefile;
        }
        $this->cachefile = $cachefile;
        $this->cached = file_exists($this->cachefile);
    }


    protected function configureSearch($searchDir = null, $pattern = null): void
    {
        $this->searchDir = $searchDir ?? constant("SRC_DIR");

        if (!is_dir($this->searchDir) || !is_readable($this->searchDir)) {
            throw new \RuntimeException(sprintf("DynamicLoader: the search directory '%s' does "
                . "not exist or is not readable.", $this->searchDir));
        }

        if (isset($pattern)) {
            $this->pattern = $pattern;
        } else if (!$this->interface) {
            throw new \Exception("DynamicLoader: You need to specify either 'interface' or 'pattern' " .
                "to search for files");
        };
    }

    protected function configureInterface($interface, ?array $args): void
    {
        if (!is_string($interface)) {
            throw new \InvalidArgumentException("DynamicLoader: 'cachefile' must be a string");
        }
        if (!interface_exists($interface)) {
            throw new \InvalidArgumentException("DynamicLoader: invalid 'interface': "
                . (string) $interface);
        }
        $this->interface = $interface;
        $this->parseInterfaceArgs($interface);
        $this->matchInterfaceArgs($args);
    }


    /**
     * Inspects the __invoke method of an interface and extracts the type-hintings
     * of all parameters. The type-hintings are stored in the $interfaceArgs
     * property.
     *
     * @param class-string $interface A fully qualified interface name
     */
    protected function parseInterfaceArgs(string $interface)
    {
        $this->interfaceArgs = [];
        $__invoke = new \ReflectionMethod($interface, '__invoke');
        if (!$__invoke->isPublic()) {
            throw new \InvalidArgumentException("DynamicLoader: interface $interface does not have " .
                "a public __invoke method");
        }
        $params = $__invoke->getParameters();
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type == 'object') {
                $type = $type->getName();
            }
            $this->interfaceArgs[$param->getName()] = (string)$type ?: "mixed";
        }
    }



    /**
     * Verify that the types of the passed args match the types required by the interface
     * 
     * @param array $args The array of arguments to check
     * 
     * @throws \Exception If the types don't match
     */
    protected function matchInterfaceArgs(?array $args): void
    {
        if (! $this->interfaceArgs) {
            throw new \Exception("DynamicLoader: Interface not parsed yet. Run ->findFiles() or ->configure() first");
        }
        $args = $args ?? [];
        if (count($args) !== count($this->interfaceArgs)) {
            throw new \Exception("DynamicLoader: Not enough args passed for interface '{$this->interface}'");
        }
        $i = 0;
        foreach ($this->interfaceArgs as $expectedType) {
            //make sure the types match if they're specified for the interface
            $arg = $args[$i];
            if ($expectedType !== 'mixed') {
                $gotType = gettype($arg);
                if ($gotType === 'object') {
                    $gotType = get_class($arg);
                }
                if ($gotType !== $expectedType) {
                    throw new \Exception("DynamicLoader: arg $i is wrong type for interface '{$this->interface}'. "
                        . CustomException::wrongtype($expectedType, $arg));
                }
            }
            $i++;
        }
        //If we're still running all is good so we store the passed args on the object
        $this->args = $args;
    }



    /**
     * Searches for all files in the $dir directory (and subdirectories) which 
     * either/and match the $pattern/implements the $interface. These files will
     * be stored in protected property $this->files.
     * 
     * After this has run you can call ->load() which will actually load them.
     * 
     * If the files have already been cached then this will not scan anything, but you can
     * still run ->load() which will load the cache instead.
     * 
     * @return $this
     */
    protected function scanForFiles(): self
    {
        //If we've already cached, don't scan again, just return
        if ($this->cached) {
            return $this;
        }

        $this->files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->searchDir)) as $fileInfo) {
            if ($fileInfo instanceof \SplFileInfo && $fileInfo->isFile()) {
                $filepath = $fileInfo->getPathname();
                $relpath = substr($filepath, strlen($this->searchDir)) ?: '';
                //Either no pattern is specified (ie. we match only on interface), or the pattern matches
                if (!$this->pattern || fnmatch($this->pattern, $relpath)) {
                    try {
                        //Allow for loading things other than php files, like .json files for instance
                        if (substr_compare($filepath, '.php', -4) === 0) {
                            $something = require $filepath;
                            if ($this->interface) {
                                if (!is_object($something) || !($something instanceof $this->interface)) {
                                    throw new \Exception("File didn't return an object implementing '{$this->interface}'");
                                }
                            }
                        } else {
                            $something = file_get_contents($filepath);
                        }
                        $this->files[$filepath] = $something;
                    } catch (\Throwable $e) {
                        error_log("Error dynamically loading $relpath: " . $e->getMessage());
                    }
                }
            }
        }
        if (count($this->files) === 0) {
            $matched = $this->pattern ? "pattern '{$this->pattern}'" : '';
            $matched .= $this->interface ? (($matched ? ' and ' : ' ') . "interface '{$this->interface}'") : '';
            error_log("DynamicLoader->findFiles(): No files found in {$this->searchDir} matching {$matched}");
        }
        return $this;
    }



    /**
     * Load all the files which have been found, or the cache file if it exists.
     * 
     * This will also run createCacheFile() if you're using cache and this is the first run
     * of load().
     * 
     * @throws \Exception If the args don't match the required args, 
     * @throws \Exception If we're using cache but the {@see $this->cachefile} fails to run
     * @throws \Exception If we're not using cache and {@see $this->files} is empty.
     */
    protected function loadFromFilesOrCache(): self
    {
        //If we'd already cached then by definition files will be empty which is a good thing
        if ($this->cached) {
            try {
                $closure = require $this->cachefile;
                $closure(...$this->args);
            } catch (\Throwable $e) {
                throw new \Exception("DynamicLoader->load(): failed to run cached file $this->cachefile. "
                    . $e->getMessage());
            }
        } else {
            if (!$this->files) {
                throw new \Exception("DynamicLoader->load(): Files not set. Run ->findFiles() first");
            }
            //We're going to run each closure, and all that succeed we're going to create 
            //a cache file for
            $filesToCache = [];
            foreach ($this->files as $name => $closure) {
                try {
                    $closure(...$this->args);
                    $filesToCache[$name] = $closure;
                } catch (\Throwable $e) {
                    $err = new \Exception("DynamicLoader->load(): failed to load '$name'. " .
                        "It will not be included in the cache file. " . $e->getMessage());
                    error_log((string)$err);
                }
            }
            $this->files = $filesToCache;
        }
        return $this;
    }





    /**
     * Write a php file which, when required, returns a closure which, when run with args, 
     * will run each of the files in $filesToCache.
     * 
     * @param array $filesToCache Should match format of {@see $this->files}
     * 
     * @throws \Exception If cachefile wasn't set in constructor, or if interface args haven't been parsed yet.
     */
    protected function createCacheFile(): void
    {
        if (!$this->cachefile || $this->cached) {
            return;
        }

        $contents = "<?php\n\n";
        $contents .= "\t// Created by " . __CLASS__ . " on " . date('Y-m-d H:i:s') . "\n";
        $contents .= "\treturn function (";
        $args = array_map(
            fn($key, $value) => "$value \$$key",
            array_keys($this->interfaceArgs),
            $this->interfaceArgs
        );
        $contents .= implode(", ", array_values($args));
        $contents .= ") {\n";

        $argStr = implode(', ', array_map(fn($name) => "\${$name}", array_keys($this->interfaceArgs))); //we'll use this for each require statement

        //Loop through all the files, creating a require block for each
        foreach (array_keys($this->files) as $filepath) {
            $relpath = substr((string)$filepath, strlen(constant('SRC_DIR')));
            $contents .= "\n\ttry {";
            $contents .= "\n\t\t\$filepath = constant('SRC_DIR') . '$relpath';";
            $contents .= "\n\t\t(require \$filepath)($argStr);";
            $contents .= "\n\t} catch (\\Throwable \$e) {";
            $contents .= "\n\t\terror_log(\"Error running '$relpath' : {\$e->getMessage()}\");\n\t}";
        }
        $contents .= "\t};\n";

        file_put_contents($this->cachefile, $contents);
    }
};
