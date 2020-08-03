<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_dashboard_agent\src\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the uli endpoint.
 */
class ULIEndpointTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_dashboard_agent',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Checks the relative url for one-time login.
   */
  public function testExtensionsEndpoint(): void {
    $this->drupalGet('admin/reports/dashboard/uli');

    $this->assertResponse(200);
    $this->assertSession()->responseContains('{"uli":"\/user\/reset\/1\/');
  }

}
