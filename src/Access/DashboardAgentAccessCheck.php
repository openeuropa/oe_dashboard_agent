<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Access;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Access check for dashboard agent.
 */
class DashboardAgentAccessCheck implements AccessInterface {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * DashboardAgentAccessCheck constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * Checks the access based on the received token in the request header.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, Request $request): AccessResultInterface {
    if (!$request->headers->has('NETOKEN')) {
      return AccessResult::forbidden()->setReason('NETOKEN reqåuest header is missing')->setCacheMaxAge(0);
    }

    $dashboard_token = Settings::get('oe_dashboard_agent.token');
    if (!$dashboard_token) {
      return AccessResult::forbidden()->setReason('DASHBOARD_TOKEN environment variable is not set.')->setCacheMaxAge(0);
    }

    $netoken = $request->headers->get('NETOKEN');
    $salt = substr($netoken, 0, 4);
    $sub_hash = substr($netoken, 4);

    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    // We use the time service to get the current request time because it is
    // the most reliable one and it can also be decorated for testing purposes.
    $date->setTimestamp($this->time->getRequestTime());
    $date = $date->format('Ymd');

    $hash = $salt . $dashboard_token . $date;
    $hash = hash('SHA512', $hash);

    if ($hash === $sub_hash) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }

    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
