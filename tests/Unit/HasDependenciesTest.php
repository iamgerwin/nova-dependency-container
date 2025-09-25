<?php

declare(strict_types=1);

use Iamgerwin\NovaDependencyContainer\HasDependencies;
use Iamgerwin\NovaDependencyContainer\Tests\Mocks\MockField;

class TestFieldWithDependencies extends MockField
{
    use HasDependencies;

    public $component = 'test-field';
}

it('can add dependencies to a field using the trait', function () {
    $field = new TestFieldWithDependencies('Test');
    $field->dependsOn('status', 'active');

    expect($field->getDependencies())->toHaveCount(1);
    expect($field->getDependencies()[0])->toBe([
        'field' => 'status',
        'value' => 'active',
    ]);
});

it('can mark dependencies as satisfied', function () {
    $field = new TestFieldWithDependencies('Test');

    expect($field->areDependenciesSatisfied())->toBeFalse();

    $field->satisfyDependencies();

    expect($field->areDependenciesSatisfied())->toBeTrue();
});

it('can chain dependency methods on field with trait', function () {
    $field = new TestFieldWithDependencies('Test');

    $result = $field->dependsOn('status', 'active')
        ->dependsOnNotEmpty('title')
        ->dependsOnNot('type', 'draft');

    expect($result)->toBeInstanceOf(TestFieldWithDependencies::class);
    expect($field->getDependencies())->toHaveCount(3);
});

it('adds meta data for dependencies', function () {
    $field = new TestFieldWithDependencies('Test');
    $field->dependsOn('status', 'active');

    $meta = $field->jsonSerialize()['meta'];
    expect($meta)->toHaveKey('dependencies');
    expect($meta['dependencies'])->toHaveCount(1);
});

it('supports all dependency types in trait', function () {
    $field = new TestFieldWithDependencies('Test');

    $field->dependsOn('field1', 'value1')
        ->dependsOnIn('field2', ['value2', 'value3'])
        ->dependsOnNotIn('field3', ['value4', 'value5'])
        ->dependsOnNot('field4', 'value6')
        ->dependsOnNotEmpty('field5')
        ->dependsOnEmpty('field6')
        ->dependsOnNullOrZero('field7');

    $dependencies = $field->getDependencies();
    expect($dependencies)->toHaveCount(8);
});
