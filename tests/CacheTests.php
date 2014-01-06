<?php

use Gregwar\Cache\Cache;

/**
 * Unit testing for Cache
 */
class CacheTests extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Cache
     */
    protected $cache;
    
    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     * 
     * - Creates cache object
     * - Checks 'testing.txt' is not existing
     */
    protected function setUp() {
        $this->cache = new Cache;
        $this->cache->setPrefixSize(5)
                    ->setCacheDirectory($this->getCacheDirectory())
                    ->setActualCacheDirectory($this->getActualCacheDirectory());
        
        $this->assertFalse($this->cache->exists('testing.txt'));
    }
    
    /**
     * Testing that file names are good
     */
    public function testFileName()
    {
        $cache = $this->cache;

        $cacheDir = $this->getCacheDirectory();
        $actualCacheDir = $this->getActualCacheDirectory();
        $cacheFile = $cache->getCacheFile('helloworld.txt');
        $actualCacheFile = $cache->getCacheFile('helloworld.txt', true);
        $this->assertEquals($cacheDir . '/h/e/l/l/o/helloworld.txt', $cacheFile);
        $this->assertEquals($actualCacheDir . '/h/e/l/l/o/helloworld.txt', $actualCacheFile);

        $cacheFile = $cache->getCacheFile('xy.txt');
        $actualCacheFile = $cache->getCacheFile('xy.txt', true);
        $this->assertEquals($cacheDir . '/x/y/xy.txt', $cacheFile);
        $this->assertEquals($actualCacheDir . '/x/y/xy.txt', $actualCacheFile);
    }
    
    /**
     * @covers Gregwar\Cache\Cache::exists
     */
    public function testExits_onNoCondition()
    {
        $this->cache->set('testing.txt', 'content');
        $this->assertTrue($this->cache->exists('testing.txt'));
    }
    
    /**
     * @covers Gregwar\Cache\Cache::exists
     * Data should be cached
     */
    public function testExits_onMaxAgeValid()
    {
        $conditions = array('max-age' => 60); // 60 seconds
        
        $this->cache->set('testing.txt', 'content');
        $this->assertTrue($this->cache->exists('testing.txt', $conditions));
    }
    
    /**
     * @covers Gregwar\Cache\Cache::exists
     * Cache expired
     */
    public function testExits_onMaxAgeExpired()
    {
        $conditions = array('max-age' => 1); // 1 second
        
        $this->cache->set('testing.txt', 'content');
        sleep(2);
        $this->assertFalse($this->cache->exists('testing.txt', $conditions));
    }
    
    /**
     * @covers Gregwar\Cache\Cache::exists
     * Cache expired - -1 second
     */
    public function testExits_onMaxAgeAlwaysExpired()
    {
        $conditions = array('max-age' => -1);
        
        $this->cache->set('testing.txt', 'content');
        $this->assertFalse($this->cache->exists('testing.txt', $conditions));
    }
    
    /**
     * @covers Gregwar\Cache\Cache::exists
     * Cache expired - 0 second
     * 0 second proprably means 'no cache'
     */
    public function testExits_onMaxAgeZero()
    {
        $conditions = array('max-age' => 0);
        
        $this->cache->set('testing.txt', 'content');
        $this->assertFalse($this->cache->exists('testing.txt', $conditions));
    }
    
    /**
     * Check if configuration passed on constuctor is respected
     * @covers Gregwar\Cache\Cache::__construct()
     */
    public function testConstuctor_onConfigPassed()
    {
        // initial_conditions : no cache
        $initial_conditions = array('conditions' => array( 'max-age' => 0) );
        $cache = new Cache($initial_conditions);
        $cache->set('testing.txt', 'content');
        $this->assertFalse($cache->exists('testing.txt'));
    }
    
    /**
     * Testing the getOrCreate function
     */
    public function testGetOrCreate()
    {
        $cache = $this->cache;

        $this->assertFalse($cache->exists('testing.txt'));

        $data = $cache->getOrCreate('testing.txt', array(), function() {
            return 'zebra';
        });

        $this->assertTrue($cache->exists('testing.txt'));
        $this->assertEquals('zebra', $data);

        $data = $cache->getOrCreate('testing.txt', array(), function() {
            return 'elephant';
        });
        $this->assertEquals('zebra', $data);
    }

    /**
     * Testing the getOrCreate function with $file=true
     */
    public function testGetOrCreateFile()
    {
        $dir = __DIR__;
        $cache = $this->cache;

        $file = $dir.'/'.$cache->getOrCreateFile('file.txt', array(), function() {
            return 'xyz';
        });
        $file2 = $dir.'/'.$cache->getOrCreate('file.txt', array(), function(){}, true);

        $this->assertEquals($file, $file2);
        $this->assertTrue(file_exists($file));
        $this->assertEquals('xyz', file_get_contents($file));
    }

    /**
     * Testing that the not existing younger file works
     */
    public function testNotExistingYounger()
    {
        $cache = $this->cache;

        $data = $cache->getOrCreate('testing.txt', array('younger-than'=> 'i-dont-exist'), function() {
            return 'some-data';
        });

        $this->assertEquals('some-data', $data);
    }


    protected function getActualCacheDirectory()
    {
        return __DIR__.'/'.$this->getCacheDirectory();
    }

    protected function getCacheDirectory()
    {
        return 'cache';
    }

    public function tearDown()
    {
        $cacheDirectory = $this->getActualCacheDirectory();
        `rm -rf $cacheDirectory`;
    }
}
