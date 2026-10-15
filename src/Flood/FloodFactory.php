<?php

namespace Drupal\redis\Flood;

use Drupal\redis\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Flood backend singleton handling.
 */
class FloodFactory {

  public function __construct(protected ClientFactory $clientFactory, protected RequestStack $requestStack, protected ?ContainerInterface $container = NULL) {
  }

  /**
   * Get actual flood backend.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   *   Return flood instance.
   */
  public function get() {
    if ($this->clientFactory->useFailover()) {
      // @phpstan-ignore-next-line
      return ($this->container ?? \Drupal::getContainer())->get('flood.failover');
    }
    return new RedisFloodBackend($this->clientFactory, $this->requestStack);
  }
}
