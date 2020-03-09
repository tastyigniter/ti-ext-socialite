<?php

namespace Igniter\Socialite\Classes;

use Auth;
use Event;
use Exception;
use Igniter\Flame\Exception\SystemException;
use Igniter\Socialite\Models\Provider;
use Illuminate\Support\Facades\Request;
use Redirect;
use Session;
use Str;
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
        $redirect = Session::get('igniter_socialite_redirect', ['/', '/login']);
        [$successUrl, $errorUrl] = $redirect;
        $successUrl = Request::get('success', $successUrl);
        $errorUrl = Request::get('error', $errorUrl);

        $manager = self::instance();
        $providerClassName = $manager->resolveProvider($code);
        if (!$providerClassName) {
            flash()->error("Unknown socialite provider: $providerClassName.");

            return Redirect::to($errorUrl);
        }

        $provider = $manager->makeProvider($providerClassName);

        if ($action != 'callback') {
            Session::flash('igniter_socialite_redirect', [$successUrl, $errorUrl]);

            return $provider->redirectToProvider();
        }

        try {
            $providerUser = $provider->handleProviderCallback($provider->getDriver());
        }
        catch (Exception $ex) {
            $provider->handleProviderException($ex);

            return Redirect::to($errorUrl);
        }

        // Grab the user associated with this provider. Creates or attach one if need be.
        $user = Provider::findOrCreateUser($providerUser, $provider->getDriver());

        // Support custom login handling
        $result = Event::fire('igniter.socialite.onLogin', [$providerUser, $user], TRUE);
        if ($result instanceof RedirectResponse)
            return $result;

        Auth::login($user);

        return Redirect::to($successUrl);
    }
}