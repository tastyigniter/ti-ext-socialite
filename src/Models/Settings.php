<?php

namespace Igniter\Socialite\Models;

use Igniter\Flame\Database\Model;
use Igniter\Socialite\Classes\ProviderManager;

/**
 * Settings Model
 */
class Settings extends Model
{
    public $implement = [\Igniter\System\Actions\SettingsModel::class];

    // A unique code
    public $settingsCode = 'igniter_socialite_settings';

    // Reference to field configuration
    public $settingsFieldsConfig = 'settings';

    public function getProvider($provider)
    {
        $manager = resolve(ProviderManager::class);
        $className = $manager->resolveProvider($provider);

        return $manager->makeProvider($className);
    }
}
