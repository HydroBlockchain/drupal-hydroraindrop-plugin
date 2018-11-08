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

    $form['hydro_raindrop_message'] = [
      '#prefix' => '<div class="hydro-raindrop-message">',
      '#suffix' => '</div>',
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['register'] = [
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->verifySignature($form_state->getValue('hydro_username'), (int) $this->tempStore->get('hydro_raindrop_message'));

    drupal_set_message(
      $this->t('Hydro Account <b><i>@username</i></b> has been verified.',
      ['@username' => $form_state->getValue('hydro_username')])
    );
    
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
  public function ajaxRegister(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', TRUE])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-register', 'attr', ['disabled', TRUE])
    );

    $this->registerUser($form_state->getValue('hydro_username'));

    // Account registered!
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

    $this->tempStore->set('hydro_raindrop_message', $this->generateMessage());

    // Display message.
    $ajax_response->addCommand(
      new HtmlCommand(
        '.hydro-raindrop-message',
        '6 digit message: ' . $this->tempStore->get('hydro_raindrop_message')
      )
    );
    
    $ajax_response->addCommand(
      new InvokeCommand('#edit-submit', 'attr', ['disabled', FALSE])
    );

    return $ajax_response;
  }

  /**
   * Register a user by Hydro ID.
   */
  public function registerUser(string $hydroId) {
    try {
      $this->getClient()->registerUser($hydroId);
    }
    catch (\Adrenth\Raindrop\Exception\UserAlreadyMappedToApplication $e) {

    }
  }

  /**
   * Generate 6 digit message.
   */
  public function generateMessage() {
    return $this->getClient()->generateMessage();
  }

  /**
   * Verify Hydro user signature.
   */
  public function verifySignature(string $hydroId, int $message) {
    try {
      $this->getClient()->verifySignature($hydroId, $message);
    }
    catch (\Adrenth\Raindrop\Exception\VerifySignatureFailed $e) {

    }
  }

}
