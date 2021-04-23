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
use Symfony\Component\HttpFoundation\IpUtils;
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
    $allowed_ips = Settings::get('oe_dashboard_agent.allowed_ips');
    if (!$allowed_ips) {
      return AccessResult::forbidden()->setReason('The allowed IPs are not configured.')->setCacheMaxAge(0);
    }

    if (!IpUtils::checkIp($request->getClientIp(), $allowed_ips)) {
      return AccessResult::forbidden()->setReason('The request origin is not allowed.')->setCacheMaxAge(0);
    }

    if (!$request->headers->has('NETOKEN')) {
      return AccessResult::forbidden()->setReason('The NETOKEN request header is missing.')->setCacheMaxAge(0);
    }

    $dashboard_token = Settings::get('oe_dashboard_agent.token');
    if (!$dashboard_token) {
      return AccessResult::forbidden()->setReason('Missing dashboard token. See installation instructions.')->setCacheMaxAge(0);
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

    return AccessResult::forbidden()->setReason('The NETOKEN request header is incorrect.')->setCacheMaxAge(0);
  }

}
