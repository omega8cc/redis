<?php

declare(strict_types=1);

namespace Drupal\redis_test\Plugin\QueueWorker;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for testing exceptions.
 */
#[QueueWorker(
  id: 'redis_test_queue',
  title: new TranslatableMarkup('Redis test queue'),
  cron: ['time' => 1]
)]
class RedisTestQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected KeyValueFactoryInterface $keyValueFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('keyvalue'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->keyValueFactory->get('redis_test')->set('data', $data);
  }

}
