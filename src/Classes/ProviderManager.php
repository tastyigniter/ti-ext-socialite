<?php

namespace Igniter\Socialite\Classes;

use Exception;
use Igniter\Flame\Exception\SystemException;
use Igniter\Socialite\Models\Provider;
use Igniter\System\Classes\ExtensionManager;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\CustomerGroup;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as ProviderUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProviderManager
{
    /**
     * @var array An array of provider types.
     */
    protected $providers;

    /**
     * @var array Cache of social provider registration callbacks.
     */
    protected $providerCallbacks = [];

    /**
     * @var array An array of social provider codes and class names.
     */
    protected $providerHints;

    protected $resolveUserTypeCallbacks = [];

    /**
     * Returns a single provider information
     *
     * @return array|null
     */
    public function findProvider($name)
    {
        $name = $this->resolveProvider($name) ?? $name;
        if (!isset($this->providers[$name])) {
            return null;
        }

        return $this->providers[$name];
    }

    /**
     * Returns a class name from a social provide code
     * @return string The class name resolved, or null.
     */
    public function resolveProvider($name)
    {
        if ($this->providers === null) {
            $this->listProviders();
        }

        if (isset($this->providerHints[$name])) {
            return $this->providerHints[$name];
        }

        return null;
    }

    /**
     * Returns a list of registered social providers.
     * @return array Array keys are class names.
     */
    public function listProviders()
    {
        if ($this->providers === null) {
            $this->loadProviders();
        }

        return $this->providers;
    }

    /**
     * Returns a list of social provider links
     */
    public function listProviderLinks()
    {
        return collect($this->listProviders())->mapWithKeys(function($info, $className) {
            $provider = $this->makeProvider($className, $info);
            if ($provider->isEnabled()) {
                return [$info['code'] => $provider->makeEntryPointUrl('auth')];
            }

            return [];
        });
    }

    /**
     * Load the registered social providers
     */
    public function loadProviders()
    {
        $this->providers = [];
        $this->providerHints = [];

        foreach ($this->providerCallbacks as $callback) {
            $callback($this);
        }

        $registeredProviders = resolve(ExtensionManager::class)->getRegistrationMethodValues('registerSocialiteProviders');
        foreach ($registeredProviders as $socialProviders) {
            $this->registerProviders($socialProviders);
        }
    }

    /**
     * Registers the social providers
     */
    public function registerProviders($providers)
    {
        foreach ($providers as $className => $providerInfo) {
            $this->registerProvider($className, $providerInfo);
        }
    }

    /**
     * Registers a single social provider.
     */
    public function registerProvider($className, $providerInfo = null)
    {
        $providerCode = $providerInfo['code'] ?? null;
        if (!$providerCode) {
            $providerCode = Str::getClassId($className);
        }

        $this->providers[$className] = $providerInfo;
        $this->providerHints[$providerCode] = $className;
    }

    /**
     * Manually registers social providers for consideration.
     * Usage:
     * <pre>
     *   ProviderManager::registerCallback(function($manager){
     *       $manager->registerProviders([
     *
     *       ]);
     *   });
     * </pre>
     */
    public function registerCallback(callable $definitions)
    {
        $this->providerCallbacks[] = $definitions;
    }

    /**
     * Makes a social provider object with the supplied configuration
     *
     * @param array|null $providerInfo
     * @return \Igniter\Socialite\Classes\BaseProvider
     * @throws \Igniter\Flame\Exception\SystemException
     */
    public function makeProvider($className, $providerInfo = null)
    {
        if (is_null($providerInfo)) {
            $providerInfo = $this->findProvider($className);
        }

        if (!class_exists($className)) {
            throw new SystemException(sprintf("The socialite provider class name '%s' has not been registered", $className));
        }

        $code = array_get($providerInfo, 'code');

        return new $className($code);
    }

    public function resolveUserType(callable $callback)
    {
        $this->resolveUserTypeCallbacks[] = $callback;
    }

    /**
     * Executes an entry point for registered social providers, defined in routes.php file.
     *
     * @param string $code Social provider code
     * @param string $action auth: redirect to provider or callback: handle response
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function runEntryPoint($code, $action)
    {
        [$successUrl, $errorUrl] = session()->get('igniter_socialite_redirect', ['/', '/login']);
        $successUrl = request()->get('success', $successUrl);
        $errorUrl = request()->get('error', $errorUrl);

        try {
            if (!$providerClassName = $this->resolveProvider($code)) {
                throw new \LogicException("Unknown socialite provider: $providerClassName.");
            }

            $providerClass = $this->makeProvider($providerClassName);

            if ($action === 'auth') {
                session()->put('igniter_socialite_redirect', [$successUrl, $errorUrl]);

                event('igniter.socialite.beforeRedirect', [$providerClass]);

                return $providerClass->redirectToProvider();
            }

            if ($redirect = $this->handleProviderCallback($providerClass, $errorUrl)) {
                return $redirect;
            }

            // Grab the user associated with this provider. Creates or attach one if need be.
            $redirectUrl = $this->completeCallback();

            session()->forget([
                'igniter_socialite_redirect',
                'igniter_socialite_provider',
            ]);

            return $redirectUrl ?: redirect()->to($successUrl);
        } catch (Exception $ex) {
            flash()->error($ex->getMessage());

            return redirect()->to($errorUrl);
        }
    }

    public function completeCallback()
    {
        $providerData = $this->getProviderData();

        if (!$providerData || !isset($providerData['user'])) {
            return;
        }

        $providerUser = $providerData['user'];
        if (is_null($provider = Provider::find($providerData['id']))) {
            return;
        }

        if (event('igniter.socialite.completeCallback', [$providerUser, $provider], true) === true) {
            return;
        }

        $user = $this->createOrUpdateUser($providerUser, $provider);

        $provider->applyUser($user)->save();

        // Support custom login handling
        $result = Event::dispatch('igniter.socialite.beforeLogin', [$providerUser, $user], true);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        Auth::login($user, true);

        Event::dispatch('igniter.socialite.login', [$user], true);
    }

    public function getProviderData()
    {
        return session()->get('igniter_socialite_provider');
    }

    public function setProviderData(array $providerData)
    {
        session()->put('igniter_socialite_provider', (object)$providerData);
    }

    protected function handleProviderCallback($providerClass, $errorUrl)
    {
        try {
            $providerUser = $providerClass->handleProviderCallback();

            $provider = Provider::firstOrNew([
                'user_type' => $this->resolveUserTypeCallback(),
                'provider' => $providerClass->getDriver(),
                'provider_id' => $providerUser->id,
            ]);

            $provider->token = $providerUser->token;
            $provider->save();

            $this->setProviderData([
                'id' => $provider->getKey(),
                'user' => $providerUser,
            ]);

            if ($providerClass->shouldConfirmEmail($providerUser)) {
                return redirect()->to(page_url('/confirm-email'));
            }
        } catch (Exception $ex) {
            $providerClass->handleProviderException($ex);

            return redirect()->to($errorUrl);
        }
    }

    protected function createOrUpdateUser(ProviderUser $providerUser, Provider $provider)
    {
        if ($user = Auth::getByCredentials(['email' => $providerUser->getEmail()])) {
            return $user;
        }

        $data = [
            'first_name' => $providerUser->getName() ?? 'blank name',
            'email' => $providerUser->getEmail(),
            // Generate a random password for the new user
            'password' => str_random(),
            // Assign the new user to default group
            'customer_group_id' => optional(CustomerGroup::getDefault())->getKey(),
            'status' => true,
        ];

        if (!$user = Event::dispatch('igniter.socialite.register', [$providerUser, $provider], true)) {
            $user = Auth::getProvider()->register($data, true);
        }

        return $user;
    }

    protected function resolveUserTypeCallback()
    {
        foreach ($this->resolveUserTypeCallbacks as $callback) {
            if ($userType = $callback($this)) {
                return $userType;
            }
        }

        return 'customers';
    }
}
