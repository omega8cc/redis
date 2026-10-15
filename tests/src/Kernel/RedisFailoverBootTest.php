<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Cache\MemoryBackendFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\Cache\CacheBackendFactory;
use Drupal\redis\Cache\RedisCacheTagsChecksumProxy;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the failover while the container is compiled and before it exists.
 *
 * @group redis
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
class RedisFailoverBootTest extends KernelTestBase {

  use RedisTestInterfaceTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'redis'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    self::setUpFailoverSettings();
    parent::register($container);
    self::setUpFailoverServices($container);
    // Core builds the bootstrap bin while it compiles the container, before
    // \Drupal has one: keep that bin on Redis.
    $definition = $container->getDefinition('cache.bootstrap');
    $tags = $definition->getTags();
    $tags['cache.bin'][0]['default_backend'] = 'cache.backend.redis';
    $definition->setTags($tags);
  }

  /**
   * Tests that a bin built during the compile fails over to the database.
   */
  public function testCompile() {
    if ($this->container->get('cache_factory') instanceof MemoryBackendFactory) {
      $this->markTestSkipped("This core's kernel tests keep every bin in memory.");
    }
    $this->assertInstanceOf('\Drupal\Core\Cache\DatabaseBackend', $this->container->get('cache.bootstrap'));
  }

  /**
   * Tests the failover where there is no container at all.
   *
   * The bootstrap container gives the factory and the proxy no container,
   * and \Drupal has none yet.
   */
  public function testNoContainer() {
    $factory = $this->container->get('redis.factory');
    $container = \Drupal::getContainer();
    \Drupal::unsetContainer();
    try {
      $checksum = new RedisCacheTagsChecksumProxy($factory);
      $this->assertInstanceOf('\Drupal\Core\Cache\DatabaseCacheTagsChecksum', $checksum->getActiveService());
      $cache = (new CacheBackendFactory($factory, $checksum, new PhpSerialize()))->get('container');
      $this->assertInstanceOf('\Drupal\Core\Cache\NullBackend', $cache);
      // An empty preload list, NULL on PHP 7.3, is accepted.
      $checksum->registerCacheTagsForPreload(NULL);
    }
    finally {
      \Drupal::setContainer($container);
    }
  }

}
