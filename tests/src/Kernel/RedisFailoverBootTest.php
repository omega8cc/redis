<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\Cache\CacheBackendFactory;
use Drupal\redis\Cache\RedisCacheTagsChecksumProxy;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the failover while the container is compiled and before it exists.
 *
 * @group redis
 */
#[Group('redis')]
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
  public function register(ContainerBuilder $container): void {
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
  public function testCompile(): void {
    $this->assertInstanceOf(DatabaseBackend::class, $this->container->get('cache.bootstrap'));
  }

  /**
   * Tests the failover where there is no container at all.
   *
   * The bootstrap container gives the factory and the proxy no container,
   * and \Drupal has none yet.
   */
  public function testNoContainer(): void {
    $factory = $this->container->get('redis.factory');
    $container = \Drupal::getContainer();
    \Drupal::unsetContainer();
    try {
      $checksum = new RedisCacheTagsChecksumProxy($factory);
      $this->assertInstanceOf(DatabaseCacheTagsChecksum::class, $checksum->getActiveService());
      $cache = (new CacheBackendFactory($factory, $checksum, new PhpSerialize()))->get('container');
      $this->assertInstanceOf(NullBackend::class, $cache);
    }
    finally {
      \Drupal::setContainer($container);
    }
  }

}
