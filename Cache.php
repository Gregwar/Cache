<?php

namespace Gregwar\Cache;

/**
 * A cache system based on files
 *
 * @author Gregwar <g.passault@gmail.com>
 */
class Cache
{
    /**
     * Cache directory
     */
    protected $cacheDirectory;

    /**
     * Prefix directories size
     *
     * For instance, if the file is helloworld.txt and the prefix size is
     * 5, the cache file will be: h/e/l/l/o/helloworld.txt
     *
     * This is useful to avoid reaching a too large number of files into the 
     * cache system directories
     */
    protected $prefixSize = 5;

    /**
     * Constructs the cache system
     */
    public function __construct($cacheDirectory = 'cache/')
    {
	$this->cacheDirectory = $cacheDirectory;
    }

    /**
     * Sets the cache directory
     *
     * @param $cacheDirectory the cache directory
     */
    public function setCacheDirectory($cacheDirectory)
    {
	$this->cacheDirectory = $cacheDirectory;

	return $this;
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
     * Change the prefix size
     *
     * @param $prefixSize the size of the prefix directories
     */
    public function setPrefixSize($prefixSize)
    {
	$this->prefixSize = $prefixsize;
    }

    /**
     * Creates a directory
     *
     * @param $directory, the target directory
     */
    protected function mkdir($directory)
    {
	mkdir($directory, 0755, true);
    }

    /**
     * Gets the cache file name
     *
     * @param $filename, the name of the cache file
     * @param $mkdir, a boolean to enable/disable the construction of the
     *        cache file directory
     */
    public function getCacheFile($filename, $mkdir = false)
    {
	$path = array();
	$path[] = $this->cacheDirectory;

	// Getting the length of the filename before the extension
	$parts = explode('.', $filename);
	$len = strlen($parts[0]);

	for ($i=0; $i<min($len, $this->prefixSize); $i++) {
	    $path[] = $filename[$i];

	}
	$path = implode('/', $path);

	if ($mkdir && !is_dir($path)) {
	    mkdir($path, 0755, true);
	}

	$path .= '/' . $filename;

	return implode('/', $path);
    }

    /**
     * Checks that the cache conditions are respected
     *
     * @param $cacheFile the cache file
     * @param $conditions an array of conditions to check
     */
    protected function checkConditions($cacheFile, array $conditions = array())
    {
	// Implicit condition: the cache file should exist
	if (!file_exists($cacheFile)) {
	    return false;
	}

	foreach ($conditions as $type => $value) {
	    switch ($type) {
	    case 'maxage':
	    case 'max-age':
		// Return false if the file is older than $value
		break;
	    case 'younger-than':
	    case 'youngerthan':
		// Return false if the file is older than the file $value
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
	$cacheFile = $this->getCacheFile($filename);

	return $this->checkConditions($cacheFile, $conditions);
    }

    /**
     * Write data in the cache
     */
    public function write($filename, $contents = '')
    {
	$cacheFile = $this->getCacheFile($filename, true);

	file_put_contents($cacheFile, $contents);
    }

    /**
     * Get data from the cache
     */
    public function get($filename, array $conditions = array())
    {
	if ($this->exists($filename, $conditions)) {
	    return file_get_contents($this->getCacheFile($filename));
	} else {
	    return null;
	}
    }
}
