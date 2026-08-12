<?php

namespace App\Providers;

use App\Services\Ai\GeminiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class, fn (): GeminiClient => new GeminiClient(
            apiKey: (string) config('services.gemini.key'),
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

        ViewFacade::composer('layouts.sidebar', function (View $view): void {
            $user = auth()->user();
            $sidebarProjects = $user === null
                ? collect()
                : $user->projects()
                    ->withCount([
                        'activities',
                        'activities as completed_activities_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
                    ])
                    ->take(6)
                    ->get();

            $view->with('sidebarProjects', $sidebarProjects);
        });
    }
}
