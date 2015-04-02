<?php
namespace Aws;

/**
 * Loads JSON files and compiles them into PHP files so that they are loaded
 * from PHP's opcode cache.
 *
 * @internal Please use Aws\load_compiled_json() instead.
 */
class JsonCompiler
{
    const CACHE_ENV = 'AWS_PHP_CACHE_DIR';

    private $cacheDir;
    private $hasOpcacheCheck;
    private $useCache;
    private $stripPath;

    /**
     * @param bool $useCache Set to false to force the cache to be disabled.
     */
    public function __construct($useCache = true)
    {
        $this->stripPath = __DIR__ . DIRECTORY_SEPARATOR;
        $this->useCache = $useCache && extension_loaded('Zend OPcache');
        $this->hasOpcacheCheck = $this->useCache
            && function_exists('opcache_is_script_cached');
        $this->cacheDir = getenv(self::CACHE_ENV) ?: (__DIR__ . '/cache');

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0777, true)) {
                $message = 'Unable to create cache directory: %s. Please make '
                    . 'this directory writable or provide the path to a '
                    . 'writable directory using the AWS_PHP_CACHE_DIR '
                    . 'environment variable. Note that this cache dir may need '
                    . 'to be cleared when updating the SDK in order to see '
                    . 'updates.';
                throw new \RuntimeException(sprintf($message, $this->cacheDir));
            }
        }
    }

    /**
     * Gets the JSON cache directory.
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Deletes all cached php files in the cache directory.
     */
    public function purge()
    {
        foreach (glob($this->cacheDir . '/*.php') as $file) {
            unlink($file);
        }
    }

    /**
     * Loads a JSON file from cache or from the JSON file directly.
     *
     * @param string $path Path to the JSON file to load.
     *
     * @return mixed
     */
    public function load($path)
    {
        if (!$this->useCache) {
            return json_decode(file_get_contents($path), true);
        }

        $real = realpath($path);
        $cache = str_replace($this->stripPath, '', $real);
        $cache = str_replace(['\\', '/'], '_', $cache);
        $cache = $this->cacheDir . DIRECTORY_SEPARATOR . $cache . '.php';

        if (($this->hasOpcacheCheck && opcache_is_script_cached($cache))
            || file_exists($cache)
        ) {
            return require $cache;
        }

        if (!file_exists($real)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $data = json_decode(file_get_contents($real), true);
        file_put_contents($cache, "<?php return " . var_export($data, true) . ';');

        return $data;
    }
}
