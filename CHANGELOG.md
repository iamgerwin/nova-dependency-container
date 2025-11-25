# Changelog

All notable changes to `nova-dependency-container` will be documented in this file.

## [1.0.4] - 2025-11-25

### Fixed
- Fixed Nova 4.35.x compatibility issue with `resolveForDisplay` method signature mismatch ([#1](https://github.com/iamgerwin/nova-dependency-container/issues/1))
- Fixed `resolve` method signature to match parent `Laravel\Nova\Fields\Field` class
- Updated `Stubs/Field.php` method signatures to align with Nova's actual implementation

### Changed
- Removed strict type hints from `resolve($resource, $attribute = null)` method for Nova compatibility
- Removed strict type hints from `resolveForDisplay($resource, $attribute = null)` method for Nova compatibility
- Updated `MockField` test mock to use compatible method signatures

### Added
- Added comprehensive tests for method signature compatibility using PHP Reflection API
- Added tests for method invocation with various argument types

## [1.0.3] - 2025-09-26

### Added
- Psalm configuration for future static analysis support (awaiting PHP 8.4 compatibility)
- Psalm cache to gitignore

### Changed
- Updated package description to clarify Laravel Nova 4 and 5 compatibility
- Prepared for enhanced development tooling

## [1.0.2] - 2025-09-26

### Fixed
- Fixed Laravel 12 compatibility in GitHub Actions by updating Orchestra Testbench version requirement
- Added support for Orchestra Testbench v10 for Laravel 12 testing

### Changed
- Updated Orchestra Testbench dependency to support both v9 and v10

## [1.0.1] - 2025-09-26

### Fixed
- Code style improvements and formatting consistency
- Enhanced compatibility with Laravel Nova 4.x and 5.x
- Improved test coverage and reliability

## [1.0.0] - 2024-12-26

### Added
- Initial release
- Support for Laravel Nova 4.x and 5.x
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