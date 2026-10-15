<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cache tag preloading through the failover checksum proxy.
 *
 * @group redis
 * @runTestsInSeparateProcesses
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisFailoverPreloadTest extends KernelTestBase {

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
    self::setUpSettings();
    $settings = Settings::getAll();
    $settings['redis.failover'] = TRUE;
    new Settings($settings);
    parent::register($container);
    self::setUpFailoverServices($container);
  }

  /**
   * Tests that the proxy passes cache tag preloading on.
   */
  public function testPreload() {
    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $active = $checksum->getActiveService();
    $this->assertInstanceOf('\Drupal\redis\Cache\RedisCacheTagsChecksum', $active);
    if (!property_exists($active, 'preloadTags')) {
      $this->markTestSkipped('This core has no cache tag preloading.');
    }
    $checksum->registerCacheTagsForPreload(['preload_tag']);
    $preload = new \ReflectionProperty($active, 'preloadTags');
    $this->assertContains('preload_tag', $preload->getValue($active));
  }

}
