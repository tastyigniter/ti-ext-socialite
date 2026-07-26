<?php

declare(strict_types=1);

namespace Igniter\Socialite\Tests\Subscribers;

use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\ProviderManager;
use Igniter\Socialite\Models\Settings;
use Igniter\Socialite\SocialiteProviders\Apple;
use Igniter\Socialite\SocialiteProviders\Facebook;
use Igniter\Socialite\Subscribers\SettingsSubscriber;
use Igniter\System\Http\Controllers\Extensions;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use RuntimeException;

beforeEach(function(): void {
    $this->subscriber = new SettingsSubscriber;
});

it('subscribes to admin.form.extendFields event', function(): void {
    $events = $this->subscriber->subscribe(Mockery::mock(Dispatcher::class));

    expect($events)->toHaveKey('admin.form.extendFields')
        ->and($events['admin.form.extendFields'])->toBe('extendSettingsFormFields');
});

it('extends settings form fields for socialite providers', function(): void {
    $form = new class extends Form
    {
        public function __construct() {}

        public function getController(): Extensions
        {
            return new Extensions;
        }

        public function addFields(array $fields, string $addToArea = ''): void
        {
            expect($fields)->toHaveKeys([
                'setup',
                'providers[facebook][status]',
                'providers[facebook][client_id]',
                'providers[facebook][client_secret]',
            ]);
        }
    };
    $form->model = new Settings;

    $providerManager = Mockery::mock(ProviderManager::class);
    $providerManager->shouldReceive('listProviders')->andReturn([
        Facebook::class => [
            'code' => 'facebook',
            'label' => 'Facebook',
            'description' => 'Log in with Facebook',
        ],
    ]);
    $providerManager->shouldReceive('makeProvider')->andReturn(new Facebook);
    app()->instance(ProviderManager::class, $providerManager);

    $this->subscriber->extendSettingsFormFields($form);
});

it('does not extend settings form fields for unrelated forms', function(): void {
    $form = new class extends Form
    {
        public function __construct() {}

        public function getController(): Extensions
        {
            return new Extensions;
        }

        public function addFields(array $fields, string $addToArea = ''): void
        {
            throw new RuntimeException('Should not add fields');
        }
    };
    $form->model = Mockery::mock('UnrelatedModel');

    $this->subscriber->extendSettingsFormFields($form);
})->throwsNoExceptions();

it('resets apple client secret expiry when generate secret is enabled', function(): void {
    $provider = Mockery::mock(Apple::class);
    $provider->shouldReceive('makeEntryPointUrl')->with('callback')->andReturn('https://example.test/callback');

    $providerManager = Mockery::mock(ProviderManager::class);
    $providerManager->shouldReceive('resolveProvider')->with('sign-in-with-apple')->andReturn(Apple::class);
    $providerManager->shouldReceive('makeProvider')->andReturn($provider);
    app()->instance(ProviderManager::class, $providerManager);

    $model = Mockery::mock(Settings::class)->makePartial();
    $model->data = [
        'providers' => [
            'sign-in-with-apple' => [
                'generate_secret' => true,
                'client_secret_expiry' => now()->addMonth(),
            ],
        ],
    ];
    $model->shouldReceive('getProvider')->with('sign-in-with-apple')->andReturn($provider);
    $model->shouldReceive('set')->once()->with(Mockery::on(function(array $payload): bool {
        expect($payload['providers']['sign-in-with-apple'])
            ->not->toHaveKey('client_secret_expiry')
            ->and($payload['providers']['sign-in-with-apple']['generate_secret'])->toBeFalse()
            ->and($payload['providers']['sign-in-with-apple']['login'])->toBe(page_url('account.login'))
            ->and($payload['providers']['sign-in-with-apple']['redirect'])->toBe('https://example.test/callback');

        return true;
    }))->andReturnTrue();

    $this->subscriber->resetClientSecretExpiry($model);
});

it('does nothing when apple provider settings are missing', function(): void {
    $model = Mockery::mock(Settings::class)->makePartial();
    $model->data = ['providers' => []];
    $model->shouldNotReceive('set');

    $this->subscriber->resetClientSecretExpiry($model);
});
