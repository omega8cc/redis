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
   * @var \Drupal\redis\ClientInterface
   */
  protected $clientFactory;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The serialization class to use.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

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

  /**
   * Creates a redis CacheBackendFactory.
   *
   * @param \Drupal\redis\ClientFactory $client_factory
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   * @param \Drupal\redis\Cache\SerializationInterface $serializer
   *   The serialization class to use.
   */
  public function __construct(ClientFactory $client_factory, CacheTagsChecksumInterface $checksum_provider, SerializationInterface $serializer) {
    $this->clientFactory = $client_factory;
    $this->checksumProvider = $checksum_provider;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $client = $this->clientFactory->getClient();

      if ($client === FALSE && Settings::get('redis.failover', FALSE)) {
        $cache_failover = Settings::get('redis.failover.cache_service', 'cache.backend.database');
        $this->bins[$bin] = \Drupal::service($cache_failover)->get($bin);
      }
      else {
        $class_name = $this->clientFactory->getClass(ClientFactory::REDIS_IMPL_CACHE);
        $this->bins[$bin] = new $class_name($bin, $client, $this->checksumProvider, $this->serializer);
      }

    }
    return $this->bins[$bin];
  }

}
