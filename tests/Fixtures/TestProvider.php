<?php

namespace Igniter\Socialite\Tests\Fixtures;

use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\BaseProvider;

class TestProvider extends BaseProvider
{
    public function extendSettingsForm(Form $form) {}

    public function redirectToProvider() {}

    public function handleProviderCallback() {}
}
