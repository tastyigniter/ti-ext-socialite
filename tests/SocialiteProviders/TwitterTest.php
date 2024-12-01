<?php

namespace Igniter\Socialite\Tests\SocialiteProviders;

use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Models\Settings;
use Igniter\Socialite\SocialiteProviders\Twitter;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;

it('builds Twitter provider with valid config and app', function() {
    $config = ['identifier' => 'test_key', 'secret' => 'test_secret'];
    Settings::set('providers', ['twitter' => $config]);

    Socialite::shouldReceive('extend')->with('twitter', Mockery::on(function($callback) {
        $provider = $callback(app());

        expect($provider)->toBeInstanceOf(\Laravel\Socialite\One\TwitterProvider::class);
        return true;
    }));

    new Twitter('twitter');
});

it('extends settings form with Twitter fields', function() {
    $form = new class extends Form
    {
        public function __construct() {}

        public function addFields(array $fields, $addToArea = null)
        {
            return expect($fields)->toHaveKeys([
                'setup',
                'providers[twitter][status]',
                'providers[twitter][identifier]',
                'providers[twitter][secret]',
            ]);
        }
    };

    (new Twitter('twitter'))->extendSettingsForm($form);
});

it('redirects to Twitter provider', function() {
    Socialite::shouldReceive('extend')->andReturnSelf();
    Socialite::shouldReceive('driver')->with('twitter')->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn('redirect_response');

    $response = (new Twitter('twitter'))->redirectToProvider();

    expect($response)->toBe('redirect_response');
});

it('handles Twitter provider callback and returns user', function() {
    Socialite::shouldReceive('extend')->andReturnSelf();
    Socialite::shouldReceive('driver')->with('twitter')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn('user_instance');

    $user = (new Twitter('twitter'))->handleProviderCallback();

    expect($user)->toBe('user_instance');
});

it('confirms email if twitter provider user has no email', function() {
    $providerUser = Mockery::mock(AbstractUser::class);
    $providerUser->email = '';

    $shouldConfirm = (new Twitter('twitter'))->shouldConfirmEmail($providerUser);

    expect($shouldConfirm)->toBeTrue();
});

it('does not confirm email if twitter provider user has email', function() {
    $providerUser = Mockery::mock(AbstractUser::class);
    $providerUser->email = 'user@example.com';

    $shouldConfirm = (new Twitter('twitter'))->shouldConfirmEmail($providerUser);

    expect($shouldConfirm)->toBeFalse();
});
