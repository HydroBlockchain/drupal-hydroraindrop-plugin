<?php

namespace Drupal\hydro_raindrop\EventSubscriber;

use Adrenth\Raindrop\Exception\UnableToAcquireAccessToken;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hydro_raindrop\TokenStorage\PrivateTempStoreStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class RequireMFASubscriber.
 */
class RequireMFASubscriber implements EventSubscriberInterface {

  /**
   * The request exception boolean.
   */
  protected $requestException;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var RequestStack
   */
  protected $requestStack;
  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new RequireMFASubscriber object.
   */
  public function __construct(RequestStack $request_stack, AccountProxyInterface $current_user) {
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
  }

  /**
   * Check MFA requirement for current request.
   *
   * @param GetResponseEvent $event
   *   The event response.
   * @param Request $request
   *   The HTTP request.
   *
   * @return boolean
   *   Return FALSE if MFA not required. TRUE otherwise.
   */
  private function checkMFA($event, $request) {
    // Check for user session.
    if ($this->currentUser->isAnonymous()) {
      // Don't redirect if not authenticated.
      return FALSE;
    }

    $route_name = $request->get('_route');

    // Check for paths we don't want to redirect.
    $checks = [
      // Check if we're already there.
      ($route_name == 'hydro_raindrop.auth'),
      // Let the user logout.
      ($route_name == 'user.logout'),
    ];

    // Load the Token Storage.
    $tempstore = \Drupal::service('user.private_tempstore')->get('hydro_raindrop');
    $tokenStorage = new PrivateTempStoreStorage($tempstore);

    foreach ($checks as $check) {
      if ($check) {
        // Unset access token and prevent redirect for the checks above.
        $tokenStorage->unsetAccessToken();
        return FALSE;
      }
    }

    // Check if the user has a token set.
    try {
      $tokenStorage->getAccessToken();
      return FALSE;
    } catch (UnableToAcquireAccessToken $e) {
      // Redirect the authenticated user to MFA.
      return TRUE;
    }
  }

  /**
   * Prepare redirect response.
   *
   * @param GetResponseEvent $event
   *   The event response.
   *
   * @return string|null
   *   Redirect URL including query string.
   */
  private function prepareMFARedirect($event) {
    $request = $this->requestStack->getCurrentRequest();
    $destination = $request->getRequestUri();

    if ($this->checkMFA($event, $request)) {
      return Url::fromRoute('hydro_raindrop.auth', ['destination' => $destination])->toString();
    }
    return NULL;
  }

  /**
   * MFA redirect on KernelEvents::EXCEPTION.
   *
   * @param GetResponseEvent $event
   *   The event response.
   */
  public function exceptionRedirect(GetResponseEvent $event) {

    // Boolean to indicate request exception. Prevents additional login
    // requirement checks on KernelEvents::REQUEST which could cause
    // infinite loop redirects on protected pages.
    $this->requestException = TRUE;

    if ($redirect = $this->prepareMFARedirect($event)) {
      $response = new RedirectResponse($redirect);
      $event->setResponse($response);
    }
  }

  /**
   * MFA redirect on KernelEvents::REQUEST.
   *
   * @param GetResponseEvent $event
   *   The event response.
   */
  public function requestRedirect(GetResponseEvent $event) {
    if (!$this->requestException && ($redirect = $this->prepareMFARedirect($event))) {
      $response = new RedirectResponse($redirect);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['exceptionRedirect'];
    $events[KernelEvents::REQUEST][] = ['requestRedirect'];
    return $events;
  }

}
