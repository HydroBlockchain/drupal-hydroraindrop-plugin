<?php

namespace Drupal\hydro_raindrop\Form;

use Adrenth\Raindrop\ApiSettings;
use Adrenth\Raindrop\Client;
use Adrenth\Raindrop\Environment;
use Adrenth\Raindrop\Exception;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hydro_raindrop\TokenStorage\PrivateTempStoreStorage;
use Drupal\User\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * @var Drupal\User\PrivateTempStore
   */
  protected $tempStore;

  /**
   * Constructs a new SettingsForm object.
   *
   * @param PrivateTempStoreFactory $temp_store_factory
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('hydro_raindrop');
  }
 
  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore')
    );
  }

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
        'Production' => $this->t('Production'),
        'Sandbox' => $this->t('Sandbox')
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

    try {
      $this->getClient(
        $form_state->getValue('application_id'),
        $form_state->getValue('client_id'),
        $form_state->getValue('client_secret'),
        $form_state->getValue('environment')
      )->getAccessToken();
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('client_id', 'There was an error verifying these credentials. Please check your Client ID and Secret, then try again.');
      $form_state->setErrorByName('client_secret');
    }
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

  /**
   * Uses the Raindrop developer's API credentials to return a client object.
   *
   * @param Environment $environment
   *
   * @return Client
   */
  protected function getClient($applicationId, $clientId, $clientSecret, $environment): Client {

    // Instantiate the appropriate Environment class
    switch($environment) {
      case 'Production' :
        $environment = new \Adrenth\Raindrop\Environment\ProductionEnvironment();
        break;
      default :
        $environment = new \Adrenth\Raindrop\Environment\SandboxEnvironment();
        break;
    }

    // Clear the current access token and get a new one to ensure it's valid.
    $tokenStorage = new PrivateTempStoreStorage($this->tempStore);
    $tokenStorage->unsetAccessToken();

    $settings = new ApiSettings(
      $clientId,
      $clientSecret,
      $environment
    );

    return new Client($settings, $tokenStorage, $applicationId);
  }

}
