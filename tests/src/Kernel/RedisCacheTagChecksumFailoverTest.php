<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests Redis cache tag checksum service failover.
 *
 * @group redis
 */
class RedisCacheTagChecksumFailoverTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cacheTagChecksum = $this->container->get('cache_tags.invalidator.checksum');
  }

  /**
   * Tests that the failover backend is used when Redis is not available.
   */
  public function testCacheTagChecksumFailover() {
    // Verify that the correct cache tag checksum backend is being instantiated
    // by the proxy class.
    $this->assertInstanceOf('\Drupal\Core\Cache\DatabaseCacheTagsChecksum', $this->cacheTagChecksum->getActiveService());
  }

}
