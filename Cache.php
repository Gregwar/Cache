<?php
/**
 * Gregwar Cache package
 * @author Gregwar <g.passault@gmail.com>
 */
namespace Gregwar\Cache;

/**
 * A cache system based on files
 */
class Cache {
    /**
     * Cache directory
     */
    protected $cacheDirectory;

    /**
     * Use a different directory as actual cache
     */
    protected $actualCacheDirectory = null;

    /**
     * Number of prefix directories
     */
    protected $prefixSize = 5;

    /**
     * Permission mode value of cache directories
     *
     * Default is 0777 for match with default builtin mkdir().
     * User can use chmod or umask.
     */
    protected $dirmode = 0777;

    /**
     * Construct the cache system
     *
     * @param string $cacheDirectory cache location.
     */
    public function __construct($cacheDirectory = 'cache') {
        $this -> cacheDirectory = $cacheDirectory;
    }

    /**
     * Set the cache directory
     *
     * @param string $cacheDirectory the cache directory
     * @return Cache instance of Cache object
     */
    public function setCacheDirectory($cacheDirectory) {
        $this -> cacheDirectory = $cacheDirectory;
        return $this;
    }

    /**
     * Get the cache directory
     *
     * @return string the cache directory url
     */
    public function getCacheDirectory() {
        return $this -> cacheDirectory;
    }

    /**
     * Set the actual cache directory
     * 
     * @param string $actualCacheDirectory the actual cache directory.
     * @return Cache instance of Cache object.
     */
    public function setActualCacheDirectory($actualCacheDirectory = null) {
        $this -> actualCacheDirectory = $actualCacheDirectory;
        return $this;
    }

    /**
     * Get the actual cache directory or default cache if null.
     * 
     * @return string the actual cache directory
     */
    public function getActualCacheDirectory() {
        return $this -> actualCacheDirectory ? : $this -> cacheDirectory;
    }

    /**
     * Set number of prefix directories.
     * 
     * Prevent reaching a too large number of files into the
     * cache system directories
     *
     * **example** : for file *helloworld.txt* and the `$prefixSize`
     * equal to 5, the cache file will be: `h/e/l/l/o/helloworld.txt`
     *
     * @param int $prefixSize number of prefix directories
     * @return Cache instance of Cache object
     */
    public function setPrefixSize($prefixSize) {
        $this -> prefixSize = $prefixSize;
        return $this;
    }

    /**
     * Change default permission mode value of cache directories (default is 0777 for match with default builtin `mkdir()`).
     *
     * You can also use `umask()`. If umask is equal to 0002, `mkdir("dir",0777)` make a directory
     * with mode equal to 0775. ([see umask php doc](http://php.net/manual/function.umask.php))
     *
     * **Note**: Files already present in cache will be not modified.
     * Use `Cache::chmod()` for modify current cache.
     *
     * @param int $mode octal number represent directory permissions with unix integer mode format
     *  ([see chmod man page](http://www.freebsd.org/cgi/man.cgi?query=chmod))
     */
    public function setDefaultDirMode($mode) {
        $this -> dirmode = $mode;
    }

    /**
     * Creates a directory
     *
     * @param string $directory the target directory.
     */
    protected function mkdir($directory) {
        if (!is_dir($directory)) {
            @mkdir($directory, $this -> dirmode, true);
        }
    }

    /**
     * Gets the cache file url
     *
     * @param string $filename the name of the cache file
     * @param bool $actual get the actual file or the public file
     * @param bool $mkdir enable/disable the construction of the
     *  cache file directory
     */
    public function getCacheFile($filename, $actual = false, $mkdir = false) {
        $path = array();

        // Getting the length of the filename before the extension
        $parts = explode('.', $filename);
        $len = strlen($parts[0]);

        for ($i = 0; $i < min($len, $this -> prefixSize); $i++) {
            $path[] = $filename[$i];

        }
        $path = implode('/', $path);

        $actualDir = $this -> getActualCacheDirectory() . '/' . $path;
        if ($mkdir && !is_dir($actualDir)) {
            mkdir($actualDir, $this -> dirmode, true);
        }

        $path .= '/' . $filename;

        if ($actual) {
            return $this -> getActualCacheDirectory() . '/' . $path;
        } else {
            return $this -> getCacheDirectory() . '/' . $path;
        }
    }

    /**
     * Checks that the cache conditions are respected
     *
     * @param string $cacheFile the cache file
     * @param array $conditions an array of conditions to check
     * @return bool `TRUE` if all conditions are respected, else `FALSE`
     */
    protected function checkConditions($cacheFile, array $conditions = array()) {
        // Implicit condition: the cache file should exist
        if (!file_exists($cacheFile)) {
            return false;
        }

        foreach ($conditions as $type => $value) {
            switch ($type) {
                case 'maxage' :
                case 'max-age' :
                    // Return false if the file is older than $value
                    $age = time() - filectime($cacheFile);
                    if ($age > $value) {
                        return false;
                    }
                    break;
                case 'younger-than' :
                case 'youngerthan' :
                    // Return false if the file is older than the file $value, or the files $value
                    $check = function($filename) use ($cacheFile) {
                        return !file_exists($filename) || filectime($cacheFile) < filectime($filename);
                    };

                    if (!is_array($value)) {
                        if (!$this -> isRemote($value) && $check($value)) {
                            return false;
                        }
                    } else {
                        foreach ($value as $file) {
                            if (!$this -> isRemote($file) && $check($file)) {
                                return false;
                            }
                        }
                    }
                    break;
                default :
                    throw new \Exception('Cache condition ' . $type . ' not supported');
            }
        }

        return true;
    }

    /**
     * Checks if the target filename exists in the cache and if the conditions
     * are respected
     *
     * @param string $filename the filename
     * @param array $conditions the conditions to respect
     * @return bool filename exists in the cache and if the conditions are respected
     */
    public function exists($filename, array $conditions = array()) {
        $cacheFile = $this -> getCacheFile($filename, true);

        return $this -> checkConditions($cacheFile, $conditions);
    }

    /**
     * Alias for exists
     * 
     * @param string $filename the filename
     * @param array $conditions the conditions to respect
     * @return bool filename exists in the cache and if the conditions are respected
     **/
    public function check($filename, array $conditions = array()) {
        return $this -> exists($filename, $conditions);
    }

    /**
     * Write data in the cache
     * 
     * @param string $filename name of file to save in cache
     * @param string $contents content to put in file
     * @return Cache instance of Cache object
     */
    public function set($filename, $contents = '') {
        $cacheFile = $this -> getCacheFile($filename, true, true);

        file_put_contents($cacheFile, $contents);
        chmod($cacheFile, $this -> filemode);
        return $this;
    }

    /**
     * Alias for set()
     * 
     * @param string $filename name of file to save in cache
     * @param string $contents content to put in file
     * @return Cache instance of Cache object
     */
    public function write($filename, $contents = '') {
        return $this -> set($filename, $contents);
    }

    /**
     * Get data from the cache
     * 
     * @param string $filename the filename
     * @param array $conditions the conditions to respect
     * @return content of cache file if exists, else `null`
     */
    public function get($filename, array $conditions = array()) {
        if ($this -> exists($filename, $conditions)) {
            return file_get_contents($this -> getCacheFile($filename, true));
        } else {
            return null;
        }
    }

    /**
     * Change recursively all cache directories and files permissions.
     *
     * **Note** : Prefer `Cache::setDefaultDirMode()` before cache creation.
     *
     * This method will scan the entire cache, therefore may  take time for
     * a large cache.
     *
     * @param int $dirmode octal number represent directory permissions with unix integer mode format.
     *  ([see chmod man page](http://www.freebsd.org/cgi/man.cgi?query=chmod))
     * @param int $filemode octal number represent files permissions with unix integer mode format
     *  ([see chmod man page](http://www.freebsd.org/cgi/man.cgi?query=chmod))
     */
    public function chmod($dirmode, $filemode) {
        $cacheDirectory = $this -> getActualCacheDirectory();
        if (is_dir($cacheDirectory)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDirectory), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                if (is_dir($item)) {
                    chmod($item, $dirmode);
                } else {
                    chmod($item, $filemode);
                }
            }
        }
    }

    /**
     * Is this URL remote?
     * 
     * @param string $file file url
     * @return bool `TRUE` if file is on remote address, else `FALSE` 
     */
    protected function isRemote($file) {
        return preg_match('/^http(s{0,1}):\/\//', $file);
    }

    /**
     * Get or create the cache entry
     *
     * @param string $filename the cache file name
     * @param array $conditions an array of conditions about expiration
     * @param \Closure $function the closure to call if the file does not exists
     * @param bool $file returns the cache file or the file contents
     * @param bool $actual returns the actual cache file
     * @return string content or url of cached file
     */
    public function getOrCreate($filename, array $conditions = array(), \Closure $function, $file = false, $actual = false) {
        $cacheFile = $this -> getCacheFile($filename, true, true);
        $data = null;

        if ($this -> check($filename, $conditions)) {
            $data = file_get_contents($cacheFile);
        } else {
            @unlink($cacheFile);
            $data = $function($cacheFile);

            // Test if the closure wrote the file or if it returned the data
            if (!file_exists($cacheFile)) {
                $this -> set($filename, $data);
            } else {
                $data = file_get_contents($cacheFile);
            }
        }

        return $file ? $this -> getCacheFile($filename, $actual) : $data;
    }

    /**
     * Alias to getOrCreate with $file = true
     * 
     * @param string $filename the cache file name
     * @param array $conditions an array of conditions about expiration
     * @param \Closure $function the closure to call if the file does not exists
     * @param bool $actual returns the actual cache file
     * @return string content or url of cached file
     */
    public function getOrCreateFile($filename, array $conditions = array(), \Closure $function, $actual = false) {
        return $this -> getOrCreate($filename, $conditions, $function, true, $actual);
    }

}
