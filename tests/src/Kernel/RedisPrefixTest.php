<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\RedisPrefixTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cache prefix: the site's name and the copy's database.
 *
 * The expected values are the formula the hosting side uses to find a
 * site's namespaces: md5 of the name without www., then '_sn_' (or '_hh_'),
 * then the first 8 hex of md5('<host lowercased and trimmed>/<database>/
 * <table prefix>') and '_'.
 *
 * @group redis
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
class RedisPrefixTest extends KernelTestBase {

  use RedisPrefixTrait;

  /**
   * The server values replaced by a test.
   *
   * @var array
   */
  protected $savedServer = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['SERVER_NAME', 'HTTP_HOST'] as $key) {
      $this->savedServer[$key] = isset($_SERVER[$key]) ? $_SERVER[$key] : NULL;
      unset($_SERVER[$key]);
    }
    Database::renameConnection('default', 'redis_prefix_test_saved');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    Database::removeConnection('default');
    Database::renameConnection('redis_prefix_test_saved', 'default');
    foreach ($this->savedServer as $key => $value) {
      if (isset($value)) {
        $_SERVER[$key] = $value;
      }
    }
    parent::tearDown();
  }

  /**
   * Gives the default connection the values a test names.
   */
  protected function setDefaultConnection($host, $database, $prefix = '') {
    Database::removeConnection('default');
    Database::addConnectionInfo('default', 'default', [
      'driver' => 'mysql',
      'host' => $host,
      'port' => '3306',
      'database' => $database,
      'prefix' => $prefix,
    ]);
    $this->prefix = NULL;
  }

  /**
   * Tests the server name, its www. stripped, and the copy segment.
   */
  public function testServerName() {
    $_SERVER['SERVER_NAME'] = 'www.example.com';
    $this->setDefaultConnection(' LocalHost ', 'db1');
    $this->assertSame('5ababd603b22780302dd8d83498e5172_sn_2434d3e0_', $this->getPrefix());
  }

  /**
   * Tests that the table prefix tells copies apart and the port does not.
   */
  public function testTablePrefix() {
    $_SERVER['SERVER_NAME'] = 'example.com';
    $this->setDefaultConnection('localhost', 'db1', 'pre_');
    $this->assertSame('5ababd603b22780302dd8d83498e5172_sn_3d2155e5_', $this->getPrefix());
  }

  /**
   * Tests the host header when there is no server name.
   */
  public function testHttpHost() {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $this->setDefaultConnection('localhost', 'db1');
    $this->assertSame('5ababd603b22780302dd8d83498e5172_hh_2434d3e0_', $this->getPrefix());
  }

  /**
   * Tests the name alone while no database is known.
   */
  public function testNoDatabase() {
    $_SERVER['SERVER_NAME'] = 'example.com';
    $this->setDefaultConnection('localhost', '');
    $this->assertSame('5ababd603b22780302dd8d83498e5172_sn_', $this->getPrefix());
  }

}
