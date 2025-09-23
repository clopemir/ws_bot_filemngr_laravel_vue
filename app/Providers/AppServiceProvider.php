<?php

namespace App\Providers;

use URL;
use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

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
        Inertia::share([
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'error' => session('error'),
                ];
            },
        ]);

        LogViewer::auth(function ($request) {
            //return $request->user() && $request->user()->can('view logs');
            return true; // For simplicity, we allow all authenticated users to view logs
        });

        //forzar https
        app('url')->forceScheme('https');
    }
}
