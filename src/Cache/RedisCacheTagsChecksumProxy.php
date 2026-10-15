<?php

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
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
        $this->activeService = ($container ?? \Drupal::getContainer())->get('cache_tags.invalidator.checksum.failover');
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
   * @param array $cache_tags
   *   The cache tags.
   */
  public function registerCacheTagsForPreload(array $cache_tags): void {
    if (method_exists($this->getActiveService(), 'registerCacheTagsForPreload')) {
      $this->getActiveService()->registerCacheTagsForPreload($cache_tags);
    }
  }

}
