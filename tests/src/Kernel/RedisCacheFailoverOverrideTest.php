<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests overriding the Redis cache failover service.
 *
 * @group redis
 */
class RedisCacheFailoverOverrideTest extends KernelTestBase {

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
    $settings = Settings::getAll();
    $settings['redis.failover.cache_service'] = 'cache.backend.php';
    new Settings($settings);
    parent::register($container);
    self::setUpFailoverChecksumServices($container);
  }

  /**
   * Tests that the Redis cache failover service can be overridden.
   */
  public function testCacheBackend() {
    $cache = \Drupal::service('cache.backend.redis')->get('testing');
    // Verify that the correct cache backend is being instantiated by the
    // factory.
    $this->assertInstanceOf('\Drupal\Core\Cache\PhpBackend', $cache);
  }

}
