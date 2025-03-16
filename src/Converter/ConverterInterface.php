<?php

namespace SBOMinator\Transformatron\Converter;

use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Exception\ConversionException;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Interface for SBOM converters.
 *
 * Defines the contract for classes that convert between different SBOM formats.
 */
interface ConverterInterface
{
    /**
     * Convert SBOM content from source format to target format.
     *
     * @param string $json The JSON string to convert
     * @return ConversionResult The conversion result containing the converted content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convert(string $json): ConversionResult;

    /**
     * Get the source format for this converter.
     *
     * @return string The source format (e.g., 'SPDX', 'CycloneDX')
     */
    public function getSourceFormat(): string;

    /**
     * Get the target format for this converter.
     *
     * @return string The target format (e.g., 'SPDX', 'CycloneDX')
     */
    public function getTargetFormat(): string;
}