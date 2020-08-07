<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched to alter the extensions info returned by the agent.
 */
class ExtensionsInfoAlterEvent extends Event {

  /**
   * The event name.
   */
  const EVENT = 'oe_dashboard_agent.extensions_info_alter';

  /**
   * The extensions info.
   *
   * @var array
   */
  protected $info;

  /**
   * ExtensionsInfoAlterEvent constructor.
   *
   * @param array $info
   *   The extensions info.
   */
  public function __construct(array $info) {
    $this->info = $info;
  }

  /**
   * Returns the extensions info.
   *
   * @return array
   *   The extensions info.
   */
  public function getInfo(): array {
    return $this->info;
  }

  /**
   * Sets the extensions info.
   *
   * @param array $info
   *   The extensions info.
   */
  public function setInfo(array $info): void {
    $this->info = $info;
  }

}
