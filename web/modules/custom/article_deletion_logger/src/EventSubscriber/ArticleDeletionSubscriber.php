<?php

namespace Drupal\article_deletion_logger\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityEvents;
use Drupal\Core\Entity\Event\EntityDeleteEvent;

/**
 * Event subscriber to log article deletions.
 */
class ArticleDeletionSubscriber implements EventSubscriberInterface {

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the ArticleDeletionSubscriber object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, AccountProxyInterface $current_user) {
    $this->logger = $logger_factory->get('article_deletion');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Subscribe to the entity deletion event.
      'entity.delete' => 'onEntityDelete',
    ];
  }

  /**
   * Responds to the entity deletion event.
   *
   * @param \Drupal\Core\Entity\Event\EntityDeleteEvent $event
   *   The entity delete event.
   */
  public function onEntityDelete(EntityDeleteEvent $event) {
    $entity = $event->getEntity();

    // Check if the deleted entity is an article.
    if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'article') {
      $user_id = $this->currentUser->id();
      $user_name = $this->currentUser->getDisplayName();
      $article_title = $entity->getTitle();
      $article_id = $entity->id();

      // Log the deletion event with sufficient details.
      $this->logger->notice('Article "{title}" (ID: {nid}) was deleted by user {uid} ({name}).', [
        'title' => $article_title,
        'nid' => $article_id,
        'uid' => $user_id,
        'name' => $user_name,
      ]);
    }
  }

}
