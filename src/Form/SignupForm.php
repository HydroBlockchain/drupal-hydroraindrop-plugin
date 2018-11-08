<?php

namespace Drupal\hydro_raindrop\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SignupForm.
 */
class SignupForm extends FormBase
{

  // Todo: comment
  private $client;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SignupForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    $this->configFactory = $config_factory;

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
    $this->client = new \Adrenth\Raindrop\Client($settings, $tokenStorage, $applicationId);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

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
        'callback' => '::linkAccount',
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

  /**
   * Todo: comment
   */
  protected static function linkAccount(array &$form, FormStateInterface $form_state)
  {
    $ajax_response = new AjaxResponse();

    $ajax_response->addCommand(
      new InvokeCommand('#edit-hydro-username', 'attr', ['readonly', true])
    );

    $ajax_response->addCommand(
      new InvokeCommand('#edit-link-account', 'attr', ['disabled', 'disabled'])
    );

    $this->register($form_state->getValue('hydro_username'));

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
      new InvokeCommand('#edit-submit', 'attr', ['style', 'margin-left: 0'])
    );

    return $ajax_response;
  }

  /**
   * Register a user by Hydro ID.
   *
   * @return string
   *   Return Todo: comment
   */
  public function register(string $hydroId) {
    $this->client->registerUser($hydroId);

    // return [
    //   '#type' => 'markup',
    //   '#markup' => $this->t('Implement method: register')
    // ];
  }
  /**
   * Verify Hydro user.
   *
   * @return string
   *   Return Todo: comment
   */
  public function verify(string $hydroId) {
    // Generate 6 digit message
    $message = $client->generateMessage();

    // Verify signature
    $client->verifySignature($hydroId, $message);

    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: verify')
    ];
  }

}
