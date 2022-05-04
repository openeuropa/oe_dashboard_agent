<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides data from the manifest file.
 */
class ManifestFileReader implements ContainerInjectionInterface {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The location of the manifest file.
   *
   * @var string
   */
  protected $manifestFileLocation;

  /**
   * ManifestFileReader constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   * @param string $manifest_file_location
   *   The location of the manifest file.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, string $manifest_file_location) {
    $this->logger = $logger_factory->get('dashboard_agent');
    $this->manifestFileLocation = $manifest_file_location;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->getParameter('oe_dashboard_agent.manifest_file_location')
    );
  }

  /**
   * Get the file content.
   *
   * @return string|false
   *   Returns string if file can be read and false otherwise.
   */
  protected function getFileContent() {
    if (!file_exists($this->manifestFileLocation) || !is_readable($this->manifestFileLocation)) {
      $this->logger->warning('The manifest.json file was not found.');
      return FALSE;
    }

    $file_content = file_get_contents($this->manifestFileLocation);
    if (!$file_content) {
      $this->logger->warning('The manifest.json file could not be read.');
      return FALSE;
    }

    return $file_content;
  }

  /**
   * Gets data array from the file.
   *
   * @return array|null
   *   Returns array with data if data can be parsed and null otherwise.
   */
  public function getData() {
    $file_content = $this->getFileContent();
    if (!$file_content) {
      return NULL;
    }

    $data = json_decode($file_content, TRUE);
    if (!$data) {
      $this->logger->warning('The manifest.json file could not be decoded.');
      return NULL;
    }
    return $data;
  }

}
