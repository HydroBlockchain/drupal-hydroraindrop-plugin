<?php

namespace Drupal\hydro_raindrop\Form;

use Adrenth\Raindrop\ApiSettings;
use Adrenth\Raindrop\Client;
use Adrenth\Raindrop\Environment;
use Adrenth\Raindrop\Exception;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hydro_raindrop\TokenStorage\PrivateTempStoreStorage;
use Drupal\user\Entity\User;
use Drupal\User\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LinkAccountForm.
 */
class LinkAccountForm extends FormBase
{

  /**
   * @var Drupal\User\PrivateTempStore
   */
  protected $tempStore;

  /**
   * Constructs a new LinkAccountForm object.
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
  public function getFormId() {
    return 'link-account-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['hydro_raindrop_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hydro Username'),
      '#description' => 'Enter your Hydro username, visible in the Hydro mobile app.',
      '#maxlength' => 7,
      '#size' => 7,
      '#weight' => '0',
    ];

    $form['hydro_raindrop_message'] = [
      '#prefix' => '<div id="hydro-raindrop-message">',
      '#suffix' => '</div>',
    ];

    $form['hydro_raindrop_ajax_register_user'] = [
      '#type' => 'button',
      '#value' => $this->t('Register'),
      '#ajax' => array(
        'callback' => '::ajaxRegisterUser',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Registering...'),
        ],
      ),
      '#weight' => '1',
    ];

    $form['hydro_raindrop_submit'] = [
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
    $hydroId = $form_state->getValue('hydro_raindrop_id');
    $message = (int) $this->tempStore->get('hydro_raindrop_message');
    
    // If the user passes verification...
    if ($this->verifySignature($hydroId, $message)) {
      // Attach the Raindrop ID to user and indicate that Raindrop is enabled.
      $user = User::load(\Drupal::currentUser()->id());
      $user->set('field_link_hydro_raindrop', TRUE);
      $user->set('field_hydro_raindrop_id', $hydroId);
      $user->save();

      // Redirect to profile page.
      $form_state->setRedirect('user.page');
    }
  }

  /**
   * Uses the Raindrop developer's API credentials to return a client object.
   *
   * @param Environment $environment
   *
   * @return Client
   */
  public function getClient(Environment $environment = NULL): Client {
    $config = $this->config('hydro_raindrop.settings');
    $clientId = $config->get('client_id');
    $clientSecret = $config->get('client_secret');
    $applicationId = $config->get('application_id');
    $tokenStorage = new PrivateTempStoreStorage($this->tempStore);
    if (!$environment) {
      $environment = new Environment\SandboxEnvironment;
    }

    $settings = new ApiSettings(
      $clientId,
      $clientSecret,
      $environment
    );

    return new Client($settings, $tokenStorage, $applicationId);
  }

  /**
   * Asynchronously register a user using the provided Hydro ID.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return AjaxResponse
   */
  public function ajaxRegisterUser(array &$form, FormStateInterface $form_state): AjaxResponse {
    $ajax_response = new AjaxResponse();
    $client = $this->getClient();
    $hydroId = $form_state->getValue('hydro_raindrop_id');

    $this->_lockForm($ajax_response);

    try {
      $client->registerUser($hydroId);

      drupal_set_message(t('Hydro Account <b><i>@username</i></b> has been successfully registered.', ['@username' => $hydroId]));

      $this->ajaxGenerateMessage($ajax_response);
    }
    catch (Exception\UserAlreadyMappedToApplication $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> was already mapped to this application.', ['@username' => $hydroId]), 'warning');

      $client->unregisterUser($hydroId);
      $client->registerUser($hydroId);

      $this->ajaxGenerateMessage($ajax_response);
    }
    catch (Exception\UsernameDoesNotExist $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> does not exist.', ['@username' => $hydroId]), 'error');
      $this->_unlockForm($ajax_response);
    }

    $ajax_response->addCommand(new HtmlCommand('.region-highlighted', ['#type' => 'status_messages']));

    return $ajax_response;
  }

  /**
   * Generate 6 digit message.
   *
   * @param AjaxResponse $ajax_response
   */
  public function ajaxGenerateMessage(AjaxResponse &$ajax_response) {
    $client = $this->getClient();
    $this->tempStore->set('hydro_raindrop_message', $client->generateMessage());

    // Display hydro_raindrop_message.
    $ajax_response->addCommand(
      new HtmlCommand(
        '#hydro-raindrop-message',
        '6 digit message: ' . $this->tempStore->get('hydro_raindrop_message')
      )
    );
    
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-raindrop-submit', 'attr', ['disabled', FALSE])
    );
  }

  /**
   * Verify Hydro user signature.
   *
   * @param string $hydroId
   * @param integer $message
   *
   * @return bool
   */
  public function verifySignature(string $hydroId, int $message): bool {
    $client = $this->getClient();
    try {
      $client->verifySignature($hydroId, $message);
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> has been verified.', ['@username' => $hydroId]));
      return TRUE;
    }
    catch (Exception\VerifySignatureFailed $e) {
      drupal_set_message(t('Hydro Account <b><i>@username</i></b> could not be verified.', ['@username' => $hydroId]), 'error');
    }
    return FALSE;
  }

  /**
   * Prevents user from editing their ID once clicking the Register button and disables button.
   *
   * @param AjaxResponse $ajax_response
   */
  protected function _lockForm(AjaxResponse &$ajax_response) {
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-raindrop-id', 'attr', ['readonly', TRUE])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-raindrop-ajax-register-user', 'attr', ['disabled', TRUE])
    );
  }

  /**
   * Allows a user to edit their ID and re-attempt to register (i.e. in the case of an error).
   *
   * @param AjaxResponse $ajax_response
   */
  protected function _unlockForm(AjaxResponse &$ajax_response) {
    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-raindrop-id', 'attr', ['readonly', FALSE])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-raindrop-ajax-register-user', 'attr', ['disabled', FALSE])
    );
  }

}
