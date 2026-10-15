<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\Cache\RedisBackend;
use Drupal\redis\Cache\RedisCacheTagsChecksum;
use Drupal\redis\Flood\RedisFloodBackend;
use Drupal\redis\Lock\RedisLock;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the failover services when Redis can be reached.
 *
 * @group redis
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisFailoverNotNeededTest extends KernelTestBase {

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
    self::setUpSettings();
    $settings = Settings::getAll();
    $settings['redis.failover'] = TRUE;
    new Settings($settings);
    parent::register($container);
    self::setUpFailoverServices($container);
  }

  /**
   * Tests that each service uses Redis.
   */
  public function testNoFailover(): void {
    $this->assertFalse($this->container->get('redis.factory')->useFailover());

    $cache = $this->container->get('cache.backend.redis')->get('testing');
    $this->assertInstanceOf(RedisBackend::class, $cache);
    $cache->set('no_failover', 'value');
    $this->assertSame('value', $cache->get('no_failover')->data);

    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $this->assertInstanceOf(RedisCacheTagsChecksum::class, $checksum->getActiveService());

    $this->assertInstanceOf(RedisLock::class, $this->container->get('lock'));
    $this->assertInstanceOf(RedisLock::class, $this->container->get('lock.persistent'));
    $this->assertInstanceOf(RedisFloodBackend::class, $this->container->get('flood'));
  }

}
