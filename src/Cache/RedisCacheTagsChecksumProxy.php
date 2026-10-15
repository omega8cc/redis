<?php

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsChecksumPreloadInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Drupal\redis\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

// BC for Drupal 11.1 and earlier: RedisCacheTagsChecksum declares the alias.
if (!interface_exists(CacheTagsChecksumPreloadInterface::class)) {
  class_exists(RedisCacheTagsChecksum::class);
}

/**
 * Proxy for the cache tags checksum service to handle failover.
 *
 * Uses the redis checksum when Redis is reachable, otherwise the
 * cache_tags.invalidator.checksum.failover service, see
 * example.failover.services.yml.
 */
class RedisCacheTagsChecksumProxy implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface, CacheTagsChecksumPreloadInterface {

  /**
   * The currently active cache tags checksum service.
   */
  protected CacheTagsChecksumInterface&CacheTagsInvalidatorInterface $activeService;

  /**
   * Creates the proxy and selects the active service.
   *
   * @param \Drupal\redis\ClientFactory $factory
   *   The client factory.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface|null $container
   *   The container the failover service is taken from. The proxy is built
   *   while the container is compiled, before \Drupal has one. In the
   *   bootstrap container there is none: the database checksum is built
   *   directly there.
   */
  public function __construct(ClientFactory $factory, ?ContainerInterface $container = NULL) {
    if ($factory->useFailover()) {
      if (!isset($container) && \Drupal::hasContainer()) {
        $container = \Drupal::getContainer();
      }
      $this->activeService = isset($container) ? $container->get('cache_tags.invalidator.checksum.failover') : new DatabaseCacheTagsChecksum(Database::getConnection());
    }
    else {
      $this->activeService = new RedisCacheTagsChecksum($factory);
    }
  }

  /**
   * Gets the currently active cache tags checksum service.
   *
   * @return \Drupal\Core\Cache\CacheTagsChecksumInterface&\Drupal\Core\Cache\CacheTagsInvalidatorInterface
   *   The active service.
   */
  public function getActiveService(): CacheTagsChecksumInterface&CacheTagsInvalidatorInterface {
    return $this->activeService;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentChecksum(array $tags) {
    return $this->activeService->getCurrentChecksum($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($checksum, array $tags) {
    return $this->activeService->isValid($checksum, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->activeService->reset();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $this->activeService->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function registerCacheTagsForPreload(array $cache_tags): void {
    // RedisBackend only preloads through this method when it exists, so the
    // proxy must forward it; Drupal 11.1 and earlier have no preloading.
    if (method_exists($this->activeService, 'registerCacheTagsForPreload')) {
      $this->activeService->registerCacheTagsForPreload($cache_tags);
    }
  }

}
