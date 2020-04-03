<?php

namespace Drupal\social_auth_apple\Settings;

/**
 * Defines an interface for Social Auth Apple settings.
 */
interface AppleAuthSettingsInterface {

  /**
   * Gets the client ID.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId();

  /**
   * Gets the team ID.
   *
   * @return string
   *   getTeamId.
   */
  public function getTeamId();

  /**
   * Gets the Key File ID.
   *
   * @return string
   *   getKeyFileId.
   */
  public function getKeyFileId();

  /**
   * Gets the Key File Path.
   *
   * @return string
   *   get getKeyFilePath.
   */
  public function getKeyFilePath();

}
