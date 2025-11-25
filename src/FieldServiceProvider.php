<?php

declare(strict_types=1);

namespace Iamgerwin\NovaDependencyContainer;

use Illuminate\Support\ServiceProvider;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only register Nova assets when Nova is available
        if (! class_exists(\Laravel\Nova\Nova::class)) {
            return;
        }

        \Laravel\Nova\Nova::serving(function (mixed $event): void {
            \Laravel\Nova\Nova::script('nova-dependency-container', __DIR__ . '/../dist/js/field.js');
            \Laravel\Nova\Nova::style('nova-dependency-container', __DIR__ . '/../dist/css/field.css');
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
