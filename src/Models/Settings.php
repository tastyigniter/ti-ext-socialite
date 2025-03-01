<?php

declare(strict_types=1);

namespace Igniter\Socialite\Models;

use Igniter\System\Actions\SettingsModel;
use Igniter\Flame\Database\Model;
use Igniter\Socialite\Classes\ProviderManager;

/**
 * Settings Model
 */
class Settings extends Model
{
    public array $implement = [SettingsModel::class];

    // A unique code
    public string $settingsCode = 'igniter_socialite_settings';

    // Reference to field configuration
    public string $settingsFieldsConfig = 'settings';

    public function getProvider($provider)
    {
        $manager = resolve(ProviderManager::class);
        $className = $manager->resolveProvider($provider);

        return $manager->makeProvider($className);
    }
}
