<?php

namespace Drupal\hydro_raindrop\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SignupForm.
 */
class SignupForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'signup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['hydro_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hydro Username'),
      '#description' => 'Enter your Hydro username, visible in the Hydro mobile app.',
      '#maxlength' => 7,
      '#size' => 7,
      '#weight' => '0',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['link_account'] = [
      '#type' => 'button',
      '#value' => $this->t('Link'),
      '#ajax' => array(
        'callback' => '::LinkAccount',
        'wrapper' => $this->getFormId(),
        'method' => 'replace',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Linking...'),
        ],
      ),
      '#weight' => '1',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Successful link, proceed to verification'),
      '#attributes' => [
        'style' => 'display: none'
      ],
      '#weight' => '2',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }

  }

  public static function LinkAccount(array &$form, FormStateInterface $form_state)
  {
    $ajax_response = new AjaxResponse();

    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', true])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-link-account', 'attr', ['disabled', 'disabled'])
    );

    sleep(1.75);

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
      new InvokeCommand('#edit-link-account', 'css', ['display', 'none'])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-submit', 'attr', ['style', 'margin-left: 0'])
    );

    return $ajax_response;
  }

}
