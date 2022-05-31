<?php

namespace Drupal\social_auth_apple\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\Controller\OAuth2ControllerBase;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\User\UserAuthenticator;
use Drupal\social_auth_apple\AppleAuthManager;
use Firebase\JWT\JWT;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

JWT::$leeway = 300;

/**
 * Manages requests to Apple API.
 */
class AppleAuthController extends OAuth2ControllerBase {

  /**
   * Expirable key/value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory;

  /**
   * Session storage options.
   *
   * @var array
   */
  protected array $sessionStorageOptions;

  /**
   * AppleAuthController constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_apple network plugin.
   * @param \Drupal\social_auth\User\UserAuthenticator $user_authenticator
   *   Used to manage user authentication/registration.
   * @param \Drupal\social_auth_apple\AppleAuthManager $apple_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   The Social Auth data handler (used for session management).
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Used to handle metadata for redirection to authentication URL.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   *   Expirable key/value factory.
   */
  public function __construct(
    MessengerInterface $messenger,
    NetworkManager $network_manager,
    UserAuthenticator $user_authenticator,
    AppleAuthManager $apple_manager,
    RequestStack $request,
    SocialAuthDataHandler $data_handler,
    RendererInterface $renderer,
    KeyValueExpirableFactoryInterface $key_value_expirable_factory,
    SessionConfigurationInterface $session_configuration
  ) {

    parent::__construct(
      'Social Auth Apple',
      'social_auth_apple',
      $messenger,
      $network_manager,
      $user_authenticator,
      $apple_manager,
      $request,
      $data_handler,
      $renderer
    );
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->sessionStorageOptions = $session_configuration
      ->getOptions($request->getCurrentRequest()) + [
        // This default should land in core at some point.
        // @see https://www.drupal.org/project/drupal/issues/3150614
        'cookie_samesite' => NULL,
        // This default shoudln't be necessary.
        // @see https://www.drupal.org/project/drupal/issues/289145
        'cookie_path' => '/',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_authenticator'),
      $container->get('social_auth_apple.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler'),
      $container->get('renderer'),
      $container->get('keyvalue.expirable'),
      $container->get('session_configuration'),
    );
  }

  /**
   * Callback function to login user.
   *
   * Most of the providers' API redirects to a callback url. Most work for the
   * callback of an OAuth2 protocol is implemented in processCallback. However,
   * you should adapt this method to the provider's requirements.
   *
   * This method is called in 'social_auth_apple.callback' route.
   *
   * @see social_auth_apple.routing.yml
   * @see \Drupal\social_auth\Controller\OAuth2ControllerBase::processCallback
   *
   * This method is triggered when the path user/login/apple/callback is
   * requested. It calls processCallback which creates an instance of
   * the Network Plugin 'social auth apple'. It later authenticates the user
   * and creates the service to obtain data about the user.
   */
  public function callback() {

    $request = $this->request->getCurrentRequest();

    // Checks if authentication failed, the error can either be part of the
    // query arguments or the POST body.
    if ($request->get('error')) {
      $this->messenger->addError($this->t('You could not be authenticated.'));

      $response = $this->userAuthenticator->dispatchAuthenticationError($request->get('error'));
      if ($response) {
        return $response;
      }

      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\AppleResourceOwner|null $profile */
    $profile = $this->processCallback();

    // If authentication was successful.
    if ($profile !== NULL) {

      // Gets (or not) extra initial data.
      $data = $this->userAuthenticator->checkProviderIsAssociated($profile->getId()) ? NULL : $profile->toArray();

      $redirect = $this->userAuthenticator->authenticateUser(
        $profile->getEmail(),
        $profile->getEmail(),
        $profile->getId(),
        $this->providerManager->getAccessToken(),
        NULL,
        $data
      );
    }
    else {
      $redirect = $this->redirect('user.login');
    }
    if ($this->useCustomSessionForState()) {
      $redirect->headers->removeCookie(
        $this->getStateSessionName(),
        $this->sessionStorageOptions['cookie_path'],
        $this->sessionStorageOptions['cookie_domain']
      );
    }
    return $redirect;
  }

  /**
   * ProcessCallback.
   */
  public function processCallback() {
    try {
      /* @var \League\OAuth2\Client\Provider\AbstractProvider|false $client */
      $client = $this->networkManager->createInstance($this->pluginId)->getSdk();

      // If provider client could not be obtained.
      if (!$client) {
        $this->messenger->addError($this->t('%module not configured properly. Contact site administrator.', ['%module' => $this->module]));

        return NULL;
      }

      if ($this->useCustomSessionForState()) {
        $key_value = $this->getKeyValueStore();
        $state = $key_value->get($this->request->getCurrentRequest()->cookies->get($this->getStateSessionName()));
        $key_value->delete($this->sessionStorageOptions['name']);
      }
      else {
        $state = $this->dataHandler->get('oauth2state');
      }

      $retrievedState = $this->request->getCurrentRequest()->get('state');

      if (empty($retrievedState) || ($retrievedState !== $state)) {
        $this->userAuthenticator->nullifySessionKeys();
        $this->messenger->addError($this->t('Login failed. Invalid OAuth2 state.'));

        return NULL;
      }
      $this->providerManager->setClient($client)->authenticate();

      // Saves access token to session.
      // Work around https://github.com/patrickbussmann/oauth2-apple/issues/26,
      // convert the access token into a regular AccessToken object that can
      // be serialized.
      $access_token = $this->providerManager->getAccessToken();
      $json = Json::encode($access_token);
      $this->dataHandler->set('access_token', new AccessToken(Json::decode($json)));

      // Gets user's info from provider.
      if (!$profile = $this->providerManager->getUserInfo()) {
        $this->messenger->addError($this->t('Login failed, could not load user profile. Contact site administrator.'));
        return NULL;
      }

      return $profile;

    }
    catch (PluginException $exception) {
      $this->messenger->addError($this->t('There has been an error when creating plugin.'));

      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function redirectToProvider() {
    $redirect = parent::redirectToProvider();
    if ($this->useCustomSessionForState()) {
      // Due to Apple not correctly implementing OAuth 2 redirects via GET,
      // storing the state in a session cookie will only work if the session
      // cookie is SameSite=None, which is not best security practice. To
      // mitigate this, we will set a purpose-specific, session-like cookie
      // with SameSite=None, so we may recover the state from storage when
      // Apple sends the user back to Drupal via HTTP POST.
      // @see https://www.drupal.org/project/social_auth_apple/issues/3283058

      $session_id = Crypt::randomBytesBase64();
      $this->getKeyValueStore()
        ->set($session_id, $this->dataHandler->get('oauth2state'));
      $this->dataHandler->set('oauth2state', NULL);
      $redirect->headers->setCookie(new Cookie(
        $this->getStateSessionName(),
        $session_id,
        (new DrupalDateTime())->getTimestamp() + 60*10,
        $this->sessionStorageOptions['cookie_path'],
        $this->sessionStorageOptions['cookie_domain'],
        TRUE,
        TRUE,
        FALSE,
        Cookie::SAMESITE_NONE
      ));
    }
    return $redirect;
  }

  /**
   * Get the cookie name for storing a reference to the state.
   *
   * @return string
   */
  protected function getStateSessionName() {
    return 'Drupal_Apple_' . $this->sessionStorageOptions['name'];
  }

  /**
   * Get the key/value store.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected function getKeyValueStore() {
    return $this->keyValueExpirableFactory->get('social_auth_apple_state');
  }

  /**
   * Whether we must use a custom session cookie for storing state with Apple.
   *
   * @return bool
   */
  protected function useCustomSessionForState() {
    return $this->sessionStorageOptions['cookie_samesite'] !== Cookie::SAMESITE_NONE;
  }

}
