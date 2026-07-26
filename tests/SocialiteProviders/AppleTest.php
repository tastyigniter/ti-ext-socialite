<?php

declare(strict_types=1);

namespace Igniter\Socialite\Tests\SocialiteProviders;

use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Models\Settings;
use Igniter\Socialite\SocialiteProviders\Apple;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use ReflectionClass;

function appleWithSettings(Settings $settings): Apple
{
    $reflection = new ReflectionClass(Apple::class);
    /** @var Apple $apple */
    $apple = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('settings')->setValue($apple, $settings);
    $reflection->getProperty('driver')->setValue($apple, 'sign-in-with-apple');

    return $apple;
}

function invokeEnsureClientSecret(Apple $apple): void
{
    (new ReflectionClass($apple))->getMethod('ensureClientSecret')->invoke($apple);
}

it('extends settings form with Apple fields', function(): void {
    $form = new class extends Form
    {
        public function __construct() {}

        public function addFields(array $fields, string $addToArea = ''): void
        {
            expect($fields)->toHaveKeys([
                'setup',
                'providers[sign-in-with-apple][status]',
                'providers[sign-in-with-apple][generate_secret]',
                'providers[sign-in-with-apple][client_id]',
                'providers[sign-in-with-apple][team_id]',
                'providers[sign-in-with-apple][key_id]',
                'providers[sign-in-with-apple][private_key]',
            ]);
        }
    };

    (new Apple('sign-in-with-apple'))->extendSettingsForm($form);
});

it('redirects to Apple provider with name and email scopes', function(): void {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('providers', [])->andReturn([
        'sign-in-with-apple' => [
            'client_secret' => 'valid-secret',
            'client_secret_expiry' => now()->addMonth(),
        ],
    ]);
    $settings->shouldNotReceive('set');

    $apple = appleWithSettings($settings);

    Socialite::shouldReceive('driver')->with('sign-in-with-apple')->andReturnSelf();
    Socialite::shouldReceive('scopes')->with(['name', 'email'])->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn('redirect_response');

    $response = $apple->redirectToProvider();

    expect($response)->toBe('redirect_response');
});

it('regenerates client secret when expired before redirecting to Apple', function(): void {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('providers', [])->andReturn([
        'sign-in-with-apple' => [
            'team_id' => 'TEAM123',
            'key_id' => 'KEY123',
            'client_id' => 'com.example.service',
            'private_key' => file_get_contents(__DIR__.'/../Fixtures/apple-private-key.pem'),
            'client_secret' => 'expired-secret',
            'client_secret_expiry' => now()->subDay(),
        ],
    ]);
    $settings->shouldReceive('set')->once()->with(Mockery::on(function(array $payload): bool {
        expect($payload['providers']['sign-in-with-apple']['client_secret'])
            ->not->toBe('expired-secret')
            ->and($payload['providers']['sign-in-with-apple']['client_secret_expiry'])->toBeGreaterThan(now());

        return true;
    }))->andReturnTrue();

    invokeEnsureClientSecret(appleWithSettings($settings));
});

it('does not regenerate client secret when still valid', function(): void {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('providers', [])->andReturn([
        'sign-in-with-apple' => [
            'client_secret' => 'valid-secret',
            'client_secret_expiry' => now()->addMonth(),
        ],
    ]);
    $settings->shouldNotReceive('set');

    invokeEnsureClientSecret(appleWithSettings($settings));
});

it('handles Apple provider callback and returns user', function(): void {
    Socialite::shouldReceive('extend')->andReturnSelf();
    Socialite::shouldReceive('driver')->with('sign-in-with-apple')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn('user_instance');

    $user = (new Apple('sign-in-with-apple'))->handleProviderCallback();

    expect($user)->toBe('user_instance');
});

it('confirms email if apple provider user has no email', function(): void {
    $providerUser = Mockery::mock(AbstractUser::class);
    $providerUser->email = null;

    $shouldConfirm = (new Apple('sign-in-with-apple'))->shouldConfirmEmail($providerUser);

    expect($shouldConfirm)->toBeTrue();
});

it('does not confirm email if apple provider user has email', function(): void {
    $providerUser = Mockery::mock(AbstractUser::class);
    $providerUser->email = 'user@example.com';

    $shouldConfirm = (new Apple('sign-in-with-apple'))->shouldConfirmEmail($providerUser);

    expect($shouldConfirm)->toBeFalse();
});

it('generates a client secret jwt from apple credentials', function(): void {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('providers', [])->andReturn([
        'sign-in-with-apple' => [
            'team_id' => 'TEAM123',
            'key_id' => 'KEY123',
            'client_id' => 'com.example.service',
            'private_key' => file_get_contents(__DIR__.'/../Fixtures/apple-private-key.pem'),
        ],
    ]);

    $secret = appleWithSettings($settings)->generateClientSecret();

    expect($secret)->toMatch('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/');
});
