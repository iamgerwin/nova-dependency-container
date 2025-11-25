# Nova Dependency Container

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamgerwin/nova-dependency-container.svg?style=flat-square)](https://packagist.org/packages/iamgerwin/nova-dependency-container)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/iamgerwin/nova-dependency-container/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/iamgerwin/nova-dependency-container/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/iamgerwin/nova-dependency-container/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/iamgerwin/nova-dependency-container/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/iamgerwin/nova-dependency-container.svg?style=flat-square)](https://packagist.org/packages/iamgerwin/nova-dependency-container)

A Laravel Nova field container allowing fields to depend on other field values. Show and hide fields dynamically based on other fields' values with support for complex conditional logic.

![Nova Dependency Container Demo](https://i.imgur.com/TpG8L0Z.gif)

## Features

- **Conditional Field Display**: Show/hide fields based on other field values
- **Multiple Dependency Types**: Support for various comparison operators
- **Complex Logic**: Chain multiple conditions together
- **Nova 4 & 5 Compatible**: Works with Laravel Nova 4.x and 5.x (tested with Nova 4.35.x and Nova 5.7.5)
- **Laravel 12 Ready**: Full support for Laravel 11.x and 12.x
- **PHP 8.3 Support**: Modern PHP features and type safety
- **Fully Tested**: Comprehensive test coverage with Pest
- **Development Ready**: Comprehensive testing and code quality tools

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or 12.x
- Laravel Nova 4.x or 5.x

## Installation

You can install the package via composer:

```bash
composer require iamgerwin/nova-dependency-container
```

## Usage

### Basic Usage

```php
use Iamgerwin\NovaDependencyContainer\NovaDependencyContainer;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

public function fields(NovaRequest $request)
{
    return [
        Select::make('Type')
            ->options([
                'personal' => 'Personal',
                'business' => 'Business',
            ]),

        NovaDependencyContainer::make([
            Text::make('Company Name'),
            Text::make('Tax ID'),
        ])->dependsOn('type', 'business'),

        NovaDependencyContainer::make([
            Text::make('Personal ID'),
            Text::make('Date of Birth'),
        ])->dependsOn('type', 'personal'),
    ];
}
```

### Available Dependency Methods

#### `dependsOn(string $field, $value)`
Show container when field equals specific value:

```php
NovaDependencyContainer::make([
    Text::make('Company Name'),
])->dependsOn('type', 'business')
```

#### `dependsOnIn(string $field, array $values)`
Show container when field value is in array:

```php
NovaDependencyContainer::make([
    Text::make('Priority Note'),
])->dependsOnIn('status', ['urgent', 'high'])
```

#### `dependsOnNot(string $field, $value)`
Show container when field does NOT equal value:

```php
NovaDependencyContainer::make([
    Text::make('Cancellation Reason'),
])->dependsOnNot('status', 'active')
```

#### `dependsOnNotIn(string $field, array $values)`
Show container when field value is NOT in array:

```php
NovaDependencyContainer::make([
    Text::make('Additional Info'),
])->dependsOnNotIn('status', ['completed', 'cancelled'])
```

#### `dependsOnEmpty(string $field)`
Show container when field is empty:

```php
NovaDependencyContainer::make([
    Text::make('Default Value'),
])->dependsOnEmpty('custom_value')
```

#### `dependsOnNotEmpty(string $field)`
Show container when field is NOT empty:

```php
NovaDependencyContainer::make([
    Textarea::make('Description'),
])->dependsOnNotEmpty('title')
```

#### `dependsOnNullOrZero(string $field)`
Show container when field is null or zero:

```php
NovaDependencyContainer::make([
    Text::make('Free tier features'),
])->dependsOnNullOrZero('subscription_plan')
```

### Chaining Multiple Dependencies

You can chain multiple dependencies together. ALL conditions must be met:

```php
NovaDependencyContainer::make([
    Text::make('Premium Features'),
    Text::make('Custom Domain'),
])->dependsOn('plan', 'premium')
  ->dependsOnNotEmpty('company_name')
  ->dependsOnNot('status', 'suspended')
```

### Using with Closures

You can pass a closure to dynamically generate fields:

```php
NovaDependencyContainer::make(function () {
    return [
        Text::make('Dynamic Field 1'),
        Text::make('Dynamic Field 2'),
    ];
})->dependsOn('type', 'dynamic')
```

### Apply to Fields (Flat Structure)

Use `applyToFields()` to apply dependencies without the container wrapper:

```php
NovaDependencyContainer::make([
    Text::make('Field 1'),
    Text::make('Field 2'),
])->dependsOn('type', 'special')
  ->applyToFields()
```

### Adding Dependencies to Regular Fields

You can also add dependencies directly to regular Nova fields using the `HasDependencies` trait:

```php
use Iamgerwin\NovaDependencyContainer\HasDependencies;
use Laravel\Nova\Fields\Text;

class CustomTextField extends Text
{
    use HasDependencies;
}

// In your Nova resource:
CustomTextField::make('Special Field')
    ->dependsOn('type', 'custom')
```

## Advanced Examples

### Multi-Step Form

```php
public function fields(NovaRequest $request)
{
    return [
        Select::make('Step')
            ->options([
                '1' => 'Basic Info',
                '2' => 'Address',
                '3' => 'Confirmation',
            ]),

        NovaDependencyContainer::make([
            Text::make('First Name')->required(),
            Text::make('Last Name')->required(),
            Text::make('Email')->required(),
        ])->dependsOn('step', '1'),

        NovaDependencyContainer::make([
            Text::make('Street Address')->required(),
            Text::make('City')->required(),
            Text::make('Zip Code')->required(),
        ])->dependsOn('step', '2'),

        NovaDependencyContainer::make([
            Boolean::make('Confirm Details'),
            Textarea::make('Additional Notes'),
        ])->dependsOn('step', '3'),
    ];
}
```

### Conditional Validation

```php
public function fields(NovaRequest $request)
{
    return [
        Select::make('Payment Method')
            ->options([
                'credit_card' => 'Credit Card',
                'bank_transfer' => 'Bank Transfer',
                'paypal' => 'PayPal',
            ]),

        NovaDependencyContainer::make([
            Text::make('Card Number')
                ->required()
                ->rules('required', 'credit_card'),
            Text::make('CVV')
                ->required()
                ->rules('required', 'digits:3'),
        ])->dependsOn('payment_method', 'credit_card'),

        NovaDependencyContainer::make([
            Text::make('Bank Account')
                ->required(),
            Text::make('Routing Number')
                ->required(),
        ])->dependsOn('payment_method', 'bank_transfer'),

        NovaDependencyContainer::make([
            Text::make('PayPal Email')
                ->required()
                ->rules('required', 'email'),
        ])->dependsOn('payment_method', 'paypal'),
    ];
}
```

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Code Quality

Format code with Laravel Pint:

```bash
composer format
```

Run static analysis with PHPStan:

```bash
composer analyse
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [iamgerwin](https://github.com/iamgerwin)
- [All Contributors](../../contributors)

This package is inspired by [alexwenzel/nova-dependency-container](https://github.com/alexwenzel/nova-dependency-container).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.