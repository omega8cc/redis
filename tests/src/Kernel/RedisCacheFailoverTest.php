<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests Redis cache service failover.
 *
 * @group redis
 */
class RedisCacheFailoverTest extends KernelTestBase {

  use RedisTestInterfaceTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['redis'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    self::setUpFailoverSettings();
    parent::register($container);
    self::setUpFailoverChecksumServices($container);
  }

  /**
   * Tests that the failover backend is used when Redis is not available.
   */
  public function testCacheBackend() {
    $cache = \Drupal::service('cache.backend.redis')->get('testing');
    // Verify that the correct cache backend is being instantiated by the
    // factory.
    $this->assertInstanceOf('\Drupal\Core\Cache\DatabaseBackend', $cache);
  }

}
