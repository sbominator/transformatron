<?php

namespace SBOMinator\Transformatron;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Exception\ConversionException;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Factory\ConverterFactory;

/**
 * Main converter class for transforming between SPDX and CycloneDX formats.
 *
 * This class provides a simple interface for converting between SBOM formats,
 * delegating the actual conversion work to specialized converters.
 */
class Converter
{
    /**
     * Format constants for backward compatibility
     */
    public const FORMAT_SPDX = FormatEnum::FORMAT_SPDX;
    public const FORMAT_CYCLONEDX = FormatEnum::FORMAT_CYCLONEDX;

    /**
     * Version constants for backward compatibility
     */
    public const SPDX_VERSION = 'SPDX-2.3';
    public const CYCLONEDX_VERSION = '1.4';

    /**
     * Required fields for valid CycloneDX - used for unit tests
     *
     * @var array<string>
     */
    public const REQUIRED_CYCLONEDX_FIELDS = [
        'bomFormat',
        'specVersion',
        'version'
    ];

    /**
     * Factory for creating SBOM converters.
     *
     * @var ConverterFactory
     */
    private ConverterFactory $converterFactory;

    /**
     * Constructor.
     */
    public function __construct(?ConverterFactory $converterFactory = null)
    {
        $this->converterFactory = $converterFactory ?? new ConverterFactory();
    }

    /**
     * Convert SPDX format to CycloneDX format.
     *
     * @param string $json The SPDX JSON to convert
     * @return ConversionResult The conversion result with CycloneDX content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convertSpdxToCyclonedx(string $json): ConversionResult
    {
        try {
            // First attempt to decode the JSON to catch syntax errors
            try {
                json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ValidationException('Invalid JSON: ' . $e->getMessage());
            }

            $converter = $this->converterFactory->createConverter(self::FORMAT_SPDX, self::FORMAT_CYCLONEDX);
            return $converter->convert($json);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ConversionException($e->getMessage(), self::FORMAT_SPDX, self::FORMAT_CYCLONEDX);
        }
    }

    /**
     * Convert CycloneDX format to SPDX format.
     *
     * @param string $json The CycloneDX JSON to convert
     * @return ConversionResult The conversion result with SPDX content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convertCyclonedxToSpdx(string $json): ConversionResult
    {
        try {
            // First attempt to decode the JSON to catch syntax errors
            try {
                json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ValidationException('Invalid JSON: ' . $e->getMessage());
            }

            $converter = $this->converterFactory->createConverter(self::FORMAT_CYCLONEDX, self::FORMAT_SPDX);
            return $converter->convert($json);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ConversionException($e->getMessage(), self::FORMAT_CYCLONEDX, self::FORMAT_SPDX);
        }
    }

    /**
     * Convert between SBOM formats.
     *
     * @param string $json The JSON content to convert
     * @param string $targetFormat The target format
     * @param string|null $sourceFormat The source format (or null for auto-detection)
     * @return ConversionResult The conversion result
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convert(string $json, string $targetFormat, ?string $sourceFormat = null): ConversionResult
    {
        $converter = $this->createConverter($json, $targetFormat, $sourceFormat);
        return $converter->convert($json);
    }

    /**
     * Create the appropriate converter.
     *
     * @param string $json The JSON content to convert
     * @param string $targetFormat The target format
     * @param string|null $sourceFormat The source format (or null for auto-detection)
     * @return \SBOMinator\Transformatron\Converter\ConverterInterface The converter
     * @throws ValidationException If the source format can't be detected
     */
    private function createConverter(string $json, string $targetFormat, ?string $sourceFormat = null): \SBOMinator\Transformatron\Converter\ConverterInterface
    {
        if ($sourceFormat !== null) {
            return $this->converterFactory->createConverter($sourceFormat, $targetFormat);
        }

        return $this->converterFactory->createConverterFromJson($json, $targetFormat);
    }

    /**
     * Auto-detect the format of a JSON string.
     *
     * @param string $json The JSON string to analyze
     * @return string|null The detected format or null if unknown
     */
    public function detectFormat(string $json): ?string
    {
        return $this->converterFactory->detectJsonFormat($json);
    }
}