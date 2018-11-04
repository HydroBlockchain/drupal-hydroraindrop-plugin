<?php

namespace Drupal\hydro_raindrop\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ActivateRaindropController.
 */
class ActivateRaindropController extends ControllerBase {

  /**
   * Todo: Add details...
   *
   * @return string
   *   Return Raindrop activation form.
   */
  public function content() {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    return [
      '#theme' => 'activate_raindrop',
      '#raindrop_activated' => $user->field_activate_raindrop->value ? $user->field_activate_raindrop->getSetting('on_label') : $user->field_activate_raindrop->getSetting('off_label'),
    ];
  }

}
