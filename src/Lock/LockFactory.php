<?php

namespace Drupal\redis\Lock;

use Drupal\Core\Site\Settings;
use Drupal\redis\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lock backend singleton handling.
 */
class LockFactory {

  /**
   * @var \Drupal\redis\ClientInterface
   */
  protected $clientFactory;

  /**
   * The container the failover services are taken from, if given.
   *
   * Services are built while the container is compiled, before \Drupal has
   * one; the bootstrap container gives none.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected $container;

  /**
   * Creates a redis LockFactory.
   */
  public function __construct(ClientFactory $client_factory, ?ContainerInterface $container = NULL) {
    $this->clientFactory = $client_factory;
    $this->container = $container;
  }

  /**
   * Get actual lock backend.
   *
   * @param bool $persistent
   *   (optional) Whether to return a persistent lock implementation or not.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   *   Return lock backend instance.
   */
  public function get($persistent = FALSE) {
    $class_name = $this->clientFactory->getClass($persistent ? ClientFactory::REDIS_IMPL_PERSISTENT_LOCK : ClientFactory::REDIS_IMPL_LOCK);

    if (Settings::get('redis.failover', FALSE)) {
      $client = $this->clientFactory->getClient();
      if ($client === FALSE) {
        if ($persistent === TRUE) {
          return ($this->container ?? \Drupal::getContainer())->get('lock.persistent.failover');
        }
        return ($this->container ?? \Drupal::getContainer())->get('lock.failover');
      }
    }

    return new $class_name($this->clientFactory);
  }
}
