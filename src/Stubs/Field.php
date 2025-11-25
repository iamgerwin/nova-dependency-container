<?php

declare(strict_types=1);

namespace Iamgerwin\NovaDependencyContainer\Stubs;

abstract class Field
{
    public $component;

    public $showOnIndex = true;

    public $showOnDetail = true;

    public $name;

    public $attribute;

    public $value;

    public $panel;

    protected array $meta = [];

    public function __construct($name, $attribute = null, $resolveCallback = null)
    {
        $this->name = $name;
        $this->attribute = $attribute ?? str_replace(' ', '_', strtolower($name));
    }

    public static function make(...$arguments)
    {
        return new static(...$arguments);
    }

    public function withMeta(array $meta)
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function component()
    {
        return $this->component;
    }

    public function meta()
    {
        return $this->meta;
    }

    public function jsonSerialize(): array
    {
        return array_merge([
            'component' => $this->component(),
            'attribute' => $this->attribute,
            'name' => $this->name,
            'value' => $this->value,
        ], ['meta' => $this->meta]);
    }

    public function fill($request, $model)
    {
        return function () {};
    }

    public function resolve($resource, $attribute = null) {}

    public function resolveForDisplay($resource, $attribute = null) {}
}
