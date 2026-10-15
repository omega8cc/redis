<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\redis\Cache\RedisCacheTagsChecksum;
use Drupal\redis\Cache\RedisCacheTagsChecksumProxy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Runs the Redis cache backend tests with the failover services in place.
 *
 * With Redis reachable, example.failover.services.yml must change nothing:
 * the cache tags checksum proxy passes everything to the redis checksum.
 *
 * @group redis
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisCacheFailoverProxyTest extends RedisCacheTest {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $settings = Settings::getAll();
    $settings['redis.failover'] = TRUE;
    new Settings($settings);
    self::setUpFailoverServices($container);
  }

  /**
   * Tests that the proxy wraps the redis checksum.
   */
  public function testProxyActiveService(): void {
    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $this->assertInstanceOf(RedisCacheTagsChecksumProxy::class, $checksum);
    $this->assertInstanceOf(RedisCacheTagsChecksum::class, $checksum->getActiveService());
  }

}
