<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Custom Blade directives for roles and permissions
        Blade::if('role', function ($role) {
            return auth()->check() && auth()->user()->hasRole($role);
        });

        Blade::if('permission', function ($permission) {
            return auth()->check() && auth()->user()->can($permission);
        });

        Blade::if('anyrole', function (...$roles) {
            return auth()->check() && auth()->user()->hasAnyRole($roles);
        });

        Blade::if('anypermission', function (...$permissions) {
            return auth()->check() && auth()->user()->hasAnyPermission($permissions);
        });
    }
}
