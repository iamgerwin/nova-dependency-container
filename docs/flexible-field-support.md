# Flexible Field Support

This document describes how `NovaDependencyContainer` works with [whitecube/nova-flexible-content](https://github.com/whitecube/nova-flexible-content) Flexible fields.

## Overview

Starting from version 1.0.5, `NovaDependencyContainer` supports conditional field visibility inside Flexible field layouts. This enables dynamic form behavior within repeatable content blocks.

## The Problem

When fields are placed inside a Flexible layout, their attribute names are automatically prefixed by the Flexible field component. For example:

- A field named `type` inside a Flexible layout becomes `overlay_items__0__type`
- The index (`0`, `1`, `2`, etc.) changes based on the layout position

This prefixing caused the original dependency detection to fail because it looked for exact attribute matches.

## The Solution

The package now implements **context-aware dependency resolution** that:

1. Detects when a field is inside a Flexible layout context
2. Automatically resolves relative field names to their prefixed equivalents
3. Supports multiple Flexible attribute formats (double underscore and bracket notation)
4. Falls back to suffix matching for edge cases

## Usage

Use `NovaDependencyContainer` inside Flexible layouts exactly as you would elsewhere:

```php
use Iamgerwin\NovaDependencyContainer\NovaDependencyContainer;
use Whitecube\NovaFlexibleContent\Flexible;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;

Flexible::make('Overlay Items')
    ->addLayout('Overlay Item', 'overlay_item', [
        Select::make('Type')
            ->options([
                'Default' => 'Default',
                'Location' => 'Location',
                'Contact Us' => 'Contact Us',
            ]),

        NovaDependencyContainer::make([
            Text::make('Recipient Email', 'recipient_email')
                ->rules('nullable', 'email', 'max:255'),
        ])->dependsOn('type', 'Contact Us'),

        NovaDependencyContainer::make([
            Text::make('Location Name', 'location_name'),
            Text::make('Address', 'address'),
        ])->dependsOn('type', 'Location'),
    ]),
```

## How It Works

### Attribute Resolution

When you specify `dependsOn('type', 'Contact Us')`, the package:

1. First attempts an exact match for `type`
2. If not found, detects the Flexible context prefix from sibling fields
3. Resolves to the prefixed attribute (e.g., `overlay_items__0__type`)
4. Checks multiple format variations for compatibility

### Supported Attribute Formats

The package recognizes these Flexible field attribute patterns:

| Format | Example |
|--------|---------|
| Double underscore | `overlay_items__0__type` |
| Bracket notation | `overlay_items[0][type]` |
| Single underscore | `overlay_items_0_type` |

### Event Handling

When a field value changes inside a Flexible layout:

1. The change event includes the full prefixed attribute
2. The package extracts the base attribute name
3. Both the full and base attribute values are cached
4. Dependencies are re-evaluated against all matching fields

## Multiple Flexible Groups

Dependencies work correctly across multiple instances of the same layout. Each layout group maintains its own context, so changing `type` in one Overlay Item only affects the dependent fields in that same group.

```php
// Layout instance 0: type = 'Contact Us' -> shows recipient_email
// Layout instance 1: type = 'Location' -> shows location_name, address
// Layout instance 2: type = 'Default' -> hides all dependent fields
```

## Limitations

### Cross-Group Dependencies

Currently, dependencies are resolved within the same Flexible group context. Cross-group dependencies (e.g., a field in group 0 depending on a field in group 1) are not supported.

### Deeply Nested Flexible Fields

The package supports one level of Flexible field nesting. Deeply nested Flexible fields (Flexible inside Flexible) may not resolve correctly.

### Detail View

Flexible field support works best on form views (create/edit). Detail view support is included but may have limitations depending on how the Flexible content renders field data.

## Troubleshooting

### Dependency Not Triggering

If a dependency isn't working inside a Flexible layout:

1. **Check the field attribute name**: Ensure you're using the simple attribute name (e.g., `type`) not the prefixed version
2. **Verify the field exists**: The dependent field must be in the same Flexible layout group
3. **Check the console**: Browser dev tools may show helpful debugging information

### Field Visibility Stuck

If a field remains hidden when it should be visible:

1. Try selecting a different option and then back to the triggering value
2. Ensure the comparison value matches exactly (including case sensitivity)

## Technical Details

### Context Detection

The Flexible context is detected by examining (in order of priority):

1. Cached context prefix from previous field-changed events
2. The container's own `attribute` property
3. Child field `attribute` properties (inside the dependency container)
4. Cached dependent field values
5. Parent component structure

The context prefix is cached once detected, improving performance and reliability for subsequent dependency checks.

### Cross-Group Event Filtering

When a field changes inside a Flexible layout, the event is broadcast globally. The dependency container automatically filters events to only process those from the same Flexible group:

- Events with a prefix matching the container's context are processed
- Events from different Flexible groups (different index) are ignored
- Non-prefixed events are processed normally (for non-Flexible contexts)

This ensures that changing a field in Overlay Item #1 doesn't affect the dependent fields in Overlay Item #2.

### Regex Patterns Used

```javascript
// Double underscore format
/^(.+__\d+__)/

// Bracket format
/^(.+\[\d+\]\[)/

// Base attribute extraction (double underscore)
/^.+__\d+__(.+)$/

// Base attribute extraction (bracket)
/^.+\[\d+\]\[(.+)\]$/
```

## Version History

- **1.0.13**: Fixed dependent field values not being saved on form submission
- **1.0.12**: Added DOM-based watching for Flexible fields where Nova events don't fire
- **1.0.11**: Fixed regex patterns to support nova-flexible-content's random key format
- **1.0.10**: Fixed FieldServiceProvider not registering assets with Nova
- **1.0.9**: Added debug logging to diagnose Flexible field issues
- **1.0.8**: Fixed missing compiled assets in package distribution
- **1.0.7**: Fixed multi-group context contamination with 4-method detection approach
- **1.0.6**: Improved Flexible field context detection and cross-group event filtering
- **1.0.5**: Added Flexible field support (this feature)
- **1.0.4**: Nova 4.35.x compatibility fixes
- **1.0.0**: Initial release
