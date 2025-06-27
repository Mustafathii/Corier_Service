<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\EloquentUserProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // ✅ تسجيل custom user provider
        Auth::provider('active_eloquent', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends EloquentUserProvider {

                public function retrieveByCredentials(array $credentials)
                {
                    if (empty($credentials) ||
                        (count($credentials) === 1 && str_contains($this->firstCredentialKey($credentials), 'password'))) {
                        return null;
                    }

                    $query = $this->newModelQuery();

                    foreach ($credentials as $key => $value) {
                        if (str_contains($key, 'password')) {
                            continue;
                        }
                        $query->where($key, $value);
                    }

                    // ✅ فلترة المستخدمين النشطين فقط
                    return $query->where('is_active', true)->first();
                }

                protected function firstCredentialKey(array $credentials)
                {
                    foreach ($credentials as $key => $value) {
                        return $key;
                    }
                }
            };
        });
    }
}
