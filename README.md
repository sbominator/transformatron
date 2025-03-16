# SBOMinator Transformatron

A PHP library for transforming Software Bill of Materials (SBOM) between SPDX and CycloneDX formats.

## Installation

Install via Composer:

```bash
composer require sbominator/transformatron
```

## Requirements

- PHP 8.0 or higher
- ext-json extension

## Basic Usage

The library provides simple methods for converting between SPDX and CycloneDX formats:

```php
<?php

use SBOMinator\Transformatron\Converter;
use SBOMinator\Transformatron\Exception\ConversionException;
use SBOMinator\Transformatron\Exception\ValidationException;

// Create a converter instance
$converter = new Converter();

// Convert SPDX to CycloneDX
try {
    $spdxJson = file_get_contents('path/to/spdx-sbom.json');
    $result = $converter->convertSpdxToCyclonedx($spdxJson);
    
    // Get the converted content
    $cyclonedxJson = $result->getContent();
    
    // Write to file
    file_put_contents('path/to/output-cyclonedx.json', $cyclonedxJson);
    
    // Check for any warnings
    if ($result->hasWarnings()) {
        echo "Conversion completed with warnings:\n";
        foreach ($result->getWarnings() as $warning) {
            echo "- {$warning}\n";
        }
    }
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    print_r($e->getValidationErrors());
} catch (ConversionException $e) {
    echo "Conversion error: " . $e->getMessage() . "\n";
    echo "Source format: " . $e->getSourceFormat() . "\n";
    echo "Target format: " . $e->getTargetFormat() . "\n";
}

// Convert CycloneDX to SPDX
try {
    $cyclonedxJson = file_get_contents('path/to/cyclonedx-sbom.json');
    $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);
    
    // Get the converted content
    $spdxJson = $result->getContent();
    
    // Write to file
    file_put_contents('path/to/output-spdx.json', $spdxJson);
} catch (ValidationException | ConversionException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Advanced Usage

### Auto-detecting Format

The library can automatically detect the source format and convert to the specified target format:

```php
$json = file_get_contents('path/to/unknown-format-sbom.json');

// Detect the format
$sourceFormat = $converter->detectFormat($json);
if ($sourceFormat) {
    echo "Detected format: " . $sourceFormat . "\n";
}

// Convert to target format using auto-detection
try {
    $targetFormat = Converter::FORMAT_CYCLONEDX; // or Converter::FORMAT_SPDX
    $result = $converter->convert($json, $targetFormat);
    
    echo "Successfully converted to " . $result->getFormat() . "\n";
} catch (ValidationException | ConversionException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Accessing Conversion Details

The `ConversionResult` object provides detailed information about the conversion:

```php
$result = $converter->convertSpdxToCyclonedx($spdxJson);

// Check if conversion was successful
if ($result->isSuccessful()) {
    echo "Conversion successful\n";
} else {
    echo "Conversion completed with errors\n";
}

// Get all warnings
foreach ($result->getWarnings() as $warning) {
    echo "Warning: {$warning}\n";
}

// Get all errors (including non-critical ones)
foreach ($result->getErrors() as $error) {
    echo "Error ({$error->getSeverity()}): {$error->getMessage()}\n";
}

// Get a summary of the conversion
$summary = $result->getSummary();
print_r($summary);

// Access the converted content as an array
$contentArray = $result->getContentAsArray();
```

### Specialized Converters

If you need more control, you can work with the specialized converters directly:

```php
use SBOMinator\Transformatron\Factory\ConverterFactory;

// Create a converter factory
$factory = new ConverterFactory();

// Get a specific converter
$spdxToCycloneDxConverter = $factory->createConverter(
    Converter::FORMAT_SPDX, 
    Converter::FORMAT_CYCLONEDX
);

// Or by conversion path
$cycloneDxToSpdxConverter = $factory->createConverterForPath('cyclonedx-to-spdx');

// Use the converter directly
$result = $spdxToCycloneDxConverter->convert($spdxJson);
```

## Using the Converter

The `Converter` class is designed to be simple to use with sensible defaults:

```php
// Create a converter instance
$converter = new Converter();

// Use the converter methods
$result = $converter->convert($json, Converter::FORMAT_SPDX);
```

## Features

- Convert between SPDX 2.3 and CycloneDX 1.4 JSON formats
- Auto-detection of source formats
- Comprehensive field mapping:
  - Document metadata and creation information
  - Packages/Components with detailed properties
  - Dependencies and relationships
  - License information with support for expressions
  - Hash/checksum data with multiple algorithms
- Detailed validation with warnings and errors
- Exception handling for validation and conversion errors

## Supported Field Mappings

### SPDX to CycloneDX
- `spdxVersion` → `specVersion`
- `dataLicense` → `license`
- `name` → `name`
- `SPDXID` → `serialNumber`
- `documentNamespace` → `documentNamespace`
- `creationInfo` → `metadata`
- `packages` → `components`
- `relationships` → `dependencies`

### CycloneDX to SPDX
- `bomFormat` → *(no direct mapping)*
- `specVersion` → `spdxVersion`
- `version` → *(no direct mapping)*
- `serialNumber` → `SPDXID`
- `name` → `name`
- `metadata` → `creationInfo`
- `components` → `packages`
- `dependencies` → `relationships`

## Running Tests

To run the test suite:

```bash
composer install
composer test
```

## Error Handling

The library provides two main exception types:

- `ValidationException`: Thrown when the input JSON is invalid or required fields are missing
- `ConversionException`: Thrown when the conversion process fails due to errors

Additionally, the `ConversionResult` class collects warnings and non-critical errors during the conversion process.

## License

MIT License

## Contributing

please see [CONTRIBUTING.md](CONTRIBUTING.md) for more information.
