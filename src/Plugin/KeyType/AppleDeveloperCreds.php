<?php

namespace Drupal\social_auth_apple\Plugin\KeyType;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a key type for Apple Developer Credentials.
 *
 * @KeyType(
 *   id = "apple_developer_creds
 *   label = @Translation("Apple Developer Credentials"),
 *   description = @Translation("Defines the Key for apple developer credentials."),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   },
 *   multivalue = {
 *      "enabled" = true,
 *      "fields" = {
 *        "key_id" = {
 *          "label" = @Translation("Key ID"),
 *          "description" = @Translation("The Key ID."),
 *          "type" = "text",
 *          "required" = true,
 *          "weight" = 1,
 *        },
 *      },
 *   },
 * )
 */
class AppleDeveloperCreds extends KeyTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getKeyType() {
    return 'apple_developer_creds';
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeLabel() {
    return $this->t('Apple Developer Credentials');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeDescription() {
    return $this->t('Apple Developer Credentials');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeForm(array &$form, FormStateInterface $form_state): array {
    $form['key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key ID'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeFormValidate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    if (empty($values['key_file'])) {
      $form_state->setErrorByName('key_file', $this->t('Key file is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeFormSubmit(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    $file = File::load($values['key_file'][0]);
    $file->setPermanent();
    $file->save();

    $form_state->setValueForElement($form['key_file'], $file->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyTypeConfigForm(array &$form, FormStateInterface $form_state) {
    $form['key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key ID'),
      '#default_value' => $this->config->get('key_id'),
      '#required' => TRUE,
    ];
  }

}