<?php

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\redis\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Proxy for cache tag invalidation/checksum backends to handle failover.
 */
class RedisCacheTagsChecksumProxy implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface {

  /**
   * The currently active cache tag invalidation service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $activeService;

  /**
   * Creates a cache tag invalidation class.
   */
  public function __construct(ClientFactory $factory, ?ContainerInterface $container = NULL) {
    if (Settings::get('redis.failover', FALSE)) {
      $client = $factory->getClient();
      // Fallback to failover service.
      if ($client === FALSE) {
        if (!isset($container) && \Drupal::hasContainer()) {
          $container = \Drupal::getContainer();
        }
        // In the bootstrap container there is no container to take the
        // failover service from: the database checksum is built directly.
        $this->activeService = isset($container) ? $container->get('cache_tags.invalidator.checksum.failover') : new DatabaseCacheTagsChecksum(Database::getConnection());
        return;
      }
    }

    $this->activeService = new RedisCacheTagsChecksum($factory);
  }

  /**
   * Gets the currently active cache tag invalidation service.
   *
   * @return \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\Drupal\Core\Cache\CacheTagsChecksumInterface
   *   The cache tag invalidation service.
   */
  public function getActiveService() {
    return $this->activeService;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentChecksum(array $tags) {
    return $this->getActiveService()->getCurrentChecksum($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($checksum, array $tags) {
    return $this->getActiveService()->isValid($checksum, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    return $this->getActiveService()->reset();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    return $this->getActiveService()->invalidateTags($tags);
  }

  /**
   * Registers cache tags to load with the next checksum lookup.
   *
   * The cache backends preload tags only when their checksum provider has
   * this method, so the proxy forwards it; Drupal 11.1 and earlier have none.
   *
   * @param array|null $cache_tags
   *   The cache tags. An empty list arrives as NULL on PHP 7.3, where the
   *   backends' array_merge() with no arguments returns NULL.
   */
  public function registerCacheTagsForPreload($cache_tags): void {
    if (!is_array($cache_tags) || !$cache_tags) {
      return;
    }
    if (method_exists($this->getActiveService(), 'registerCacheTagsForPreload')) {
      $this->getActiveService()->registerCacheTagsForPreload($cache_tags);
    }
  }

}
