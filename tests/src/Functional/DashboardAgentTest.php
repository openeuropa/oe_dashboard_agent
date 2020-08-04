<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_dashboard_agent\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Site\Settings;
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
   * A header hash generated on August 8 2020 with the default token.
   *
   * @var string
   */
  protected $correctHash = 'imx70870cce44daa1745b2af95ed6b374ed41cd2e809176c7c0fe8c06e337fd29f2cc2cf413b55540be168c1776fff631e259bbb87f7840897c73f0551086584cf1d';

  /**
   * Tests access to the test route with the access checker.
   */
  public function testRouteAccessWithExistingHash(): void {
    // Freeze the time on August 8 2020 when the hash was generated.
    $date = DrupalDateTime::createFromFormat('Y-m-d H', '2020-08-03 12');
    $time = \Drupal::service('datetime.time');
    $time->freezeTime();
    $time->setTime($date->getTimestamp());

    $url = Url::fromRoute('oe_dashboard_agent_test.protected');

    // Test that access is denied with no hash in header.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Test that access is denied with no token in the environment.
    $this->drupalGet($url, [], ['NETOKEN' => $this->correctHash]);
    $this->assertSession()->statusCodeEquals(403);

    // Test that access is denied with a wrong token in the environment.
    $this->setEnvironmentToken('wrong-token');
    $this->drupalGet($url, [], ['NETOKEN' => $this->correctHash]);
    $this->assertSession()->statusCodeEquals(403);

    // Test that access is denied with incorrect hash in header.
    $this->drupalGet($url, [], ['NETOKEN' => '70870cce44daa1745b2af95ed6b374ed41cd2e809176c7c0fe8c06e337fd29f2cc2cf413b55540be168c1776fff631e259bbb87f7840897c73f0551086584cf1d']);
    $this->assertSession()->statusCodeEquals(403);

    // Set a correct token in the environment.
    $this->setEnvironmentToken($this->getEnvironmentToken());

    // Test that access is granted with a correct hash in header.
    $this->drupalGet($url, [], ['NETOKEN' => $this->correctHash]);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests access to the test route by generating a fresh token.
   */
  public function testRouteAccessWithHashGeneration(): void {
    // Set the correct token in the environment.
    $this->setEnvironmentToken($this->getEnvironmentToken());

    // Generate a hash for the current time.
    $date = new DrupalDateTime('now');
    $current_hash = $this->generateHash($date);

    // Generate a hash for yesterday.
    $date = new DrupalDateTime('now');
    $date = $date->modify('-1 day');
    $yesterday_hash = $this->generateHash($date);

    // Generate a hash for tomorrow.
    $date = new DrupalDateTime('now');
    $date->modify('+2 days');
    $tomorrow_hash = $this->generateHash($date);

    $url = Url::fromRoute('oe_dashboard_agent_test.protected');

    $this->drupalGet($url, [], ['NETOKEN' => $current_hash]);
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet($url, [], ['NETOKEN' => $yesterday_hash]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet($url, [], ['NETOKEN' => $tomorrow_hash]);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Generates a hash to be sent for authentication in the header.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date of the generation.
   *
   * @return string
   *   The hash.
   */
  protected function generateHash(DrupalDateTime $date): string {
    $token = Settings::get('oe_dashboard_agent.token');
    // The salt has to be 4 characters.
    $salt = '4444';
    $date = $date->format('Ymd');
    $temporary_token = $salt . $token . $date;
    $hash = hash('SHA512', $temporary_token);
    return $salt . $hash;
  }

  /**
   * Returns a test environment token.
   *
   * This is the token used for hashing the hardcoded test hash on August 8
   * 2020.
   *
   * @return string
   *   The token.
   */
  protected function getEnvironmentToken(): string {
    return '366035753E4387';
  }

  /**
   * Sets a token to the site environment.
   *
   * @param string $token
   *   The token.
   */
  protected function setEnvironmentToken(string $token): void {
    $settings['settings']['oe_dashboard_agent.token'] = (object) [
      'value' => $token,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

}
