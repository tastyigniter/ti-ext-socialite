<?php

namespace Igniter\Socialite\Components;

use Igniter\Socialite\Classes\ProviderManager;
use System\Classes\BaseComponent;

class Socialite extends BaseComponent
{
    use \Main\Traits\UsesPage;

    public function defineProperties()
    {
        return [
            'errorPage' => [
                'label' => 'The page to redirect to when an error occurred',
                'type' => 'select',
                'default' => 'account'.DIRECTORY_SEPARATOR.'login',
                'options' => [static::class, 'getThemePageOptions'],
                'validationRule' => 'required|regex:/^[a-z0-9\-_\/]+$/i',
            ],
            'successPage' => [
                'label' => 'The page to redirect to when login is successful',
                'type' => 'select',
                'default' => 'account'.DIRECTORY_SEPARATOR.'account',
                'options' => [static::class, 'getThemePageOptions'],
                'validationRule' => 'required|regex:/^[a-z0-9\-_\/]+$/i',
            ],
        ];
    }

    public function onRun()
    {
        $this->page['errorPage'] = $this->controller->pageUrl($this->property('errorPage'));
        $this->page['successPage'] = $this->controller->pageUrl($this->property('successPage'));

        $this->page['socialiteLinks'] = $this->loadLinks();
    }

    protected function loadLinks()
    {
        $result = [];
        $manager = ProviderManager::instance();
        $providers = $manager->listProviders();
        foreach ($providers as $className => $info) {
            $provider = $manager->makeProvider($className, $info);
            if ($provider->isEnabled()) {
                $result[$info['code']] = $provider->makeEntryPointUrl('auth');
            }
        }

        return $result;
    }
}
