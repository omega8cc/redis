<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Flood\DatabaseBackend as FloodDatabaseBackend;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the failover services when Redis cannot be reached.
 *
 * @group redis
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisFailoverTest extends KernelTestBase {

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
  }

  /**
   * Tests that each service falls back to its database implementation.
   */
  public function testFailover(): void {
    $this->assertTrue($this->container->get('redis.factory')->useFailover());

    $cache = $this->container->get('cache.backend.redis')->get('testing');
    $this->assertInstanceOf(DatabaseBackend::class, $cache);
    $cache->set('failover', 'value', tags: ['failover_tag']);
    $this->assertSame('value', $cache->get('failover')->data);

    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $this->assertInstanceOf(DatabaseCacheTagsChecksum::class, $checksum->getActiveService());
    $checksum->invalidateTags(['failover_tag']);
    $this->assertFalse($cache->get('failover'));

    $lock = $this->container->get('lock');
    $this->assertInstanceOf(DatabaseLockBackend::class, $lock);
    $this->assertTrue($lock->acquire('failover_lock'));
    $lock->release('failover_lock');
    $this->assertInstanceOf(PersistentDatabaseLockBackend::class, $this->container->get('lock.persistent'));

    $flood = $this->container->get('flood');
    $this->assertInstanceOf(FloodDatabaseBackend::class, $flood);
    $flood->register('failover_event', 3600, 'id');
    $this->assertFalse($flood->isAllowed('failover_event', 1, 3600, 'id'));
  }

}
