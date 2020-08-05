<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Route controller for dashboard ULI endpoint.
 */
class UliController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * UliController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->logger = $logger_factory->get('dashboard_agent');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('logger.factory')
    );
  }

  /**
   * Generate one-time login url for user 1.
   */
  public function generateUli() {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load(1);

    $timestamp = $this->time->getCurrentTime();
    $url = Url::fromRoute('user.reset.login',
      [
        'uid' => $user->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($user, $timestamp),
      ],
      [
        'absolute' => TRUE,
      ]
    )->toString();

    $this->logger->info('A ULI link has been requested.');
    return new JsonResponse(['uli' => $url]);
  }

}
