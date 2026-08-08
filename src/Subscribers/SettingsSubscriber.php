<?php

declare(strict_types=1);

namespace Igniter\Socialite\Subscribers;

use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\ProviderManager;
use Igniter\Socialite\Models\Settings;
use Igniter\System\Http\Controllers\Extensions;
use Illuminate\Contracts\Events\Dispatcher;

class SettingsSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        Settings::extend(function(Settings $model): void {
            $model->bindEvent('model.afterSave', function() use ($model): void {
                $this->resetClientSecretExpiry($model);
            });
        });

        return [
            'admin.form.extendFields' => 'extendSettingsFormFields',
        ];
    }

    public function extendSettingsFormFields(Form $form): void
    {
        if (
            $form->getController() instanceof Extensions
            && $form->model instanceof Settings
        ) {
            $manager = resolve(ProviderManager::class);
            foreach ($manager->listProviders() as $class => $details) {
                $provider = $manager->makeProvider($class, $details);
                $provider->extendSettingsForm($form);
            }
        }
    }

    public function resetClientSecretExpiry(Settings $model): void
    {
        $providers = $model->data['providers'] ?? [];

        if (!array_get($providers, 'sign-in-with-apple')) {
            return;
        }

        if (array_get($providers, 'sign-in-with-apple.generate_secret', false)) {
            array_forget($providers, 'sign-in-with-apple.client_secret_expiry');
            array_set($providers, 'sign-in-with-apple.generate_secret', false);
        }

        if ((string)array_get($providers, 'sign-in-with-apple.login') === '') {
            array_set($providers, 'sign-in-with-apple.login', page_url('account.login'));
        }

        if ((string)array_get($providers, 'sign-in-with-apple.redirect') === '') {
            array_set($providers, 'sign-in-with-apple.redirect', $model->getProvider('sign-in-with-apple')->makeEntryPointUrl('callback'));
        }

        $model->set(['providers' => $providers]);
    }
}
