<?php

declare(strict_types=1);

namespace App\Bootstrap;

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
     * {@see App\Bootstrap\DynamicLoader::findFiles()}
     * @var array
     */
    protected ?array $files = null;

    /**
     * Taken from `$this->interface`. Keys are arg names, values are arg type and name like so:
     *      `["foo" => "int \$foo", "bar" => "string \$bar"]`
     * @var array
     */
    protected array $argNames = [];

    /**
     * The arguments which will be passed to each closure
     * @var array
     */
    protected ?array $args = null;

    /**
     * The root dir to start the search. Defaults to SRC_DIR.
     * @var string
     */
    protected ?string $searchDir = null;

    /**
     * Dynamically load all files in the search directory which match search criteria,
     * optionally cache the resulting list, then require them all and invoke them with
     * some args.
     * 
     * Example:
     * ```
     * (new DynamicLoader('RouteIncludes.php'))
     *      ->findFiles('.routes.php',RoutesFilesInterface::class)
     *      ->setArgs($app)
     *      ->createCacheFile()
     *      ->load();
     * ```
     * @param string $cacheFilename If you intend to cache the results, provide the filename here
     * @param string $dir If you want to search in a different directory than SRC_DIR, provide it here. 
     */
    public function __construct(string $cacheFilename = null, string $dir = null)
    {
        $this->searchDir = $dir ?: constant("SRC_DIR");
        if (!is_dir($this->searchDir) || !is_readable($this->searchDir)) {
            throw new \RuntimeException(sprintf("DynamicLoader: the search directory '%s' does "
                . "not exist or is not readable.", $this->searchDir));
        }

        if ($cacheFilename) {
            $cacheFilename = rtrim($cacheFilename, '.php') . '.php';
            if (strpos($cacheFilename, '/') === 0) {
                $this->cachefile = $cacheFilename;
            } else {
                $this->cachefile = constant("CACHE_DIR") . $cacheFilename;
            }
            $this->cached = file_exists($this->cachefile);
        }
    }




    /**
     * Searches for all files in the src/ directory (and subdirectories) which end with the given extension
     * and require them. If the required file returns an object which implements the given interface, it's
     * added to an array of files. If the file doesn't return an object implementing the interface
     * or throws an exception when required, a message is logged and the file is skipped.
     *
     * @param string $extension The extension to search for
     * @param string $interface The interface which the required object should implement
     * @return $this
     */
    public function findFiles(string $extension, string $interface): self
    {
        $this->interface = $interface; //save this for next function
        //If we've already cached, don't do it again
        if ($this->cached) {
            return $this;
        }
        $this->files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(constant("SRC_DIR"))) as $fileInfo) {
            if ($fileInfo instanceof \SplFileInfo && $fileInfo->isFile()) {
                $filepath = $fileInfo->getPathname();
                $relpath = substr($filepath, strlen(constant('SRC_DIR')));
                if ($relpath && substr_compare($relpath, $extension, -strlen($extension)) === 0) {
                    try {
                        $object = require $filepath;
                        if (!is_object($object) || !($object instanceof $interface)) {
                            throw new \Exception("File didn't return an object implementing '$interface'");
                        }
                        $this->files[$filepath] = $object;
                    } catch (\Throwable $e) {
                        error_log("Error dynamically loading $relpath: " . $e->getMessage());
                    }
                }
            }
        }
        return $this;
    }

    public function setArgs(...$ifaceInvokeArgs): self
    {
        if (!$this->interface) {
            throw new \Exception("Interface not set for DynamicLoader");
        }
        $__invoke = new \ReflectionMethod($this->interface, '__invoke');
        $params = $__invoke->getParameters();
        if (count($params) !== count($ifaceInvokeArgs)) {
            throw new \Exception("DynamicLoader->setArgs(): wrong number of args.");
        }
        foreach ($params as $i => $param) {
            //make sure the types match if they're specified for the interface
            $type = $param->getType();
            if ($type) {
                $expected =  $type->getName();
                $got = gettype($ifaceInvokeArgs[$i]);
                if ($got === 'object') {
                    $got = get_class($ifaceInvokeArgs[$i]);
                }
                if ($got !== $expected) {
                    throw new \Exception("DynamicLoader->setArgs(): arg $i is wrong type. "
                        . Bootstrap::wrongtype($expected, $ifaceInvokeArgs[$i]));
                }
            }
            $name = $param->getName();
            $this->argNames[$name] = "{$expected} \${$name}";
        }
        //we're still running that means the passed in args are good and we store them
        $this->args = $ifaceInvokeArgs;
        return $this;
    }




    public function createCacheFile(): self
    {
        if (!$this->cachefile) {
            throw new \Exception("DynamicLoader->createCacheFile(): cachefile wasn't set "
                . "in constructor, don't call this method");
        } else if ($this->cached) {
            //no need to cache again
            return $this;
        } else if (!$this->args) {
            throw new \Exception("DynamicLoader->createCacheFile(): Args not set. "
                . "Run ->setArgs() first");
        } else if (!$this->files) {
            //currently this can't happen if args are set, but perhaps in the future... 
            throw new \Exception("DynamicLoader->createCacheFile(): Files not set. Run ->findFiles() first");
        }

        $contents = "<?php\n\n";
        $contents .= "\t// Created by " . __CLASS__ . " on " . date('Y-m-d H:i:s') . "\n";
        $contents .= "\treturn function (";
        $contents .= implode(", ", array_values($this->argNames));
        $contents .= ") {\n";

        $argStr = implode(', ', array_map(fn($name) => "\${$name}", array_keys($this->argNames))); //we'll use this for each require statement

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
        return $this;
    }



    public function load(): void
    {
        if (!$this->args) {
            throw new \Exception("DynamicLoader->load(): Args not set. Run ->setArgs() first");
        }
        if ($this->files) {
            //We've already verified that args match the closures, so just rock and roll
            foreach ($this->files as $closure) {
                $closure(...$this->args);
            }
            //If we'd already cached then by definition files will be empty which is a good thing
        } else if ($this->cached) {
            try {
                $closure = require $this->cachefile;
                $closure(...$this->args);
            } catch (\Throwable $e) {
                throw new \Exception("DynamicLoader->load(): failed to run cached file $this->cachefile. "
                    . $e->getMessage());
            }
        } else {
            //currently this can't happen if args are set, but perhaps in the future... 
            throw new \Exception("DynamicLoader->load(): Files not set. Run ->findFiles() first");
        }
    }
};
