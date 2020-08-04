<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Route controller for dashboard ULI endpoint.
 */
class ULIController extends ControllerBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ULIController constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * Generate one-time login url for user 1.
   */
  public function generateUli() {
    $user = User::load(1);
    $this->moduleHandler()->load('user');

    // Generate the absolute url.
    $url = user_pass_reset_url($user);
    // Get the relative path and concatenate /login.
    $relative_url = substr($url, strpos($url, '/user')) . '/login';
    return new JsonResponse(['uli' => $relative_url]);
  }

}
