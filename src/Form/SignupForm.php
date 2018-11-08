<?php

namespace Drupal\hydro_raindrop\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\User\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SignupForm.
 */
class SignupForm extends FormBase
{

  protected $tempStore;

  /**
   * Constructs a new SignupForm object.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('hydro_raindrop');
  }
 
  /**
   * Todo: comment
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'signup-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['hydro_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hydro Username'),
      '#description' => 'Enter your Hydro username, visible in the Hydro mobile app.',
      '#maxlength' => 7,
      '#size' => 7,
      '#weight' => '0',
    ];

    $form['hydro_raindrop_code'] = [
      '#prefix' => '<div class="hydro-raindrop-code">',
      '#suffix' => '</div>',
    ];

    $form['register'] = [
      '#type' => 'button',
      '#value' => $this->t('Link'),
      '#ajax' => array(
        'callback' => '::ajaxRegister',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Linking...'),
        ],
      ),
      '#weight' => '1',
    ];

    $form['submit'] = [
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->verifySignature($form_state->getValue('hydro_username'), (int) $this->tempStore->get('hydro_raindrop_code'));
  }

  /**
   * Todo: comment
   */
  private function getClient() {
    $config = $this->config('hydro_raindrop.settings');

    $clientId = $config->get('client_id');
    $clientSecret = $config->get('client_secret');
    $applicationId = $config->get('application_id');

    $settings = new \Adrenth\Raindrop\ApiSettings(
        $clientId,
        $clientSecret,
        new \Adrenth\Raindrop\Environment\SandboxEnvironment
    );

    // Create token storage for storing the API's access token.
    $tokenStorage = new \Adrenth\Raindrop\TokenStorage\FileTokenStorage(__DIR__ . '/token.txt');

    // Ideally create your own TokenStorage adapter. 
    // The shipped FileTokenStorage is purely an example of how to create your own.

    /*
    * Client-side calls
    */
    return new \Adrenth\Raindrop\Client($settings, $tokenStorage, $applicationId);
  }

  /**
   * Todo: comment
   */
  private function _lockForm(AjaxResponse &$ajax_response) {
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', TRUE])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-register', 'attr', ['disabled', TRUE])
    );
  }

  /**
   * Todo: comment
   */
  private function _unlockForm(AjaxResponse &$ajax_response) {
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', FALSE])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-register', 'attr', ['disabled', FALSE])
    );
  }

  /**
   * Todo: comment
   */
  public function ajaxRegister(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    $this->_lockForm($ajax_response);
    $this->registerUser($ajax_response, $form_state->getValue('hydro_username'));

    return $ajax_response;
  }

  /**
   * Register a user by Hydro ID.
   */
  protected function registerUser(AjaxResponse &$ajax_response, string $hydroId) {
    try {
      $this->getClient()->registerUser($hydroId);

      drupal_set_message(t('Hydro Account <b><i>@username</i></b> has been linked.', ['@username' => $hydroId]));

      $this->generateCode($ajax_response);
    }
    catch (\Adrenth\Raindrop\Exception\UserAlreadyMappedToApplication $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> was already mapped to this application.', ['@username' => $hydroId]), 'warning');

      $this->getClient()->unregisterUser($hydroId);
      $this->getClient()->registerUser($hydroId);

      $this->generateCode($ajax_response);
    }
    catch (\Adrenth\Raindrop\Exception\UsernameDoesNotExist $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> does not exist.', ['@username' => $hydroId]), 'error');
      $this->_unlockForm($ajax_response);
    }

    $ajax_response->addCommand(new HtmlCommand('.region-highlighted', ['#type' => 'status_messages']));
  }

  /**
   * Generate 6 digit code.
   */
  protected function generateCode(AjaxResponse &$ajax_response) {
    $this->tempStore->set('hydro_raindrop_code', $this->getClient()->generateMessage());

    // Display hydro_raindrop_code.
    $ajax_response->addCommand(
      new HtmlCommand(
        '.hydro-raindrop-code',
        '6 digit code: ' . $this->tempStore->get('hydro_raindrop_code')
      )
    );
    
    $ajax_response->addCommand(
      new InvokeCommand('#edit-submit', 'attr', ['disabled', FALSE])
    );
  }

  /**
   * Verify Hydro user signature.
   */
  protected function verifySignature(string $hydroId, int $code) {
    try {
      $this->getClient()->verifySignature($hydroId, $code);
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> has been verified.', ['@username' => $hydroId]));
    }
    catch (\Adrenth\Raindrop\Exception\VerifySignatureFailed $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> could not be verified.', ['@username' => $hydroId]), 'error');
    }
  }

}
