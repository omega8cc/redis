<?php

namespace Drupal\redis\Cache;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Site\Settings;
use Drupal\redis\ClientFactory;

/**
 * A cache backend factory responsible for the construction of redis cache bins.
 */
class CacheBackendFactory implements CacheFactoryInterface {

  /**
   * List of cache bins.
   *
   * Renderer and possibly other places fetch backends directly from the
   * factory. Avoid that the backend objects have to fetch meta information like
   * the last delete all timestamp multiple times.
   *
   * @var array
   */
  protected $bins = [];

  public function __construct(protected ClientFactory $clientFactory, protected CacheTagsChecksumInterface $checksumProvider, protected SerializationInterface $serializer) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      if ($this->clientFactory->useFailover()) {
        // Redis is down: use the failover cache backend for this request.
        // There is no failover for the bootstrap container, which has no
        // container to look the service up in.
        $failover_service = Settings::get('redis.failover.cache_service', 'cache.backend.database');
        // @phpstan-ignore-next-line
        $this->bins[$bin] = \Drupal::service($failover_service)->get($bin);
      }
      else {
        $this->bins[$bin] = new RedisBackend($bin, $this->clientFactory->getClient(), $this->checksumProvider, $this->serializer);
      }
    }
    return $this->bins[$bin];
  }

}
