<?php

namespace Igniter\Socialite\Models;

use Igniter\Socialite\Classes\ProviderManager;
use Model;

/**
 * Settings Model
 */
class Settings extends Model
{
    public $implement = ['System\Actions\SettingsModel'];

    // A unique code
    public $settingsCode = 'igniter_socialite_settings';

    // Reference to field configuration
    public $settingsFieldsConfig = 'settings';

    public function getProvider($provider)
    {
        $manager = ProviderManager::instance();
        $className = $manager->resolveProvider($provider);
        return $manager->makeProvider($className);
    }
}
