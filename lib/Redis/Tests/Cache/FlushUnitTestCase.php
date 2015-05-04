<?php

abstract class Redis_Tests_Cache_FlushUnitTestCase extends Redis_Tests_AbstractUnitTestCase
{
    /**
     * Get cache backend
     *
     * @return Redis_Cache
     */
    final protected function getBackend($name = 'cache')
    {
        return new Redis_Cache($name);
    }

    /**
     * Tests that with a default cache lifetime temporary non expired
     * items are kept even when in temporary flush mode.
     */
    public function doTestFlushIsTemporaryWithLifetime($flushMode = 0)
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = $flushMode;
        $conf['cache_lifetime'] = 1000;
        $backend = $this->getBackend();

        // Even though we set a flush mode into this bin, Drupal default
        // behavior when a cache_lifetime is set is to override the backend
        // one in order to keep the core behavior and avoid potential
        // nasty bugs.
        $this->assertFalse($backend->allowTemporaryFlush());

        $backend->set('test7', 42, CACHE_PERMANENT);
        $backend->set('test8', 'foo', CACHE_TEMPORARY);
        $backend->set('test9', 'bar', time() + 1000);

        $backend->clear();

        $cache = $backend->get('test7');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 42);
        $cache = $backend->get('test8');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'foo');
        $cache = $backend->get('test9');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'bar');
    }

    /**
     * Tests that with no default cache lifetime all temporary items are
     * droppped when in temporary flush mode.
     */
    public function doTestFlushIsTemporaryWithoutLifetime($flushMode = Redis_Cache::FLUSH_NORMAL)
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();

        $this->assertTrue($backend->allowTemporaryFlush());

        $backend->set('test10', 42, CACHE_PERMANENT);
        $backend->set('test11', 'foo', CACHE_TEMPORARY);
        $backend->set('test12', 'bar', time() + 10);

        $backend->clear();

        $cache = $backend->get('test10');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 42);
        $this->assertFalse($backend->get('test11'));
        $cache = $backend->get('test12');
        $this->assertNotEqual(false, $cache);
    }

    public function doTestNormalFlushing($flushMode = Redis_Cache::FLUSH_NORMAL)
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend('cache_foo');
        $backendUntouched = $this->getBackend('cache_bar');

        // Set a few entries.
        $backend->set('test13', 'foo');
        $backend->set('test14', 'bar', CACHE_TEMPORARY);
        $backend->set('test15', 'baz', time() + 3);

        $backendUntouched->set('test16', 'dog');
        $backendUntouched->set('test17', 'cat', CACHE_TEMPORARY);
        $backendUntouched->set('test18', 'xor', time() + 5);

        // This should not do anything (bugguy command)
        $backend->clear('', true);
        $backend->clear('', false);
        $this->assertNotIdentical(false, $backend->get('test13'));
        $this->assertNotIdentical(false, $backend->get('test14'));
        $this->assertNotIdentical(false, $backend->get('test15'));
        $this->assertNotIdentical(false, $backendUntouched->get('test16'));
        $this->assertNotIdentical(false, $backendUntouched->get('test17'));
        $this->assertNotIdentical(false, $backendUntouched->get('test18'));

        // This should clear every one, permanent and volatile
        $backend->clear('*', true);
        $this->assertFalse($backend->get('test13'));
        $this->assertFalse($backend->get('test14'));
        $this->assertFalse($backend->get('test15'));
        $this->assertNotIdentical(false, $backendUntouched->get('test16'));
        $this->assertNotIdentical(false, $backendUntouched->get('test17'));
        $this->assertNotIdentical(false, $backendUntouched->get('test18'));
    }

    public function testShardedLifeTime()
    {
        $this->doTestFlushIsTemporaryWithLifetime(Redis_Cache::FLUSH_SHARD);
    }

    public function testShardedWithoutLifeTime()
    {
        $this->doTestFlushIsTemporaryWithoutLifetime(Redis_Cache::FLUSH_SHARD);
    }

    public function testNormalLifeTime()
    {
        $this->doTestFlushIsTemporaryWithLifetime(Redis_Cache::FLUSH_NORMAL);
    }

    public function testNormalWithoutLifeTime()
    {
        $this->doTestFlushIsTemporaryWithoutLifetime(Redis_Cache::FLUSH_NORMAL);
    }

    public function testShardedNormalFlush()
    {
        $this->doTestNormalFlushing(Redis_Cache::FLUSH_SHARD);
    }

    public function testNormalFlush()
    {
        $this->doTestNormalFlushing(Redis_Cache::FLUSH_NORMAL);
    }
}