<?php

namespace Drupal\social_auth_apple\Settings;

use Drupal\social_auth\Settings\SettingsInterface;

/**
 * Defines an interface for Social Auth Apple settings.
 */
interface AppleAuthSettingsInterface extends SettingsInterface
{
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
