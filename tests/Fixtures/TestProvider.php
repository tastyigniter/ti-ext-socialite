<?php

declare(strict_types=1);

namespace Igniter\Socialite\Tests\Fixtures;

use Override;
use Igniter\Admin\Widgets\Form;
use Igniter\Socialite\Classes\BaseProvider;

class TestProvider extends BaseProvider
{
    #[Override]
    public function extendSettingsForm(Form $form) {}

    #[Override]
    public function redirectToProvider() {}

    #[Override]
    public function handleProviderCallback() {}
}
