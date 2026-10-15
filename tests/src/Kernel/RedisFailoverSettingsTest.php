<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\Cache\PhpBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the failover settings when Redis cannot be reached.
 *
 * @group redis
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisFailoverSettingsTest extends KernelTestBase {

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
    self::setUpFailoverSettings(str_ends_with($this->name(), 'Override'));
    $settings = Settings::getAll();
    if (str_ends_with($this->name(), 'Override')) {
      $settings['redis.failover.cache_service'] = 'cache.backend.php';
    }
    elseif (str_ends_with($this->name(), 'WithoutInstance')) {
      $settings['redis.connection']['host'] = ['127.0.0.1:1'];
      $settings['redis.failover'] = TRUE;
    }
    else {
      // Refused at once, with no name to resolve: without failover the
      // connection error is all this test expects.
      $settings['redis.connection']['host'] = '127.0.0.1';
      $settings['redis.connection']['port'] = 1;
    }
    new Settings($settings);
    parent::register($container);
  }

  /**
   * Tests that another cache service can be the failover.
   */
  public function testCacheServiceOverride(): void {
    $this->assertInstanceOf(PhpBackend::class, $this->container->get('cache.backend.redis')->get('testing'));
  }

  /**
   * Tests that sentinel hosts without an instance name are not a failover.
   */
  public function testSentinelWithoutInstance(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->container->get('redis.factory')->useFailover();
  }

  /**
   * Tests that without failover a server that cannot be reached is an error.
   */
  public function testFailoverDisabled(): void {
    $this->assertFalse($this->container->get('redis.factory')->useFailover());
    $this->expectException(\Exception::class);
    // Predis connects on its first command.
    $this->container->get('cache.backend.redis')->get('testing')->get('cid');
  }

}
