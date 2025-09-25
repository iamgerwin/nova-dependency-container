<?php

declare(strict_types=1);

use Iamgerwin\NovaDependencyContainer\FieldServiceProvider;

it('registers the service provider', function () {
    $providers = app()->getLoadedProviders();
    expect($providers)->toHaveKey(FieldServiceProvider::class);
});