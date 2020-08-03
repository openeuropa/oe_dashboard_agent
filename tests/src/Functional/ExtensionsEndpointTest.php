<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_dashboard_agent\src\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the extensions endpoint.
 */
class ExtensionsEndpointTest extends BrowserTestBase {

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
   * Checks that the information is available on the extensions route.
   */
  public function testExtensionsEndpoint(): void {
    $this->drupalGet('admin/reports/dashboard/extensions');

    $this->assertResponse(200);
    $this->assertSession()->responseContains('{"extensions":');
  }

}
