<?php

declare(strict_types=1);

namespace Iamgerwin\NovaDependencyContainer;

trait HasDependencies
{
    protected array $dependencies = [];

    protected bool $areDependenciesSatisfied = false;

    public function dependsOn(string $field, $value): self
    {
        $dependency = [
            'field' => $field,
            'value' => $value,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function dependsOnIn(string $field, array $values): self
    {
        foreach ($values as $value) {
            $this->dependsOn($field, $value);
        }

        return $this;
    }

    public function dependsOnNotIn(string $field, array $values): self
    {
        $dependency = [
            'field' => $field,
            'notIn' => $values,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function dependsOnNot(string $field, $value): self
    {
        $dependency = [
            'field' => $field,
            'not' => $value,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function dependsOnNotEmpty(string $field): self
    {
        $dependency = [
            'field' => $field,
            'notEmpty' => true,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function dependsOnEmpty(string $field): self
    {
        $dependency = [
            'field' => $field,
            'empty' => true,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function dependsOnNullOrZero(string $field): self
    {
        $dependency = [
            'field' => $field,
            'nullOrZero' => true,
        ];

        if (! in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }

        return $this->withMeta(['dependencies' => $this->dependencies]);
    }

    public function satisfyDependencies(): self
    {
        $this->areDependenciesSatisfied = true;

        return $this;
    }

    public function areDependenciesSatisfied(): bool
    {
        return $this->areDependenciesSatisfied;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
