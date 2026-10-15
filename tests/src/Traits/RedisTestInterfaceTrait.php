<?php

namespace Drupal\Tests\redis\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\Core\Site\Settings;

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
   *
   * @param bool $failover
   *   Whether failover is enabled.
   */
  public function setUpFailoverSettings(bool $failover = TRUE) {
    // Write redis_interface settings manually.
    $redis_interface = self::getRedisInterfaceEnv();
    $settings = Settings::getAll();
    $settings['redis.connection']['interface'] = $redis_interface;

    $settings['redis.connection']['host'] = 'intentionally-invalid';
    $settings['redis.failover'] = $failover;

    new Settings($settings);
  }

  /**
   * Loads the module's example.failover.services.yml into the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container being built.
   */
  public function setUpFailoverServices(ContainerBuilder $container) {
    (new YamlFileLoader($container))->load(dirname(__DIR__, 3) . '/example.failover.services.yml');
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
