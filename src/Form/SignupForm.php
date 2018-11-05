<?php

namespace Drupal\hydro_raindrop\Form;

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
        'callback' => 'Drupal\hydro_raindrop\Controller\SignupController::LinkAccount',
        'wrapper' => $this->getFormId(),
        'method' => 'replace',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Linking HydroID...'),
        ],
      ),
      '#weight' => '1',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Successful link, proceed to verification'),
      '#attributes' => [
        'disabled' => 'disabled'
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

}
