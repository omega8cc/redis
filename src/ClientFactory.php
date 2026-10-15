<?php

namespace Drupal\redis;

use Drupal\Core\Site\Settings;
use Drupal\redis\Client\PhpRedisFactory;
use Drupal\redis\Client\PredisFactory;
use Drupal\redis\Client\RedisClientFactoryInterface;
use Drupal\redis\Client\RelayFactory;

/**
 * Common code and client singleton, for all Redis clients.
 */
class ClientFactory {
  /**
   * Redis default host.
   */
  const REDIS_DEFAULT_HOST = "127.0.0.1";

  /**
   * Redis default port.
   */
  const REDIS_DEFAULT_PORT = 6379;

  /**
   * Redis default database: will select none (Database 0).
   */
  const REDIS_DEFAULT_BASE = NULL;

  /**
   * Redis default password: will not authenticate.
   */
  const REDIS_DEFAULT_PASSWORD = NULL;

  /**
   * The client adapter.
   *
   * @var \Drupal\redis\ClientInterface|null
   */
  protected static ?ClientInterface $client = NULL;

  /**
   * Whether connecting to Redis failed in this request.
   *
   * Only recorded with $settings['redis.failover'] enabled, so that every
   * service falls back without attempting the connection again.
   *
   * @var bool
   */
  protected static bool $connectionFailed = FALSE;

  /**
   * @var \Drupal\redis\Client\RedisClientFactoryInterface[]
   */
  protected array $factories = [];

  /**
   * Whether the factory has an active client.
   *
   * @return bool
   *   True if there is an instantiated client, false if not.
   */
  public function hasClient(): bool {
    return static::$client instanceof ClientInterface;
  }

  /**
   * Get underlying library name.
   *
   * @return string|null
   */
  public function getClientName(): ?string {
    return static::$client?->getName();
  }

  /**
   * Add a client factory.
   *
   * @param \Drupal\redis\Client\RedisClientFactoryInterface $client_factory
   *   The client factory.
   */
  public function addFactory(RedisClientFactoryInterface $client_factory): void {
    $this->factories[$client_factory->getName()] = $client_factory;
  }

  /**
   * Get client singleton.
   *
   * @throws \Exception
   *   When no connection to Redis can be made. With failover enabled, call
   *   useFailover() first.
   */
  public function getClient(): ClientInterface {
    if (!isset(static::$client)) {
      $settings = Settings::get('redis.connection', []);
      $settings += [
        'host' => self::REDIS_DEFAULT_HOST,
        'port' => self::REDIS_DEFAULT_PORT,
        'base' => self::REDIS_DEFAULT_BASE,
        'password' => self::REDIS_DEFAULT_PASSWORD,
        'persistent' => FALSE,
      ];

      // If using replication, lets create the client appropriately.
      if (isset($settings['replication']) && $settings['replication'] === TRUE) {
        foreach ($settings['replication.host'] as $key => $replicationHost) {
          if (!isset($replicationHost['port'])) {
            $settings['replication.host'][$key]['port'] = self::REDIS_DEFAULT_PORT;
          }
        }
      }

      // For early bootstrap container, the client factories aren't initialized
      // yet.
      if (empty($this->factories)) {
        foreach ([PhpRedisFactory::class, PredisFactory::class, RelayFactory::class] as $client_factory_class) {
          $client_factory = new $client_factory_class();
          $this->factories[$client_factory->getName()] = $client_factory;
        }
      }

      // If a specific client interface was requested, only use that.
      if (isset($settings['interface'])) {
        if (!isset($this->factories[$settings['interface']])) {
          throw new \InvalidArgumentException('Invalid interface ' . $settings['interface']);
        }
        static::$client = $this->createClient($this->factories[$settings['interface']], $settings);
      }
      else {
        // Fall back to returning the first-available client based on priority.
        foreach ($this->factories as $client_factory) {
          if ($client_factory->isAvailable()) {
            static::$client = $this->createClient($client_factory, $settings);
            break;
          }
        }
      }
    }

    return static::$client;
  }

  /**
   * Whether Redis is down and the failover services must be used instead.
   *
   * Always FALSE unless $settings['redis.failover'] is TRUE. The first call
   * connects; a failed connection is remembered for the rest of the request.
   * Configuration errors, such as an unknown interface, are still thrown.
   *
   * @return bool
   *   TRUE if failover is enabled and no connection to Redis could be made.
   */
  public function useFailover(): bool {
    if (!Settings::get('redis.failover', FALSE)) {
      return FALSE;
    }
    if (!static::$connectionFailed && !isset(static::$client)) {
      try {
        $this->getClient();
      }
      catch (\Exception $e) {
        if (!static::$connectionFailed) {
          throw $e;
        }
      }
    }
    return static::$connectionFailed;
  }

  /**
   * Creates the client, recording a failed connection when failover is on.
   *
   * @param \Drupal\redis\Client\RedisClientFactoryInterface $client_factory
   *   The client factory.
   * @param array $settings
   *   The connection settings.
   *
   * @return \Drupal\redis\ClientInterface
   *   The connected client.
   *
   * @throws \Exception
   *   When the client library can not connect.
   */
  protected function createClient(RedisClientFactoryInterface $client_factory, #[\SensitiveParameter] array $settings): ClientInterface {
    if (static::$connectionFailed) {
      // Do not wait for another connection attempt in this request.
      throw new \RuntimeException('The connection to Redis failed earlier in this request.');
    }
    $failover = Settings::get('redis.failover', FALSE);
    if ($failover) {
      // A failed connection is answered by the failover services, and the
      // exception names the reason: the warning PhpRedis also raises (for a
      // host name that does not resolve) would only log once per request.
      set_error_handler(static function () {
        return TRUE;
      }, E_WARNING);
    }
    try {
      return $client_factory->getClient($settings);
    }
    catch (\Exception $e) {
      if ($failover) {
        static::$connectionFailed = TRUE;
      }
      throw $e;
    }
    finally {
      if ($failover) {
        restore_error_handler();
      }
    }
  }

}

