<?php

namespace Igniter\Socialite\Models;

use Auth;
use Event;
use Igniter\Flame\Auth\Models\User;
use Laravel\Socialite\AbstractUser as ProviderUser;
use Model;

/**
 * Provider Model
 */
class Provider extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'igniter_socialite_providers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable fields
     */
    protected $fillable = ['user_id', 'provider', 'provider_id', 'token'];

    /**
     * @var array Relations
     */
    public $relation = [
        'belongsTo' => [
            'user' => ['Admin\Models\Customers_model']
        ],
    ];

    /**
     * If a user has registered before using social auth, return the user
     * else, create a new user object.
     *
     * @param \Laravel\Socialite\AbstractUser $providerUser
     * @return mixed
     */
    public static function findOrCreateUser($providerUser, $providerName)
    {
        $provider = self::firstOrNew(['provider_id' => $providerUser->id]);

        $provider->provider = $providerName;
        $provider->token = $providerUser->token;

        if ($provider->exists AND $provider->user) {
            $provider->save();
            return $provider->user;
        }

        if ($user = Auth::getByCredentials(['email' => $providerUser->email]))
            return self::attachProvider($user, $provider);

        $user = self::registerUser($providerUser, $provider);

        // The user may have been deleted. Make sure this isn't the case
        if (!$provider->user) {
            $provider->delete();
            return self::findOrCreateUser($providerUser, $provider);
        }

        return $user;
    }

    protected static function attachProvider(User $user, Provider $provider)
    {
        $provider->user_id = $user->getKey();
        $provider->save();

        return $user;
    }

    protected static function registerUser(ProviderUser $providerUser, Provider $provider)
    {
        // Support custom login handling
        if ($user = Event::fire('igniter.socialite.register', [$providerUser, $provider], TRUE))
            return $user;

        $data = [
            'first_name' => $providerUser->getName(),
            'email' => $providerUser->getEmail(),
            // Generate a random password for the new user
            'password' => str_random(16),
        ];

        $user = Auth::register($data);

        return self::attachProvider($user, $provider);
    }
}
