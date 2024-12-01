<?php

namespace Igniter\Socialite\Tests\Classes;

use Exception;
use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\BaseProvider;
use Igniter\Socialite\Models\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;

beforeEach(function() {
    $this->provider = new class('driver') extends BaseProvider
    {
        public function extendSettingsForm(Form $form)
        {
            return null;
        }

        public function redirectToProvider()
        {
            return new RedirectResponse('http://redirect.url');
        }

        public function handleProviderCallback()
        {
            return Mockery::mock(AbstractUser::class);
        }
    };
});

it('initializes settings and extends socialite driver', function() {
    $provider = Mockery::mock(BaseProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('getSetting')->andReturn(['client_id' => 'id', 'client_secret' => 'secret'])->once();
    $provider->shouldReceive('makeEntryPointUrl')->with('callback')->andReturn('http://callback.url')->once();
    $provider->shouldReceive('buildProvider')->andReturn('provider_instance')->once();

    Socialite::shouldReceive('extend')->with('driver', Mockery::on(function($callback) use ($provider) {
        return $callback(app()) === 'provider_instance';
    }));

    $provider->__construct('driver');
});

it('returns correct driver', function() {
    expect($this->provider->getDriver())->toBe('driver');
});

it('returns correct setting value', function() {
    Settings::set('providers', ['driver' => ['key' => 'value']]);

    expect($this->provider->getSetting('key'))->toBe('value');
});

it('returns correct entry point URL', function() {
    URL::shouldReceive('route')->with('igniter_socialite_provider', Mockery::any(), true)->andReturn('http://callback.url');

    $provider = Mockery::mock(BaseProvider::class)->makePartial();
    $provider->shouldReceive('makeEntryPointUrl')->passthru();

    expect($provider->makeEntryPointUrl('callback'))->toBe('http://callback.url');
});

it('returns true when provider is enabled', function() {
    Settings::set('providers', ['driver' => ['status' => 1]]);

    expect($this->provider->isEnabled())->toBeTrue();
});

it('handles provider exception correctly', function() {
    $provider = Mockery::mock(BaseProvider::class)->makePartial();
    $provider->shouldReceive('handleProviderException')->passthru();

    Log::shouldReceive('error')->once();

    $exception = new Exception('Test Exception');
    $provider->handleProviderException($exception);

    expect(flash()->messages()->first())->level->toBe('danger')->message->not->toBeEmpty();

    $exception = new InvalidStateException('Test Exception');
    $provider->handleProviderException($exception);

    expect(flash()->messages()->first())->level->toBe('danger')->message->not->toBeEmpty();
});

it('extends config correctly', function() {
    $callback = function($config, $provider) {
        $config['extra'] = 'value';
        return $config;
    };

    BaseProvider::extendConfig($callback);

    $provider = Mockery::mock(BaseProvider::class)->makePartial()->shouldAllowMockingProtectedMethods();
    Socialite::shouldReceive('extend')->with('driver', Mockery::on(function($callback) use ($provider) {
        return $callback(app()) === 'provider_instance';
    }));

    Socialite::shouldReceive('buildProvider')->with(Mockery::any(), Mockery::on(function($config) {
        return $config['extra'] === 'value';
    }))->andReturn('provider_instance')->once();

    $provider->__construct('driver');
});
