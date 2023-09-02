<?php

namespace Drupal\social_auth_apple\Plugin\Network;

use Drupal\Core\Url;
use Drupal\social_api\Plugin\NetworkInterface;
use Drupal\social_api\SocialApiException;
use Drupal\social_auth\Plugin\Network\NetworkBase;
use Drupal\social_auth\Settings\SettingsInterface;
use Drupal\social_auth_apple\Settings\AppleAuthSettings;
use League\OAuth2\Client\Provider\Apple;

/**
 * Defines Social Auth Apple Network Plugin.
 *
 * @Network(
 *   id = "social_auth_apple",
 *   short_name = "apple",
 *   social_network = "Apple",
 *   img_path = "img/apple_logo.svg",
 *   type = "social_auth",
 *   class_name = "League\OAuth2\Client\Provider\Apple",
 *   auth_manager = "\Drupal\social_auth_apple\AppleAuthManager",
 *   routes = {
 *      "redirect": "social_auth.network.redirect",
 *      "callback": "social_auth.network.callback",
 *      "settings_form": "social_auth.network.settings_form",
 *   },
 *   handlers = {
 *      "settings": {
 *          "class": "\Drupal\social_auth_apple\Settings\AppleAuthSettings",
 *          "config_id": "social_auth_apple.settings"
 *      }
 *   }
 * )
 */
class AppleAuth extends NetworkBase implements NetworkInterface
{
    /**
     * {@inheritdoc}
     *
     * Initializes the Apple League Plugin to request Apple Accounts.
     *
     * The returning value of this method is what is returned when an instance of
     * this Network Plugin called the getSdk method.
     *
     * @see \Drupal\social_auth_apple\Controller\AppleAuthController::callback
     * @see \Drupal\social_auth\Controller\OAuth2ControllerBase::processCallback
     */
    public function initSdk() : mixed
    {
        $class_name = '\League\OAuth2\Client\Provider\Apple';
        if (!class_exists($class_name)) {
            throw new SocialApiException(sprintf('The Apple library for PHP League OAuth2 not found. Class: %s.', $class_name));
        }

        /** @var \Drupal\social_auth_apple\Settings\AppleAuthSettings $settings */
        $settings = $this->settings;

        if ($this->validateConfig($settings)) {
            // All these settings are mandatory.
            $league_settings = [
              'clientId' => $settings->getClientId(),
              'teamId' => $settings->getTeamId(),
              'keyFileId' => $settings->getKeyFileId(),
              'keyFilePath' => DRUPAL_ROOT . '/../' . $settings->getKeyFilePath(),
              'redirectUri' => Url::fromRoute('social_auth_apple.callback')->setAbsolute()->toString(),
            ];

            // Proxy configuration data for outward proxy.
            $proxy_config = $this->siteSettings->get('http_client_config');
            if ($proxy_config) {
                $league_settings['proxy'] = !empty($proxy_config['proxy']['http']) ? $proxy_config['proxy']['http'] : null;
            }

            return new Apple($league_settings);
        }

        return false;
    }

    /**
     * Checks that module is configured.
     *
     * @param \Drupal\social_auth_apple\Settings\AppleAuthSettings $settings
     *   The implementer authentication settings.
     *
     * @return bool
     *   True if module is configured.
     *   False otherwise.
     */
    protected function validateConfig(SettingsInterface $settings): bool
    {
        $client_id = $settings->getClientId();
        $team_id = $settings->getTeamId();
        $key_file_id = $settings->getKeyFileId();
        $key_file_path = $settings->getKeyFilePath();
        if (!$client_id || !$team_id || !$key_file_id || !$key_file_path) {
            $this->loggerFactory
              ->get('social_auth_apple')
              ->error('Define Client ID, Team ID, Key File Id and the Key File Path on module settings.');

            return false;
        }

        return true;
    }
}
