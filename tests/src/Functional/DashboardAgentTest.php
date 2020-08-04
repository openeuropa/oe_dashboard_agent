<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_dashboard_agent\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the dashboard agent route access checker.
 */
class DashboardAgentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'datetime_testing',
    'oe_dashboard_agent_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access to the test route with the access checker.
   */
  public function testRouteAccess(): void {
    // Freeze the time on August 8 2020 when the hash was generated.
    $date = DrupalDateTime::createFromFormat('Y-m-d H', '2020-08-03 12');
    $time = \Drupal::service('datetime.time');
    $time->freezeTime();
    $time->setTime($date->getTimestamp());

    $url = Url::fromRoute('oe_dashboard_agent_test.protected');

    // Test that access is denied with no token in header.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Test that access is denied with incorrect token in header.
    $this->drupalGet($url, [], ['NETOKEN' => '70870cce44daa1745b2af95ed6b374ed41cd2e809176c7c0fe8c06e337fd29f2cc2cf413b55540be168c1776fff631e259bbb87f7840897c73f0551086584cf1d']);
    $this->assertSession()->statusCodeEquals(403);

    // Test that access is granted with correct token in header.
    $this->drupalGet($url, [], ['NETOKEN' => 'imx70870cce44daa1745b2af95ed6b374ed41cd2e809176c7c0fe8c06e337fd29f2cc2cf413b55540be168c1776fff631e259bbb87f7840897c73f0551086584cf1d']);
    $this->assertSession()->statusCodeEquals(200);
  }

}
