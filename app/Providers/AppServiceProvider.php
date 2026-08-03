<?php

namespace App\Providers;

use App\Services\Ai\GeminiClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class, fn (): GeminiClient => new GeminiClient(
            apiKey: (string) config('services.gemini.key'),
            model: (string) config('services.gemini.model'),
            baseUrl: (string) config('services.gemini.base_url'),
            timeout: (int) config('services.gemini.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Vite::prefetch(concurrency: 3);
    }
}
