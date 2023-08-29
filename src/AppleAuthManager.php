<?php

namespace Drupal\social_auth_apple;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\social_auth\AuthManager\OAuth2Manager;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\social_auth\User\SocialAuthUser;
use Drupal\social_auth\User\SocialAuthUserInterface;

/**
 * Contains all the logic for Apple OAuth2 authentication.
 */
class AppleAuthManager extends OAuth2Manager
{
    /**
     * Constructor.
     *
     */
    public function __construct(
        ConfigFactory $configFactory,
        LoggerChannelFactoryInterface $logger_factory,
        RequestStack $request_stack
    ) {

        parent::__construct(
            $configFactory->get('social_auth_apple.settings'),
            $logger_factory,
            $this->request = $request_stack->getCurrentRequest()
        );
    }

    /**
     * {@inheritdoc}
     *
     * Once the user authorizes the app, the provider returns an authorization
     * code. This method exchanges this code for a token.
     */
    public function authenticate(): void
    {
        try {
            $this->setAccessToken(
                $this->client->getAccessToken(
                    'authorization_code',
                    ['code' => $this->request->get('code')]
                )
            );
        } catch (IdentityProviderException $e) {
            $this->loggerFactory->get('social_auth_apple')
                ->error('There was an error during authentication. Exception: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     *
     * Requests the endpoint that would return the basic user information. The
     * library we're using (The League) abstracts us from this implementation by
     * providing the method getResourceOwner.
     */
    public function getUserInfo(): SocialAuthUserInterface
    {
        if (!$this->user) {
            $owner = $this->client->getResourceOwner($this->getAccessToken());
            $this->user = new SocialAuthUser(
                $owner->getName(),
                $owner->getId(),
                $this->getAccessToken(),
                $owner->getEmail(),
                $owner->getAvatar(),
                $this->getExtraDetails()
            );
            $this->user->setFirstName($owner->getFirstName());
            $this->user->setLastName($owner->getLastName());
        }

        return $this->user;
    }

    /**
     * {@inheritdoc}
     *
     * Generates the authentication URL the user will be redirected to for
     * authentication. This URL often includes the scopes we want to request from
     * the provider.
     */
    public function getAuthorizationUrl(): string
    {
        $scopes = [
            'name',
            'email',
        ];

        $extra_scopes = $this->getScopes();
        if ($extra_scopes) {
            $scopes = array_merge($scopes, explode('+', $extra_scopes));
        }

        // Returns the URL where user will be redirected.
        return $this->client->getAuthorizationUrl(
            [
                'scope' => $scopes,
            ]
        );
    }

    /**
     * GetScopeSeparator.
     */
    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * {@inheritdoc}
     *
     * This method allows the implementer to provide an interface for requesting
     * a resource from the provider. It is helpful for external modules that
     * want to request more data and is also used when extra endpoints are
     * configured in the settings form.
     *
     * @see \Drupal\social_auth\AuthManager\OAuth2Manager::getExtraDetails
     */
    public function requestEndPoint(string $method, string $path, ?string $domain = null, array $options = [])
    {
        if (!$domain) {
            $domain = 'https://appleid.apple.com';
        }

        $url = $domain . $path;

        $request = $this->client->getAuthenticatedRequest($method, $url, $this->getAccessToken(), $options);

        try {
            return $this->client->getParsedResponse($request);
        } catch (IdentityProviderException $e) {
            $this->loggerFactory->get('social_auth_apple')
                ->error('There was an error when requesting ' . $url . '. Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): string
    {
        return $this->client->getState();
    }
}
