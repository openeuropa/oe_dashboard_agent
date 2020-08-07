<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_dashboard_agent\src\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the dashboard agent endpoints with route access checker.
 */
class DashboardAgentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'datetime_testing',
    'oe_dashboard_agent',
    'oe_dashboard_agent_test',
    'dblog',
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
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access site reports',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    if (file_exists('../manifest.json')) {
      unlink('../manifest.json');
    }

    parent::tearDown();
  }

  /**
   * Tests access to the endpoints with the access checker.
   */
  public function testRouteAccessWithExistingHash(): void {
    // Freeze the time on August 8 2020 when the hash was generated.
    $date = DrupalDateTime::createFromFormat('Y-m-d H', '2020-08-03 12');
    $time = \Drupal::service('datetime.time');
    $time->freezeTime();
    $time->setTime($date->getTimestamp());

    $routes = $this->getEndpointRoutes();

    foreach ($routes as $route) {
      $url = Url::fromRoute($route);

      // Test that access is denied with no hash in header.
      $this->setEnvironmentToken('');
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
  }

  /**
   * Tests access to the endpoints by generating a fresh token.
   */
  public function testRouteAccessWithHashGeneration(): void {
    // Set the correct token in the environment.
    $this->setEnvironmentToken($this->getEnvironmentToken());

    // Generate a hash for the current time.
    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    $current_hash = $this->generateHash($date);

    // Generate a hash for yesterday.
    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    $date->modify('-1 day');
    $yesterday_hash = $this->generateHash($date);

    // Generate a hash for tomorrow.
    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    $date->modify('+2 days');
    $tomorrow_hash = $this->generateHash($date);

    $routes = $this->getEndpointRoutes();

    foreach ($routes as $route) {
      $url = Url::fromRoute($route);

      $this->drupalGet($url, [], ['NETOKEN' => $current_hash]);
      $this->assertSession()->statusCodeEquals(200);

      $this->drupalGet($url, [], ['NETOKEN' => $yesterday_hash]);
      $this->assertSession()->statusCodeEquals(403);

      $this->drupalGet($url, [], ['NETOKEN' => $tomorrow_hash]);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * Tests the response for the URI endpoint.
   */
  public function testUliEndpoint(): void {
    // Set the correct token in the environment.
    $this->setEnvironmentToken($this->getEnvironmentToken());

    // Generate a hash for the current time.
    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    $current_hash = $this->generateHash($date);

    $url = Url::fromRoute('oe_dashboard_agent.uli');
    $json = $this->drupalGet($url, [], ['NETOKEN' => $current_hash]);
    $response = json_decode($json);
    $uli = $response->uli;

    // Access the ULI and assert we got logged in.
    $this->drupalGet($uli);
    $this->assertSession()->pageTextContains('You have just used your one-time login link. It is no longer necessary to use this link to log in. Please change your password.');
  }

  /**
   * Tests the response for the extensions endpoint.
   */
  public function testExtensionsEndpoint(): void {
    // Set the correct token in the environment.
    $this->setEnvironmentToken($this->getEnvironmentToken());

    // Generate a hash for the current time.
    $date = new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE);
    $hash = $this->generateHash($date);

    $extensions = $this->requestExtensions($hash);

    // Assert a module.
    $modules = $extensions->modules;
    $this->assertEquals('OpenEuropa Dashboard Agent', $modules->oe_dashboard_agent->name);
    $this->assertEquals('OpenEuropa', $modules->oe_dashboard_agent->package);
    $this->assertEquals('', $modules->oe_dashboard_agent->version);
    $this->assertEquals('modules/custom/oe_dashboard_agent/oe_dashboard_agent.info.yml', $modules->oe_dashboard_agent->path);
    $this->assertEquals(TRUE, $modules->oe_dashboard_agent->installed);
    $this->assertEquals(-1, $modules->oe_dashboard_agent->schema_version);
    $this->assertEquals(['datetime', 'field'], $modules->oe_dashboard_agent->requires);

    // Assert a profile.
    $profiles = $extensions->profiles;
    $this->assertEquals('Testing', $profiles->testing->name);
    $this->assertEquals('Other', $profiles->testing->package);
    $this->assertEquals('core/profiles/testing/testing.info.yml', $profiles->testing->path);
    $this->assertEquals(TRUE, $profiles->testing->installed);
    $this->assertEquals("", $profiles->testing->requires);

    // Assert a theme.
    $themes = $extensions->themes;
    $this->assertEquals('Bartik', $themes->bartik->name);
    $this->assertEquals('Core', $themes->bartik->package);
    $this->assertEquals('core/themes/bartik/bartik.info.yml', $themes->bartik->path);
    $this->assertEquals(FALSE, $themes->bartik->installed);
    $this->assertEquals(FALSE, $themes->bartik->default);

    // Assert the Drupal version.
    $this->assertEquals(\Drupal::VERSION, $extensions->drupal_version);
    $this->assertContains('php_version', array_keys((array) $extensions));

    // Assert that we can alter the information.
    $this->assertNotContains('oe_dashboard_agent_test.extensions_alter', array_keys((array) $extensions));
    \Drupal::state()->set('oe_dashboard_agent_test.extensions_alter', 'altered');
    $extensions = $this->requestExtensions($hash);
    $this->assertContains('oe_dashboard_agent_test.extensions_alter', array_keys((array) $extensions));

    // The manifest.json file is missing.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextContains('The manifest.json file was not found.');
    $this->assertSession()->pageTextNotContains('The manifest.json file count not be read.');
    $this->clearLogMessages();

    // Create an empty/invalid file in the expected location.
    file_put_contents('../manifest.json', FALSE);
    $this->requestExtensions($hash);
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextNotContains('The manifest.json file was not found.');
    $this->assertSession()->pageTextContains('The manifest.json file count not be read.');
    $this->clearLogMessages();

    // Create an non-json file in the expected location.
    file_put_contents('../manifest.json', 'Not json');
    $this->requestExtensions($hash);
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextNotContains('The manifest.json file was not found.');
    $this->assertSession()->pageTextNotContains('The manifest.json file count not be read.');
    $this->assertSession()->pageTextContains('The manifest.json file could not be decoded.');
    $this->clearLogMessages();

    // Move the correct JSON file.
    file_put_contents('../manifest.json', file_get_contents(drupal_get_path('module', 'oe_dashboard_agent') . '/tests/fixtures/manifest.json'));
    $extensions = $this->requestExtensions($hash);
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextNotContains('The manifest.json file was not found.');
    $this->assertSession()->pageTextNotContains('The manifest.json file count not be read.');
    $this->assertSession()->pageTextNotContains('The manifest.json file could not be decoded.');
    $this->assertEquals('0.5', $extensions->site_version);
    $this->assertEquals('dfs6dfwu34yr32423e23', $extensions->site_commit);
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

  /**
   * Returns the list of endpoint routes to be tested.
   *
   * @return array
   *   The list.
   */
  protected function getEndpointRoutes(): array {
    return [
      'oe_dashboard_agent.uli',
      'oe_dashboard_agent.extensions',
    ];
  }

  /**
   * Makes a request to the extensions endpoint.
   *
   * @param string $hash
   *   The authentication hash.
   *
   * @return object
   *   The extensions list.
   */
  protected function requestExtensions(string $hash): object {
    $url = Url::fromRoute('oe_dashboard_agent.extensions');
    $json = $this->drupalGet($url, [], ['NETOKEN' => $hash]);
    $response = json_decode($json);
    return $response->extensions;
  }

  /**
   * Clears the DB log messages.
   */
  protected function clearLogMessages(): void {
    $this->drupalGet(Url::fromRoute('dblog.confirm'));
    $this->drupalPostForm(NULL, [], 'Confirm');
  }

}
