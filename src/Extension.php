<?php

declare(strict_types=1);

namespace Igniter\Socialite;

use Igniter\Socialite\Classes\ProviderManager;
use Igniter\Socialite\Models\Settings;
use Igniter\Socialite\SocialiteProviders\Apple;
use Igniter\Socialite\SocialiteProviders\Facebook;
use Igniter\Socialite\SocialiteProviders\Google;
use Igniter\Socialite\SocialiteProviders\Twitter;
use Igniter\Socialite\Subscribers\SettingsSubscriber;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Override;

/**
 * Socialite Extension Information File
 */
class Extension extends BaseExtension
{
    protected $subscribe = [
        SettingsSubscriber::class,
    ];

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(ProviderManager::class);

        $this->app->register(SocialiteServiceProvider::class);
        AliasLoader::getInstance()->alias('Socialite', Socialite::class);
    }

    #[Override]
    public function boot(): void
    {
        VerifyCsrfToken::except([
            'igniter/socialite/sign-in-with-apple/callback',
        ]);
    }

    #[Override]
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'Configure Social Login Providers',
                'description' => 'Configure social login providers with API credentials.',
                'icon' => 'fa fa-share-nodes',
                'model' => Settings::class,
                'priority' => 700,
                'permissions' => ['Igniter.Socialite.Manage'],
            ],
        ];
    }

    #[Override]
    public function registerPermissions(): array
    {
        return [
            'Igniter.Socialite.Manage' => [
                'label' => 'igniter.socialite::default.help_permission',
                'group' => 'igniter::admin.permissions.name',
            ],
        ];
    }

    public function registerSocialiteProviders(): array
    {
        return [
            Facebook::class => [
                'code' => 'facebook',
                'label' => 'Facebook',
                'description' => 'Log in with Facebook',
            ],
            Google::class => [
                'code' => 'google',
                'label' => 'Google',
                'description' => 'Log in with Google',
            ],
            Twitter::class => [
                'code' => 'twitter',
                'label' => 'Twitter',
                'description' => 'Log in with Twitter',
            ],
            Apple::class => [
                'code' => 'sign-in-with-apple',
                'label' => 'Sign in with Apple',
                'description' => 'Sign in with Apple',
            ],
        ];
    }
}
