<?php

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Site\Settings;
use Drupal\redis\ClientFactory;

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
  public function __construct(ClientFactory $factory) {
    if (Settings::get('redis.failover', FALSE)) {
      $client = $factory->getClient();
      // Fallback to failover service.
      if ($client === FALSE) {
        $this->activeService = \Drupal::service('cache_tags.invalidator.checksum.failover');
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

}
