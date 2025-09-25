<?php

declare(strict_types=1);

namespace Iamgerwin\NovaDependencyContainer;

use Illuminate\Support\ServiceProvider;

class FieldServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (class_exists('Laravel\Nova\Nova')) {
            $this->app->resolving('Laravel\Nova\Nova', function ($nova): void {
                \Laravel\Nova\Nova::serving(function ($event): void {
                    \Laravel\Nova\Nova::script('nova-dependency-container', __DIR__ . '/../dist/js/field.js');
                    \Laravel\Nova\Nova::style('nova-dependency-container', __DIR__ . '/../dist/css/field.css');
                });
            });
        }
    }
}
