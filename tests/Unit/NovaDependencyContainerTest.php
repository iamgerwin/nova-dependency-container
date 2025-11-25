<?php

declare(strict_types=1);

use Iamgerwin\NovaDependencyContainer\NovaDependencyContainer;
use Iamgerwin\NovaDependencyContainer\Tests\Mocks\MockField;

class Text extends MockField
{
    public $component = 'text-field';
}

it('can create a dependency container with fields', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
        Text::make('Field 2'),
    ]);

    expect($container)->toBeInstanceOf(NovaDependencyContainer::class);
    expect($container->getFields())->toHaveCount(2);
});

it('can create a dependency container with a callable', function () {
    $container = NovaDependencyContainer::make(function () {
        return [
            Text::make('Field 1'),
            Text::make('Field 2'),
        ];
    });

    expect($container)->toBeInstanceOf(NovaDependencyContainer::class);
    expect($container->getFields())->toHaveCount(2);
});

it('can add a single dependency', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOn('status', 'active');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toBe([
        'field' => 'status',
        'value' => 'active',
    ]);
});

it('can add multiple dependencies using dependsOnIn', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnIn('status', ['active', 'pending']);

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(2);
});

it('can add not in dependencies', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnNotIn('status', ['inactive', 'deleted']);

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toHaveKey('notIn');
    expect($dependencies[0]['notIn'])->toBe(['inactive', 'deleted']);
});

it('can add not dependency', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnNot('status', 'inactive');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toHaveKey('not');
    expect($dependencies[0]['not'])->toBe('inactive');
});

it('can add not empty dependency', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnNotEmpty('title');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toHaveKey('notEmpty');
    expect($dependencies[0]['notEmpty'])->toBeTrue();
});

it('can add empty dependency', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnEmpty('title');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toHaveKey('empty');
    expect($dependencies[0]['empty'])->toBeTrue();
});

it('can add null or zero dependency', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOnNullOrZero('count');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
    expect($dependencies[0])->toHaveKey('nullOrZero');
    expect($dependencies[0]['nullOrZero'])->toBeTrue();
});

it('can chain multiple dependency methods', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOn('status', 'active')
        ->dependsOnNotEmpty('title')
        ->dependsOnNot('type', 'draft');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(3);
});

it('does not add duplicate dependencies', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOn('status', 'active')
        ->dependsOn('status', 'active');

    $dependencies = $container->getDependencies();
    expect($dependencies)->toHaveCount(1);
});

it('can apply dependencies to fields', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->applyToFields();

    $meta = $container->jsonSerialize();
    expect($meta['applyToFields'])->toBeTrue();
});

it('serializes to JSON correctly', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ])->dependsOn('status', 'active');

    $json = $container->jsonSerialize();

    expect($json)->toHaveKey('component');
    expect($json)->toHaveKey('fields');
    expect($json)->toHaveKey('dependencies');
    expect($json['component'])->toBe('nova-dependency-container');
    expect($json['fields'])->toHaveCount(1);
    expect($json['dependencies'])->toHaveCount(1);
});

it('does not show on index by default', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ]);

    expect($container->showOnIndex)->toBeFalse();
});

it('does not show on detail by default', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ]);

    expect($container->showOnDetail)->toBeFalse();
});

it('has resolve method signature compatible with Nova Field class', function () {
    $reflection = new ReflectionMethod(NovaDependencyContainer::class, 'resolve');

    // Method should have no return type (matches Nova Field parent class)
    expect($reflection->hasReturnType())->toBeFalse();

    // Method should have two parameters
    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(2);

    // First parameter: $resource (no type hint)
    expect($parameters[0]->getName())->toBe('resource');
    expect($parameters[0]->hasType())->toBeFalse();

    // Second parameter: $attribute (no type hint, has default null)
    expect($parameters[1]->getName())->toBe('attribute');
    expect($parameters[1]->hasType())->toBeFalse();
    expect($parameters[1]->isDefaultValueAvailable())->toBeTrue();
    expect($parameters[1]->getDefaultValue())->toBeNull();
});

it('has resolveForDisplay method signature compatible with Nova Field class', function () {
    $reflection = new ReflectionMethod(NovaDependencyContainer::class, 'resolveForDisplay');

    // Method should have no return type (matches Nova Field parent class)
    expect($reflection->hasReturnType())->toBeFalse();

    // Method should have two parameters
    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(2);

    // First parameter: $resource (no type hint)
    expect($parameters[0]->getName())->toBe('resource');
    expect($parameters[0]->hasType())->toBeFalse();

    // Second parameter: $attribute (no type hint, has default null)
    expect($parameters[1]->getName())->toBe('attribute');
    expect($parameters[1]->hasType())->toBeFalse();
    expect($parameters[1]->isDefaultValueAvailable())->toBeTrue();
    expect($parameters[1]->getDefaultValue())->toBeNull();
});

it('can call resolve method with various argument types', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ]);

    $resource = new stdClass;
    $resource->field_1 = 'test value';

    // Should work with null attribute (default)
    $container->resolve($resource);

    // Should work with string attribute
    $container->resolve($resource, 'field_1');

    // Should work with null explicitly passed
    $container->resolve($resource, null);

    expect(true)->toBeTrue();
});

it('can call resolveForDisplay method with various argument types', function () {
    $container = NovaDependencyContainer::make([
        Text::make('Field 1'),
    ]);

    $resource = new stdClass;
    $resource->field_1 = 'test value';

    // Should work with null attribute (default)
    $container->resolveForDisplay($resource);

    // Should work with string attribute
    $container->resolveForDisplay($resource, 'field_1');

    // Should work with null explicitly passed
    $container->resolveForDisplay($resource, null);

    expect(true)->toBeTrue();
});
