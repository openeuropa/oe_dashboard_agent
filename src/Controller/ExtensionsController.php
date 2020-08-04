<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\InfoParserException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * ExtensionsController constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(ModuleExtensionList $extension_list_module, ThemeHandlerInterface $theme_handler) {
    $this->moduleExtensionList = $extension_list_module;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('theme_handler')
    );
  }

  /**
   * Generates a json containing system information.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function extensions() {
    // Get all available modules and profiles.
    try {
      $modules = $this->moduleExtensionList->reset()->getList();
      // Sort modules by name.
      uasort($modules, 'system_sort_modules_by_info_name');
    }
    catch (InfoParserException $e) {
      $this->messenger()->addError($this->t('Modules could not be listed due to an error: %error', ['%error' => $e->getMessage()]));
      $modules = [];
    }

    // Get all available themes.
    try {
      $themes = $this->themeHandler->rebuildThemeData();
      // Sort themes by name.
      uasort($themes, 'system_sort_modules_by_info_name');
    }
    catch (InfoParserException $e) {
      $this->messenger()->addError($this->t('Themes could not be listed due to an error: %error', ['%error' => $e->getMessage()]));
      $themes = [];
    }

    // Get default theme.
    $config = $this->config('system.theme');
    $theme_default = $config->get('default');

    $info = [];

    // Retrieve modules and profiles information.
    foreach ($modules as $filename => $module) {
      $package = $module->info['package'];
      // Skip testing and core experimental modules.
      if ($package === 'Testing' || $package === 'Core (Experimental)') {
        continue;
      }
      // Get profiles.
      if (strpos($module->getPathname(), '/profiles/') !== FALSE) {
        $info['profiles'][$filename] = [
          'name' => $module->info['name'],
          'package' => $package ? $package : '',
          'version' => $module->info['version'] ? $module->info['version'] : '',
          'path' => $module->getPathname(),
          'installed' => (bool) $module->status,
          'requires' => array_keys($module->requires) ? array_keys($module->requires) : '',
        ];
        continue;
      }
      $info['modules'][$filename] = [
        'name' => $module->info['name'],
        'package' => $package ? $package : '',
        'version' => $module->info['version'] ? $module->info['version'] : '',
        'path' => $module->getPathname(),
        'installed' => (bool) $module->status,
        'requires' => array_keys($module->requires) ? array_keys($module->requires) : '',
      ];
    }

    // Retrieve themes information.
    foreach ($themes as $filename => $theme) {
      // Skip test themes.
      if (strpos($filename, 'test') !== FALSE) {
        continue;
      }
      $package = $theme->info['package'];
      $info['themes'][$filename] = [
        'name' => $theme->info['name'],
        'package' => $package ? $package : '',
        'version' => $theme->info['version'] ? $theme->info['version'] : '',
        'path' => $theme->getPathname(),
        'installed' => (bool) $theme->status,
        'default' => ($filename === $theme_default),
      ];
    }

    return new JsonResponse(['extensions' => $info]);
  }

}
