<?php

namespace Drupal\redis\Lock;

use Drupal\redis\ClientFactory;

/**
 * Relay lock backend implementation.
 */
class Relay extends PhpRedis {

  /**
   * @var \Relay\Relay
   */
  protected $client;

  /**
   * Creates a Relay cache backend.
   */
  public function __construct(ClientFactory $factory) {
    parent::__construct($factory);

    // don't cache locks in runtime memory
    $this->client->addIgnorePattern($this->getKey('*'));
  }

}
