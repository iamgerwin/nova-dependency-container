# Changelog

All notable changes to `nova-dependency-container` will be documented in this file.

## [1.0.6] - 2025-11-25

### Fixed
- Fixed Flexible field context detection when container attribute is empty ([#4](https://github.com/iamgerwin/nova-dependency-container/issues/4))
- Fixed cross-group event contamination in Flexible layouts

### Added
- Added `cachedContextPrefix` for improved performance and reliability
- Added `extractPrefixFromAttribute()` method for better prefix extraction
- Added cross-group event filtering to prevent dependency interference between Flexible groups

### Changed
- Enhanced `getFlexibleContextPrefix()` to check child field attributes when container attribute is empty
- Enhanced `handleFieldChanged()` to filter events by Flexible group context
- Updated documentation with improved context detection details

### Technical
- Implemented context prefix caching from field-changed events
- Implemented cross-group event filtering in FormField.vue
- Updated both `FormField.vue` and `DetailField.vue` components

## [1.0.5] - 2025-11-25

### Added
- Added support for [whitecube/nova-flexible-content](https://github.com/whitecube/nova-flexible-content) Flexible field layouts ([#2](https://github.com/iamgerwin/nova-dependency-container/issues/2))
- Added context-aware dependency resolution for nested field structures
- Added automatic detection of Flexible field attribute prefixes
- Added support for multiple Flexible attribute formats (double underscore and bracket notation)
- Added comprehensive documentation for Flexible field support (`docs/flexible-field-support.md`)

### Changed
- Enhanced `findFieldByAttribute()` method to support prefix-based field lookups
- Enhanced `getFieldValue()` method to check multiple attribute formats
- Enhanced `handleFieldChanged()` to cache both full and base attribute values

### Technical
- Implemented `getFlexibleContextPrefix()` for detecting Flexible field context
- Implemented `extractBaseAttribute()` for parsing prefixed attribute names
- Implemented `getFlexibleAttributeFormats()` for generating alternative attribute patterns
- Updated both `FormField.vue` and `DetailField.vue` components

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