<?php

namespace Drupal\hydro_raindrop\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hydro_raindrop.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hydro_raindrop.settings');
    $form['application_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application ID'),
      '#description' => $this->l($this->t('Register an account'), Url::fromUri('https://www.hydrogenplatform.com')) . ' ' . $this->t('to obtain an Application ID.'),
      '#maxlength' => 36,
      '#size' => 36,
      '#default_value' => $config->get('application_id'),
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#maxlength' => 26,
      '#size' => 26,
      '#default_value' => $config->get('client_id'),
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#maxlength' => 26,
      '#size' => 26,
      '#default_value' => $config->get('client_secret'),
    ];
    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#options' => [
        'Adrenth\Raindrop\Environment\ProductionEnvironment' => $this->t('Production'),
        'Adrenth\Raindrop\Environment\SandboxEnvironment' => $this->t('Sandbox')
      ],
      '#size' => 1,
      '#default_value' => $config->get('environment'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('hydro_raindrop.settings')
      ->set('application_id', $form_state->getValue('application_id'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('environment', $form_state->getValue('environment'))
      ->save();
  }

}
