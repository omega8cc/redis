<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the services of example.failover.services.yml when Redis is down.
 *
 * @group redis
 * @runTestsInSeparateProcesses
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisFailoverServicesTest extends KernelTestBase {

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
  }

  /**
   * Tests that the failover lock, flood and checksum services work.
   */
  public function testFailover() {
    $lock = $this->container->get('lock');
    $this->assertTrue($lock->acquire('failover_lock'));
    $lock->release('failover_lock');

    $flood = $this->container->get('flood');
    $this->assertInstanceOf('\Drupal\Core\Flood\DatabaseBackend', $flood);
    $flood->register('failover_event', 3600, 'id');
    $this->assertFalse($flood->isAllowed('failover_event', 1, 3600, 'id'));

    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $this->assertInstanceOf('\Drupal\Core\Cache\DatabaseCacheTagsChecksum', $checksum->getActiveService());
  }

}
