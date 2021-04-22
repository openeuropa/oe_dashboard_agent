<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\InfoParserException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_dashboard_agent\Event\ExtensionsInfoAlterEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Route controller for dashboard extensions endpoint.
 */
class ExtensionsController extends ControllerBase {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The location of the manifest file.
   *
   * @var string
   */
  protected $manifestFileLocation;

  /**
   * ExtensionsController constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param string $manifest_file_location
   *   The location of the manifest file.
   */
  public function __construct(ModuleExtensionList $extension_list_module, ThemeHandlerInterface $theme_handler, LoggerChannelFactoryInterface $logger_factory, EventDispatcherInterface $eventDispatcher, string $manifest_file_location) {
    $this->moduleExtensionList = $extension_list_module;
    $this->themeHandler = $theme_handler;
    $this->logger = $logger_factory->get('dashboard_agent');
    $this->eventDispatcher = $eventDispatcher;
    $this->manifestFileLocation = $manifest_file_location;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('theme_handler'),
      $container->get('logger.factory'),
      $container->get('event_dispatcher'),
      $container->getParameter('oe_dashboard_agent.manifest_file_location')
    );
  }

  /**
   * Generates a json containing all available extensions.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function extensions() {
    // Get all available modules and profiles.
    try {
      $extensions = $this->moduleExtensionList->reset()->getList();
      // Sort modules by name.
      uasort($extensions, 'system_sort_modules_by_info_name');
    }
    catch (InfoParserException $e) {
      $this->logger->warning($this->t('Extensions could not be listed due to an error: %error', ['%error' => $e->getMessage()]));
      $extensions = [];
    }

    // Get all available themes.
    try {
      $themes = $this->themeHandler->rebuildThemeData();
      // Sort themes by name.
      uasort($themes, 'system_sort_modules_by_info_name');
    }
    catch (InfoParserException $e) {
      $this->logger->warning($this->t('Themes could not be listed due to an error: %error', ['%error' => $e->getMessage()]));
      $themes = [];
    }

    // Get default theme.
    $config = $this->config('system.theme');
    $theme_default = $config->get('default');

    $info = [];

    // Retrieve modules and profiles information.
    foreach ($extensions as $extension_name => $extension) {
      $package = isset($extension->info['package']) ? $extension->info['package'] : '';
      // Skip testing and core experimental modules.
      if ($package === 'Testing' || $package === 'Core (Experimental)') {
        continue;
      }
      // Get profiles.
      if ($extension->getType() === 'profile') {
        $info['profiles'][$extension_name] = [
          'name' => $extension->info['name'],
          'package' => $package,
          'version' => isset($extension->info['version']) ? $extension->info['version'] : '',
          'path' => $extension->getPathname(),
          'installed' => (bool) $extension->status,
          'requires' => array_keys($extension->requires) ? array_keys($extension->requires) : '',
        ];
        continue;
      }
      $info['modules'][$extension_name] = [
        'name' => $extension->info['name'],
        'package' => $package ? $package : '',
        'version' => isset($extension->info['version']) ? $extension->info['version'] : '',
        'path' => $extension->getPathname(),
        'installed' => (bool) $extension->status,
        'requires' => array_keys($extension->requires) ? array_keys($extension->requires) : '',
        'schema_version' => drupal_get_installed_schema_version($extension_name),
      ];
    }

    // Retrieve themes information.
    foreach ($themes as $theme_name => $theme) {
      $package = isset($theme->info['package']) ? $theme->info['package'] : '';
      // Skip test themes.
      if ($package === 'Testing') {
        continue;
      }
      $info['themes'][$theme_name] = [
        'name' => $theme->info['name'],
        'package' => $package ? $package : '',
        'version' => isset($theme->info['version']) ? $theme->info['version'] : '',
        'path' => $theme->getPathname(),
        'installed' => (bool) $theme->status,
        'default' => ($theme_name === $theme_default),
      ];
    }

    // Add Drupal and PHP versions.
    $info['drupal_version'] = \DRUPAL::VERSION;
    $info['php_version'] = phpversion();

    $this->addSiteVersion($info);

    $event = new ExtensionsInfoAlterEvent($info);
    $this->eventDispatcher->dispatch(ExtensionsInfoAlterEvent::EVENT, $event);

    $this->logger->info('The list of extensions was requested.');
    return new JsonResponse(['extensions' => $event->getInfo()]);
  }

  /**
   * Adds the site version to the info.
   *
   * @param array $info
   *   The extensions info.
   */
  protected function addSiteVersion(array &$info): void {
    if (!file_exists($this->manifestFileLocation) || !is_readable($this->manifestFileLocation)) {
      $this->logger->warning('The manifest.json file was not found.');
      return;
    }

    $file_content = file_get_contents($this->manifestFileLocation);
    if (!$file_content) {
      $this->logger->warning('The manifest.json file could not be read.');
      return;
    }

    $manifest = json_decode($file_content, TRUE);
    if (!$manifest) {
      $this->logger->warning('The manifest.json file could not be decoded.');
      return;
    }

    $info['site_version'] = $manifest['version'];
    $info['site_commit'] = isset($manifest['sha']) ? $manifest['sha'] : '';
  }

}
