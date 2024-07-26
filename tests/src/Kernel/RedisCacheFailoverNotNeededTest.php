<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests Redis cache service in failover mode when Redis is available.
 *
 * @group redis
 */
class RedisCacheFailoverNotNeededTest extends KernelTestBase {

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
    self::setUpSettings();
    $settings = Settings::getAll();
    $settings['redis.failover'] = TRUE;
    new Settings($settings);
    parent::register($container);
    self::setUpFailoverChecksumServices($container);
  }

  /**
   * Tests that the failover backend is NOT used when Redis is available.
   */
  public function testCacheBackend() {
    $cache = \Drupal::service('cache.backend.redis')->get('testing');
    // Verify that the correct cache backend is being instantiated by the
    // factory.
    // This could be PhpRedis or Predis so we just check that it's NOT the
    // database backend being instantiated.
    $this->assertNotInstanceOf('\Drupal\Core\Cache\DatabaseBackend', $cache);
  }

}
