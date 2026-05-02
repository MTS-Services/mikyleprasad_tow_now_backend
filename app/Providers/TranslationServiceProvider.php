<?php

namespace App\Providers;

use App\Services\Translation\GoogleTranslationService;
use App\Services\Translation\TranslationOrchestrator;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/translation.php'),
            'translation'
        );

        $this->app->singleton(GoogleTranslationService::class);

        $this->app->singleton(TranslationOrchestrator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            base_path('config/translation.php') => config_path('translation.php'),
        ], 'translation-config');
    }
}
