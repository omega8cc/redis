<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests Redis lock service failover.
 *
 * @group redis
 */
class RedisLockFailoverTest extends KernelTestBase {

  use RedisTestInterfaceTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['redis'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    self::setUpFailoverSettings();
    parent::register($container);

    $container->register('lock', LockBackendInterface::class)
      ->setFactory([new Reference('redis.lock.factory'), 'get']);
    $container->register('lock.failover', DatabaseLockBackend::class)
      ->setArguments([new Reference('database')])
      ->setLazy(TRUE);

    $container->register('lock.persistent', LockBackendInterface::class)
      ->setFactory([new Reference('redis.lock.factory'), 'get'])
      ->setArguments([TRUE]);
    $container->register('lock.persistent.failover', PersistentDatabaseLockBackend::class)
      ->setArguments([new Reference('database')])
      ->setLazy(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lock = $this->container->get('lock');
    $this->lockPersistent = $this->container->get('lock.persistent');
  }

  /**
   * Tests that the failover backend is used when Redis is not available.
   */
  public function testLockFailover() {
    // Verify that the correct lock backends are being instantiated by the
    // factory.
    $this->assertInstanceOf('\Drupal\Core\ProxyClass\Lock\DatabaseLockBackend', $this->lock);
    $this->assertInstanceOf('\Drupal\Core\ProxyClass\Lock\PersistentDatabaseLockBackend', $this->lockPersistent);
  }

}
