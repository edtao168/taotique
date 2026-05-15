<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 自動將 acl.php 定義的權限映射到 Laravel Gate
		$permissions = array_unique(array_merge(...array_values(config('acl.roles', []))));

		foreach ($permissions as $permission) {
			\Illuminate\Support\Facades\Gate::define($permission, function ($user) use ($permission) {
				return $user->hasAbility($permission);
			});
		}
    }
}
