<?php

namespace Igniter\Socialite\Components;

use Igniter\Socialite\Classes\ProviderManager;
use Main\Traits\HasPageOptions;
use System\Classes\BaseComponent;

class Socialite extends BaseComponent
{
    use HasPageOptions;

    public function defineProperties()
    {
        return [
            'errorPage' => [
                'label' => 'The page to redirect to when an error occurred',
                'type' => 'select',
                'default' => 'account/login',
                'options' => [static::class, 'getPageOptions'],
            ],
            'successPage' => [
                'label' => 'The page to redirect to when login is successful',
                'type' => 'select',
                'default' => 'account/account',
                'options' => [static::class, 'getPageOptions'],
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
