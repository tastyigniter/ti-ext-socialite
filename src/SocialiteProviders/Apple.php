<?php

declare(strict_types=1);

namespace Igniter\Socialite\SocialiteProviders;

use Carbon\CarbonImmutable;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\BaseProvider;
use Laravel\Socialite\Facades\Socialite;
use Lcobucci\JWT\Configuration as JWTConfiguration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Override;

class Apple extends BaseProvider
{
    protected $provider = SignInWithAppleProvider::class;

    #[Override]
    public function extendSettingsForm(Form $form): void
    {
        $form->addFields([
            'setup' => [
                'type' => 'partial',
                'path' => 'igniter.socialite::apple.info',
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][status]' => [
                'label' => 'Status',
                'type' => 'switch',
                'default' => true,
                'span' => 'left',
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][generate_secret]' => [
                'label' => 'Generate new client secret on save',
                'type' => 'switch',
                'default' => true,
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][client_id]' => [
                'label' => 'App Service ID',
                'type' => 'text',
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][team_id]' => [
                'label' => 'App Team ID',
                'type' => 'text',
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][key_id]' => [
                'label' => 'App Private Key ID',
                'type' => 'text',
                'tab' => 'Sign in with Apple',
            ],
            'providers[sign-in-with-apple][private_key]' => [
                'label' => 'App Private Key Contents',
                'type' => 'textarea',
                'tab' => 'Sign in with Apple',
            ],
        ], 'primary');
    }

    #[Override]
    public function redirectToProvider()
    {
        $this->ensureClientSecret();

        return Socialite::driver($this->driver)->scopes(['name', 'email'])->redirect();
    }

    #[Override]
    public function handleProviderCallback()
    {
        return Socialite::driver($this->driver)->user();
    }

    #[Override]
    public function shouldConfirmEmail($providerUser): bool
    {
        return empty($providerUser->email);
    }

    public function generateClientSecret(): string
    {
        $now = CarbonImmutable::now();
        $config = $this->getSetting();

        $jwtConfig = JWTConfiguration::forSymmetricSigner(
            new Sha256,
            InMemory::plainText(array_get($config, 'private_key')),
        );

        $token = $jwtConfig->builder()
            ->issuedBy(array_get($config, 'team_id'))
            ->issuedAt($now)
            ->expiresAt($now->addHour())
            ->permittedFor('https://appleid.apple.com')
            ->relatedTo(array_get($config, 'client_id'))
            ->withHeader('kid', array_get($config, 'key_id'))
            ->getToken($jwtConfig->signer(), $jwtConfig->signingKey());

        return $token->toString();
    }

    protected function ensureClientSecret(): void
    {
        $providers = $this->settings->get('providers', []);

        $expiryDate = array_get($providers, 'sign-in-with-apple.client_secret_expiry', '');
        $clientSecret = array_get($providers, 'sign-in-with-apple.client_secret', '');

        if (!$expiryDate || !$clientSecret || now()->isAfter($expiryDate)) {
            array_set($providers, 'sign-in-with-apple.client_secret', $this->generateClientSecret());
            array_set($providers, 'sign-in-with-apple.client_secret_expiry', now()->addMonths(5));
            $this->settings->set(['providers' => $providers]);
        }
    }
}
