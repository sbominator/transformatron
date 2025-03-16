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
     * Relationship type constants for backward compatibility
     */
    public const RELATIONSHIP_DEPENDS_ON = 'DEPENDS_ON';

    /**
     * SPDX to CycloneDX field mappings - used for unit tests
     *
     * @var array<string, array<string, string|null>>
     */
    public const SPDX_TO_CYCLONEDX_MAPPINGS = [
        'spdxVersion' => ['field' => 'specVersion', 'transform' => 'transformSpdxVersion'],
        'dataLicense' => ['field' => 'license', 'transform' => null],
        'name' => ['field' => 'name', 'transform' => null],
        'SPDXID' => ['field' => 'serialNumber', 'transform' => 'transformSpdxId'],
        'documentNamespace' => ['field' => 'documentNamespace', 'transform' => null],
        'creationInfo' => ['field' => 'metadata', 'transform' => 'transformCreationInfo'],
        'packages' => ['field' => 'components', 'transform' => 'transformPackagesToComponents'],
        'relationships' => ['field' => 'dependencies', 'transform' => 'transformRelationshipsToDependencies']
    ];

    /**
     * CycloneDX to SPDX field mappings - used for unit tests
     *
     * @var array<string, array<string, string|null>>
     */
    public const CYCLONEDX_TO_SPDX_MAPPINGS = [
        'bomFormat' => ['field' => null, 'transform' => null], // No direct mapping
        'specVersion' => ['field' => 'spdxVersion', 'transform' => 'transformSpecVersion'],
        'version' => ['field' => null, 'transform' => null], // No direct mapping
        'serialNumber' => ['field' => 'SPDXID', 'transform' => 'transformSerialNumber'],
        'name' => ['field' => 'name', 'transform' => null],
        'metadata' => ['field' => 'creationInfo', 'transform' => 'transformMetadata'],
        'components' => ['field' => 'packages', 'transform' => 'transformComponentsToPackages'],
        'dependencies' => ['field' => 'relationships', 'transform' => 'transformDependenciesToRelationships']
    ];

    /**
     * SPDX package field to CycloneDX component field mappings - used for unit tests
     *
     * @var array<string, string>
     */
    public const PACKAGE_TO_COMPONENT_MAPPINGS = [
        'name' => 'name',
        'versionInfo' => 'version',
        'downloadLocation' => 'purl', // Will need transformation
        'supplier' => 'supplier', // Will need transformation
        'licenseConcluded' => 'licenses', // Will need transformation
        'licenseDeclared' => 'licenses', // Secondary source
        'description' => 'description',
        'packageFileName' => 'purl', // Secondary source for purl
        'packageVerificationCode' => 'hashes', // Will need transformation
        'checksums' => 'hashes' // Will need transformation
    ];

    /**
     * CycloneDX component field to SPDX package field mappings - used for unit tests
     *
     * @var array<string, string>
     */
    public const COMPONENT_TO_PACKAGE_MAPPINGS = [
        'name' => 'name',
        'version' => 'versionInfo',
        'purl' => 'downloadLocation', // Will need transformation
        'supplier' => 'supplier', // Will need transformation
        'licenses' => 'licenseConcluded', // Will need transformation
        'description' => 'description',
        'hashes' => 'checksums' // Will need transformation
    ];

    /**
     * Required fields for valid SPDX - used for unit tests
     *
     * @var array<string>
     */
    public const REQUIRED_SPDX_FIELDS = [
        'spdxVersion',
        'dataLicense',
        'SPDXID',
        'name',
        'documentNamespace'
    ];

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