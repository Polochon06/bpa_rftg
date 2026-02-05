<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\ToadUserProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        'App\Models\User' => 'App\Policies\UserPolicy',
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Auth::provider('toad', function ($app, array $config) {
            return new ToadUserProvider();
        });
    }
}