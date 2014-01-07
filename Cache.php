<?php

namespace Gregwar\Cache;

/**
 * A cache system based on files
 *
 * @todo validate cache_id with regexp
 * 
 * @author Sébastien Monterisi <SebSept@github>
 * @author Gregwar <g.passault@gmail.com>
 */
class Cache
{
    /**
     * Cache directory
     */
    protected $cacheDirectory;

    /**
     * directories size max depth
     *
     * For instance, if the file is helloworld.txt and the depth size is
     * 5, the cache file will be: h/e/l/l/o/helloworld.txt
     *
     * This is useful to avoid reaching a too large number of files into the 
     * cache system directories
     * @var int $pathDepth
     */
    protected $pathDepth = 5;

    /**
     * default configuration options
     * @var array 
     */
    protected $options = ['cacheDirectory' => 'cache', 
                          'conditions' => ['max-age' => 86400]
            ];
    
    /**
     * cache conditions
     * 
     * keys can be only 'max-age'
     * will be overrided in __construct with $options['conditions']
     * @var array associative array
     */
    protected $conditions = [];
    
    /**
     * Constructs the cache system
     * 
     * Options param can be 'cacheDirectory' and 'conditions' @see Gregwar\Cache\Cache::$conditions
     * @param array $options 
     */
    public function __construct($options = array())
    {
        // merge default options with passed
        $this->options = array_merge($this->options, $options);

	$this->cacheDirectory = $this->options['cacheDirectory'];
        $this->conditions = $this->options['conditions'];
    }

    /**
     * Sets the cache directory
     * 
     * Set the cache directory if exists
     *
     * @todo also check that dir is writable 
     * 
     * @param string $cacheDirectory the cache directory. Without ending '/'
     * @return bool
     */
    public function setCacheDirectory($cacheDirectory)
    {
        if(file_exists($cacheDirectory))
        {
            $this->cacheDirectory = $cacheDirectory;
            return true;
        }
	return false;
    }

    /**
     * Gets the cache directory
     *
     * @return string the cache directory
     */
    public function getCacheDirectory()
    {
	return $this->cacheDirectory;
    }

    /**
     * Set directories Path max depth
     *
     * @todo add min and max constants to validate size value
     * @param int $size path max depth
     * @return $this
     */
    public function setPathDepth($size)
    {
        if(filter_var($size, FILTER_VALIDATE_INT) && $size > 0)
                $this->pathDepth = $size;

        return $this;
    }

    /**
     * Creates a directory
     *
     * @param $directory, the target directory
     */
    protected function mkdir($directory)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
    }

    /**
     * Gets the cache file path
     *
     * @todo refactor/recode
     * @param string $cache_id cache file name
     */
    public function getCachePath($cache_id)
    {
	$path = array();

	// Getting the length of the filename before the extension
	$parts = explode('.', $cache_id);
	$len = strlen($parts[0]);

	for ($i=0; $i<min($len, $this->pathDepth); $i++) {
	    $path[] = $cache_id[$i];

        }

	$path = implode('/', $path);

	$path .= '/' . $cache_id;
        return $this->getCacheDirectory() . '/' . $path;
    }
    
    protected function createDir()
    {
        trigger_error('implement me');
        die('implement');
        // moved from getCacheFile / getCachePath
                $actualDir = $this->getActualCacheDirectory() . '/' . $path;
        if ($mkdir && !is_dir($actualDir)) {
	    mkdir($actualDir, 0755, true);
	}
    }

    /**
     * Checks that the cache conditions are respected
     *
     * @param string $cacheFile the cache file to check
     * @param array $conditions an array of conditions to check, overrides current conditions
     * @return bool
     */
    protected function checkConditions($cacheFile, array $conditions = array())
    {
        // Implicit condition: the cache file should exist
        if (!file_exists($cacheFile)) {
	    return false;
	}

        // merge passed $conditions with currents
        $conditions = array_merge($this->conditions, $conditions);
        
	foreach ($conditions as $type => $value) {
	    switch ($type) {
            case 'max-age':
		// Return false if the file is older than $value
                $age = time() - filectime($cacheFile);
                if ($age >= $value) {
                    return false;
                }
		break;
	    case 'younger-than':
            case 'youngerthan':
                // Return false if the file is older than the file $value, or the files $value
                $check = function($filename) use ($cacheFile) {
                    return !file_exists($filename) || filectime($cacheFile) < filectime($filename);
                };

                if (!is_array($value)) {
                    if (!$this->isRemote($value) && $check($value)) {
                        return false;
                    }
                } else {
                    foreach ($value as $file) {
                        if (!$this->isRemote($file) && $check($file)) {
                            return false;
                        }
                    }
                }
		break;
	    default:
		throw new \Exception('Cache condition '.$type.' not supported');
	    }
	}

	return true;
    }

    /**
     * Checks if the targt filename exists in the cache and if the conditions
     * are respected
     *
     * @param $filename the filename 
     * @param $conditions the conditions to respect
     */
    public function exists($filename, array $conditions = array())
    {
        $cacheFile = $this->getCachePath($filename, true);

	return $this->checkConditions($cacheFile, $conditions);
    }

    /**
     * Alias for exists
     */
    public function check($filename, array $conditions = array())
    {
        return $this->exists($filename, $conditions);
    }

    /**
     * Caches contents
     * 
     * @todo throw Exception or display error if in debug mode (?)
     * 
     * @param string $cache_id 
     * @param string $contents contents to cache
     */
    public function set($cache_id, $contents)
    {
	$cachePath = $this->getCachePath($cache_id);
        try {
            $this->createDir($cachePath);
            file_put_contents($cachePath, $contents);
            return $this;
        }
        catch(Exception $e)
        {
            return false;
        }
    }

    /**
     * Get data from the cache
     */
    public function get($filename, array $conditions = array())
    {
	if ($this->exists($filename, $conditions)) {
	    return file_get_contents($this->getCachePath($filename, true));
	} else {
	    return null;
	}
    }

    /**
     * Is this URL remote?
     */
    protected function isRemote($file)
    {
        return preg_match('/^http(s{0,1}):\/\//', $file);
    }

    /**
     * Get or create the cache entry
     *
     * @param $filename the cache file name
     * @param $conditions an array of conditions about expiration
     * @param $function the closure to call if the file does not exists
     * @param $file returns the cache file or the file contents
     * @param $actual returns the actual cache file
     */
    public function getOrCreate($filename, array $conditions = array(), \Closure $function, $file = false, $actual = false)
    {
        $cacheFile = $this->getCachePath($filename, true, true);
        $data = null;

        if ($this->check($filename, $conditions)) {
            $data = file_get_contents($cacheFile);
        } else {
            @unlink($cacheFile);
            $data = $function($cacheFile);

            // Test if the closure wrote the file or if it returned the data
            if (!file_exists($cacheFile)) {
                $this->set($filename, $data);
            } else {
                $data = file_get_contents($cacheFile);
            }
        }

        return $file ? $this->getCachePath($filename, $actual) : $data;
    }

    /**
     * Alias to getOrCreate with $file = true
     */
    public function getOrCreateFile($filename, array $conditions = array(), \Closure $function, $actual = false)
    {
        return $this->getOrCreate($filename, $conditions, $function, true, $actual);
    }
}
