<?php

declare(strict_types=1);

namespace Iamgerwin\NovaDependencyContainer\Tests\Mocks;

class MockField
{
    public $component = 'test-field';

    public string $name;

    public $attribute;

    public $value;

    protected array $meta = [];

    public function __construct(string $name, ?string $attribute = null)
    {
        $this->name = $name;
        $this->attribute = $attribute ?? strtolower(str_replace(' ', '_', $name));
    }

    public static function make(string $name, ?string $attribute = null): self
    {
        return new static($name, $attribute);
    }

    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge([
            'component' => $this->component,
            'attribute' => $this->attribute,
            'name' => $this->name,
            'value' => $this->value,
        ], ['meta' => $this->meta]);
    }

    public function fill($request, $model)
    {
        return function () {};
    }

    public function resolve($resource, ?string $attribute = null): void {}

    public function resolveForDisplay($resource, ?string $attribute = null): void {}
}
