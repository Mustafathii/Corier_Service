<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Providers\ActiveUserProvider; 

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies(); // ✅ إضافة هذا السطر

        // ✅ تسجيل الـ custom user provider
        Auth::provider('active_eloquent', function ($app, array $config) {
            return new ActiveUserProvider($app['hash'], $config['model']);
        });
    }
}
