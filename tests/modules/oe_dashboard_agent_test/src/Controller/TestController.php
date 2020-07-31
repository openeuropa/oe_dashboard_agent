<?php

namespace Drupal\oe_dashboard_agent_test\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test controller for test routes.
 */
class TestController {

  /**
   * Test method for the test routes.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function testMethod() {
    return new Response('Some test content.');
  }

}
