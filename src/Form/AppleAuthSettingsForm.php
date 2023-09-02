<?php

namespace Drupal\social_auth_apple\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\social_auth\Form\SocialAuthSettingsForm;
use Drupal\social_auth\Plugin\Network\NetworkInterface;


/**
 * Settings form for Social Auth Apple.
 */
class AppleAuthSettingsForm extends SocialAuthSettingsForm {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return array_merge(['social_auth_apple.settings'], parent::getEditableConfigNames());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'social_auth_apple_settings';
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NetworkInterface $network = NULL): array {
    /** @var \Drupal\social_auth\Plugin\Network\NetworkInterface $network */
    $network = $this->networkManager->createInstance('social_auth_apple');
    $form = parent::buildForm($form, $form_state, $network);
    $config = $this->config('social_auth_apple.settings');

    $form['network']['apple_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Apple Sign-In settings'),
      '#open' => TRUE,
      '#description' => $this->t(
        'You need to first create an Apple App at <a href="@apple-dev">@apple-dev</a>',
        ['@apple-dev' => 'https://developer.apple.com/']
      ),
    ];

    $form['network']['apple_settings']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here, it is the Service ID'),
    ];

    $form['network']['apple_settings']['team_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Team ID'),
      '#default_value' => $config->get('team_id'),
      '#description' => $this->t('Copy the Team Id here (10 characters top right under the login)'),
    ];

    $form['network']['apple_settings']['key_file_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Key File Id'),
      '#default_value' => $config->get('key_file_id'),
      '#description' => $this->t('Copy key file id here (prefix of the key file'),
    ];

    $form['network']['apple_settings']['key_file_path'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Key File Path'),
      '#default_value' => $config->get('key_file_path'),
      '#description' => $this->t('Path to the key file relative to the website root. (f.ex. oauth/HGNHTBYZB7.p8)'),
    ];

    $form['network']['apple_settings']['authorized_redirect_url'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Authorized redirect URIs'),
      '#description' => $this->t('Copy this value to <em>Authorized redirect URIs</em> field of your Apple Service settings.'),
      '#default_value' => Url::fromRoute('social_auth_apple.callback')
        ->setAbsolute()
        ->toString(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    $this->config('social_auth_apple.settings')
      ->set('client_id', $values['client_id'])
      ->set('team_id', $values['team_id'])
      ->set('key_file_id', $values['key_file_id'])
      ->set('key_file_path', ltrim($values['key_file_path'], '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
