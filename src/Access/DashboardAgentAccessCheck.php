<?php

namespace Drupal\oe_dashboard_agent\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Access check for dashboard agent.
 */
class DashboardAgentAccessCheck implements AccessInterface {

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
  public function access(Route $route, RouteMatchInterface $route_match, Request $request) {
    if (!$request->headers->has('NETOKEN')) {
      return AccessResult::forbidden()->setReason('NETOKEN request header is missing')->setCacheMaxAge(0);
    }

    $dashboard_token = getenv('DASHBOARD_TOKEN');
    if (!$dashboard_token) {
      return AccessResult::forbidden()->setReason('DASHBOARD_TOKEN environment variable is not set.')->setCacheMaxAge(0);
    }

    $netoken = $request->headers->get('NETOKEN');
    $salt = substr($netoken, 0, 4);
    $sub_hash = substr($netoken, 4);

    $date = date('Ymd');
    $hash = $salt . $dashboard_token . $date;
    $hash = hash('SHA512', $hash);

    if ($hash === $sub_hash) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }

    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
