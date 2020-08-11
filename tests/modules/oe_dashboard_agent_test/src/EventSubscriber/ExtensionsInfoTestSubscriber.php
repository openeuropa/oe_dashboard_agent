<?php

declare(strict_types = 1);

namespace Drupal\oe_dashboard_agent_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_dashboard_agent\Event\ExtensionsInfoAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test subscriber to the extensions info alter event.
 */
class ExtensionsInfoTestSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * ExtensionsInfoTestSubscriber constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [ExtensionsInfoAlterEvent::EVENT => 'alterInfo'];
  }

  /**
   * Alters the extensions info.
   *
   * @param \Drupal\oe_dashboard_agent\Event\ExtensionsInfoAlterEvent $event
   *   TRhe event.
   */
  public function alterInfo(ExtensionsInfoAlterEvent $event): void {
    if ($this->state->get('oe_dashboard_agent_test.extensions_alter', FALSE)) {
      $info = $event->getInfo();
      $info['oe_dashboard_agent_test.extensions_alter'] = $this->state->get('oe_dashboard_agent_test.extensions_alter');
      $event->setInfo($info);
    }
  }

}
