# Changelog

All notable changes to `nova-dependency-container` will be documented in this file.

## [1.0.0] - 2024-12-26

### Added
- Initial release
- Support for Laravel Nova 5.x
- Support for Laravel 11.x and 12.x
- Support for PHP 8.3
- Basic dependency methods: `dependsOn`, `dependsOnIn`, `dependsOnNot`, `dependsOnNotIn`
- Advanced dependency methods: `dependsOnEmpty`, `dependsOnNotEmpty`, `dependsOnNullOrZero`
- Ability to chain multiple dependencies
- `HasDependencies` trait for adding dependencies to custom fields
- `applyToFields()` method for flat structure dependencies
- Comprehensive test suite with Pest
- GitHub Actions for automated testing and code quality
- Full documentation with examples