<?php

namespace App\Providers;

use App\Services\MoodleClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MoodleClient::class, fn() => new MoodleClient(
            rtrim(config('services.moodle.base_url'), '/'),
            config('services.moodle.token')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
