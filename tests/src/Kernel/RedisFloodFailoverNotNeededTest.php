<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Flood\DatabaseBackend;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests Redis flood service in failover mode when Redis is available.
 *
 * @group redis
 */
class RedisFloodFailoverNotNeededTest extends KernelTestBase {

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
    self::setUpSettings();
    $settings = Settings::getAll();
    $settings['redis.failover'] = TRUE;
    new Settings($settings);
    parent::register($container);

    $container->register('flood', FloodInterface::class)
      ->setFactory([new Reference('redis.flood.factory'), 'get']);
    $container->register('flood.failover', DatabaseBackend::class)
      ->setArguments([new Reference('database'), new Reference('request_stack')]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->flood = $this->container->get('flood');
  }

  /**
   * Tests that the failover backend is NOT used when Redis is available.
   */
  public function testFloodFailover() {
    // Verify that the correct flood backend is being instantiated by the
    // factory.
    // This could be PhpRedis or Predis so we just check that it's NOT the
    // database backend being instantiated.
    $this->assertNotInstanceOf('\Drupal\Core\Flood\DatabaseBackend', $this->flood);
  }

}
