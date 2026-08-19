<?php

namespace App\Providers;

use App\Services\MoodleClient;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fail loudly on missing config. Without this a null or blank value
        // lands on MoodleClient's string type hints, and since the client is
        // resolved inside a queued job that surfaces as a TypeError buried in
        // failed_jobs rather than as an obvious misconfiguration.
        // filled() rather than ??, because `MOODLE_TOKEN=` in .env reads as ''.
        $this->app->singleton(MoodleClient::class, function () {
            $required = fn(string $key) => filled($value = config("moodle.{$key}"))
                ? $value
                : throw new RuntimeException(
                    'MOODLE_' . strtoupper($key) . ' is not set. Copy it from .env.example and fill it in.'
                );

            return new MoodleClient(
                rtrim($required('base_url'), '/'),
                $required('token'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
