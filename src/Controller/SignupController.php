<?php

namespace Drupal\hydro_raindrop\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SignupController.
 */
class SignupController extends ControllerBase
{

  public static function LinkAccount(array &$form, FormStateInterface $form_state)
  {
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', true])
    );
    $ajax_response->addCommand(
      new InvokeCommand('#edit-link-account', 'attr', ['disabled', 'disabled'])
    );
    $ajax_response->addCommand(
      new HtmlCommand(
        '.region-highlighted',
        '<div role="contentinfo" aria-label="Status message" class="messages messages--status">
          <div class="messages__content container">
          <h2 class="visually-hidden">Status message</h2>
            <ul class="messages__list">
              <li class="messages__item">' . t('Hydro Account <b><i>@username</i></b> has been linked.', ['@username' => $form_state->getValue('hydro_username')]) . '</li>
            </ul>
          </div>
        </div>'
      )
    );
    $ajax_response->addCommand(
      new InvokeCommand('#edit-submit', 'removeAttr', ['disabled'])
    );
    return $ajax_response;
  }

}
