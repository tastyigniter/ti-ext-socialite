<?php

namespace Igniter\Socialite\Classes;

use Admin\Models\Customer_groups_model;
use Exception;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Exception\SystemException;
use Igniter\Flame\Support\Str;
use Igniter\Socialite\Models\Provider;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\AbstractUser as ProviderUser;
use Main\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use System\Classes\ExtensionManager;

class ProviderManager
{
    use \Igniter\Flame\Traits\Singleton;

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

    /**
     * @var \System\Classes\ExtensionManager
     */
    protected $extensionManager;

    protected function initialize()
    {
        $this->extensionManager = ExtensionManager::instance();
    }

    /**
     * Returns a single provider information
     *
     * @param $name
     * @return array|null
     */
    public function findProvider($name)
    {
        $name = $this->resolveProvider($name) ?? $name;
        if (!isset($this->providers[$name]))
            return null;

        return $this->providers[$name];
    }

    /**
     * Returns a class name from a social provide code
     * @param $name
     * @return string The class name resolved, or null.
     */
    public function resolveProvider($name)
    {
        if ($this->providers === null)
            $this->listProviders();

        if (isset($this->providerHints[$name]))
            return $this->providerHints[$name];

        return null;
    }

    /**
     * Returns a list of registered social providers.
     * @return array Array keys are class names.
     */
    public function listProviders()
    {
        if ($this->providers === null)
            $this->loadProviders();

        return $this->providers;
    }

    /**
     * Load the registered social providers
     */
    public function loadProviders()
    {
        foreach ($this->providerCallbacks as $callback) {
            $callback($this);
        }

        $registeredProviders = ExtensionManager::instance()->getRegistrationMethodValues('registerSocialiteProviders');
        foreach ($registeredProviders as $extensionCode => $socialProviders) {
            $this->registerProviders($socialProviders);
        }
    }

    /**
     * Registers the social providers
     * @param $providers
     */
    public function registerProviders($providers)
    {
        foreach ($providers as $className => $providerInfo) {
            $this->registerProvider($className, $providerInfo);
        }
    }

    /**
     * Registers a single social provider.
     * @param $className
     * @param null $providerInfo
     */
    public function registerProvider($className, $providerInfo = null)
    {
        $providerCode = $providerInfo['code'] ?? null;
        if (!$providerCode)
            $providerCode = Str::getClassId($className);

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
     * @param callable $definitions
     */
    public function registerCallback(callable $definitions)
    {
        $this->providerCallbacks[] = $definitions;
    }

    /**
     * Makes a social provider object with the supplied configuration
     *
     * @param $className
     * @param array|null $providerInfo
     * @return \Igniter\Socialite\Classes\BaseProvider
     * @throws \Igniter\Flame\Exception\SystemException
     */
    public function makeProvider($className, $providerInfo = null)
    {
        if (is_null($providerInfo))
            $providerInfo = $this->findProvider($className);

        if (!class_exists($className)) {
            throw new SystemException(sprintf("The socialite provider class name '%s' has not been registered", $className));
        }

        $code = array_get($providerInfo, 'code');

        return new $className($code);
    }

    /**
     * Executes an entry point for registered social providers, defined in routes.php file.
     *
     * @param string $code Social provider code
     * @param string $action auth: redirect to provider or callback: handle response
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public static function runEntryPoint($code, $action)
    {
        [$successUrl, $errorUrl] = session()->get('igniter_socialite_redirect', ['/', '/login']);
        $successUrl = request()->get('success', $successUrl);
        $errorUrl = request()->get('error', $errorUrl);

        try {
            $manager = self::instance();

            if (!$providerClassName = $manager->resolveProvider($code))
                throw new ApplicationException("Unknown socialite provider: $providerClassName.");

            $providerClass = $manager->makeProvider($providerClassName);

            if ($action === 'auth') {
                session()->put('igniter_socialite_redirect', [$successUrl, $errorUrl]);

                return $providerClass->redirectToProvider();
            }

            if ($redirect = $manager->handleProviderCallback($providerClass, $errorUrl))
                return $redirect;

            // Grab the user associated with this provider. Creates or attach one if need be.
            return $manager->completeCallback();
        }
        catch (Exception $ex) {
            flash()->error($ex->getMessage());

            return redirect()->to($errorUrl);
        }
    }

    public function completeCallback()
    {
        $sessionProvider = session()->get('igniter_socialite_provider');
        [$successUrl, $errorUrl] = session()->get('igniter_socialite_redirect', ['/', '/login']);

        if (!$sessionProvider || !isset($sessionProvider['user']))
            return;

        $providerUser = $sessionProvider['user'];
        if (is_null($provider = Provider::find($sessionProvider['id'])))
            return;

        if (!$user = Auth::getByCredentials(['email' => $providerUser->email]))
            $user = $this->registerProviderUser($providerUser, $provider);

        $provider->user()->associate($user);

        // Support custom login handling
        $result = Event::fire('igniter.socialite.onLogin', [$providerUser, $user], TRUE);
        if ($result instanceof RedirectResponse)
            return $result;

        Auth::login($user);

        Event::fire('igniter.socialite.login', [$user], TRUE);

        session()->forget([
            'igniter_socialite_redirect',
            'igniter_socialite_provider_id',
        ]);

        return redirect()->to($successUrl);
    }

    protected function handleProviderCallback($providerClass, $errorUrl)
    {
        try {
            $providerUser = $providerClass->handleProviderCallback();

            $provider = Provider::firstOrNew([
                'provider' => $providerClass->getDriver(),
                'provider_id' => $providerUser->id,
            ]);

            $provider->token = $providerUser->token;
            $provider->save();

            session()->put('igniter_socialite_provider', [
                'id' => $provider->getKey(),
                'user' => $providerUser,
            ]);

            if (!strlen($providerUser->email) || $providerClass->shouldConfirmEmail())
                return redirect()->to(page_url('/confirm-email'));
        }
        catch (Exception $ex) {
            $providerClass->handleProviderException($ex);

            return redirect()->to($errorUrl);
        }
    }

    protected function registerProviderUser(ProviderUser $providerUser, Provider $provider)
    {
        // Support custom login handling
        if ($user = Event::fire('igniter.socialite.register', [$providerUser, $provider], TRUE))
            return $user;

        $data = [
            'first_name' => $providerUser->getName(),
            'email' => $providerUser->getEmail(),
            // Generate a random password for the new user
            'password' => str_random(16),
            // Assign the new user to default group
            'customer_group_id' => optional(Customer_groups_model::getDefault())->getKey(),
            'status' => TRUE,
        ];

        return Auth::register($data, TRUE);
    }
}
