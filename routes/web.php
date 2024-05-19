<?php

Route::group([
    'prefix' => 'igniter/socialite',
    'middleware' => ['web'],
], function() {
    Route::any('{provider}/{action}', function($provider, $action) {
        return \Igniter\Socialite\Classes\ProviderManager::runEntryPoint($provider, $action);
    })->name('igniter_socialite_provider')->where('provider', '[a-zA-Z-]+')->where('action', '[a-zA-Z]+');
});
