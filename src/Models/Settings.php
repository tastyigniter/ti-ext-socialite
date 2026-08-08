<?php

declare(strict_types=1);

namespace Igniter\Socialite\Models;

use Igniter\Flame\Database\Model;
use Igniter\Socialite\Classes\ProviderManager;
use Igniter\System\Actions\SettingsModel;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string|array $key, mixed $value = null)
 * @mixin SettingsModel
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
