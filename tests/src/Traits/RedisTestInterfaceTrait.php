<?php

namespace Drupal\Tests\redis\Traits;

use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\Reference;

trait RedisTestInterfaceTrait {

  /**
 * Uses an env variable to set the redis client to use for this test.
 */
  public function setUpSettings() {

    // Write redis_interface settings manually.
    $redis_interface = self::getRedisInterfaceEnv();
    $settings = Settings::getAll();
    $settings['redis.connection']['interface'] = $redis_interface;

    if ($host = getenv('REDIS_HOST')) {
      $settings['redis.connection']['host'] = $host;
    }

    new Settings($settings);
  }

  /**
   * Sets up intentionally invalid settings for testing failover functionality.
   */
  public function setUpFailoverSettings() {
    // Write redis_interface settings manually.
    $redis_interface = self::getRedisInterfaceEnv();
    $settings = Settings::getAll();
    $settings['redis.connection']['interface'] = $redis_interface;

    $settings['redis.connection']['host'] = 'intentionally-invalid';
    $settings['redis.failover'] = TRUE;

    new Settings($settings);
  }

  /**
   * Replaces the checksum service with the redis failover implementation.
   */
  public function setUpFailoverChecksumServices($container) {
    if ($container->has('redis.factory')) {
      $container->register('cache_tags.invalidator.checksum', 'Drupal\redis\Cache\RedisCacheTagsChecksumProxy')
        ->addArgument(new Reference('redis.factory'))
        ->addTag('cache_tags_invalidator');
      $container->register('cache_tags.invalidator.checksum.redis', 'Drupal\redis\Cache\RedisCacheTagsChecksum')
        ->addArgument(new Reference('redis.factory'));
      $container->register('cache_tags.invalidator.checksum.failover', 'Drupal\Core\Cache\DatabaseCacheTagsChecksum')
        ->addArgument(new Reference('database'));
    }
  }

  /**
   * Uses an env variable to set the redis client to use for this test.
   */
  public function getRedisInterfaceEnv() {

    // Get REDIS_INTERFACE from env variable.
    $redis_interface = getenv('REDIS_INTERFACE');

    // Default to PhpRedis is env variable not available.
    if ($redis_interface == FALSE) {
      $redis_interface = 'PhpRedis';
    }
    return $redis_interface;
  }

}
