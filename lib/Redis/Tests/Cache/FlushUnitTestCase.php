<?php

abstract class Redis_Tests_Cache_FlushUnitTestCase extends Redis_Tests_AbstractUnitTestCase
{
    /**
     * @var Cache bin identifier
     */
    static private $id = 1;

    /**
     * Get cache backend
     *
     * @return Redis_Cache
     */
    final protected function getBackend($name = null)
    {
        if (null === $name) {
            // This is needed to avoid conflict between tests, each test
            // seems to use the same Redis namespace and conflicts are
            // possible.
            $name = 'cache' . (self::$id++);
        }
        return new Redis_Cache($name);
    }

    /**
     * Tests that with a default cache lifetime temporary non expired
     * items are kept even when in temporary flush mode.
     */
    public function doTestFlushIsTemporaryWithLifetime($flushMode = 0)
    {
        global $conf;

        $conf['redis_flush_mode'] = $flushMode;
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

        $conf['redis_flush_mode'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();

        $this->assertTrue($backend->allowTemporaryFlush());

        $backend->set('test10', 42, CACHE_PERMANENT);
        // Ugly concatenation with the mode, but it will be visible in tests
        // reports if the entry shows up, thus allowing us to know which real
        // test case is run at this time
        $backend->set('test11', 'foo' . $flushMode, CACHE_TEMPORARY);
        $backend->set('test12', 'bar' . $flushMode, time() + 10);

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

        $conf['redis_flush_mode'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();
        $backendUntouched = $this->getBackend();

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

    public function doTestPrefixDeletionWithSeparatorChar($flushMode = Redis_Cache::FLUSH_NORMAL)
    {
        global $conf;

        $conf['redis_flush_mode'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();

        $backend->set('testprefix10', 'foo');
        $backend->set('testprefix11', 'foo');
        $backend->set('testprefix:12', 'bar');
        $backend->set('testprefix:13', 'baz');
        $backend->set('testnoprefix14', 'giraffe');
        $backend->set('testnoprefix:15', 'elephant');

        $backend->clear('testprefix:', true);
        $this->assertFalse($backend->get('testprefix:12'));
        $this->assertFalse($backend->get('testprefix:13'));
        // @todo Temporary fix
        // At the moment shard enabled backends will erase all data instead
        // of just removing by prefix, so those tests won't pass
        if ($flushMode !== Redis_Cache::FLUSH_SHARD) {
            $this->assertNotIdentical(false, $backend->get('testprefix10'));
            $this->assertNotIdentical(false, $backend->get('testprefix11'));
            $this->assertNotIdentical(false, $backend->get('testnoprefix14'));
            $this->assertNotIdentical(false, $backend->get('testnoprefix:15'));
        }

        $backend->clear('testprefix', true);
        $this->assertFalse($backend->get('testprefix10'));
        $this->assertFalse($backend->get('testprefix11'));
        // @todo Temporary fix
        // At the moment shard enabled backends will erase all data instead
        // of just removing by prefix, so those tests won't pass
        if ($flushMode !== Redis_Cache::FLUSH_SHARD) {
            $this->assertNotIdentical(false, $backend->get('testnoprefix14'));
            $this->assertNotIdentical(false, $backend->get('testnoprefix:15'));
        }
    }

    public function doTestOrder($flushMode = Redis_Cache::FLUSH_NORMAL)
    {
        global $conf;

        $conf['redis_flush_mode'] = $flushMode;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();

        for ($i = 0; $i < 10; ++$i) {
            $id = 'speedtest' . $i;
            $backend->set($id, 'somevalue');
            $this->assertNotIdentical(false, $backend->get($id));
            $backend->clear('*', true);
            // Value created the same second before is dropped
            $this->assertFalse($backend->get($id));
            $backend->set($id, 'somevalue');
            // Value created the same second after is kept
            $this->assertNotIdentical(false, $backend->get($id));
        }
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

    public function testShardedPrefixDeletionWithSeparatorChar()
    {
        $this->doTestPrefixDeletionWithSeparatorChar(Redis_Cache::FLUSH_SHARD);
    }

    public function testPrefixDeletionWithSeparatorChar()
    {
        $this->doTestPrefixDeletionWithSeparatorChar(Redis_Cache::FLUSH_NORMAL);
    }

    public function testShardedOrder()
    {
        $this->doTestOrder(Redis_Cache::FLUSH_SHARD);
    }

    public function testOrder()
    {
        $this->doTestOrder(Redis_Cache::FLUSH_NORMAL);
    }
}