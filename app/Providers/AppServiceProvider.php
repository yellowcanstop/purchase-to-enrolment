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
            rtrim(config('moodle.base_url'), '/'),
            config('moodle.token')
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
