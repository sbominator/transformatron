# SBOMinator Converter

A PHP library for converting Software Bill of Materials (SBOM) between SPDX and CycloneDX formats.

## Installation

Install via Composer:

```bash
composer require sbominator/converter
```

## Requirements

- PHP 7.4 or higher

## Basic Usage

```php
<?php

use SBOMinator\Converter\Converter;
use SBOMinator\Converter\Exception\ConversionException;
use SBOMinator\Converter\Exception\ValidationException;

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

## Features

- Convert between SPDX and CycloneDX JSON formats
- Map key fields including:
  - Document metadata
  - Packages/Components
  - Dependencies
  - License information
  - Hash/checksum data
- Comprehensive validation of input formats
- Detailed warnings for unmapped or missing fields
- Full exception handling for validation and conversion errors

## Running Tests

To run the test suite:

```bash
composer install
composer test
```

## License

MIT License