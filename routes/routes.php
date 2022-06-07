<?php

Route::any('igniter/socialite/{provider}/{action}', [
    'as' => 'igniter_socialite_provider',
    'middleware' => ['web'],
    function ($provider, $action) {
        return resolve(\Igniter\Socialite\Classes\ProviderManager::class)->runEntryPoint($provider, $action);
    },
])->where('provider', '[a-zA-Z-]+')->where('action', '[a-zA-Z]+');
