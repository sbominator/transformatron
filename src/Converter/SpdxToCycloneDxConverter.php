<?php

namespace SBOMinator\Transformatron\Converter;

use SBOMinator\Transformatron\Config\SpdxFieldConfig;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\PackageTransformer;
use SBOMinator\Transformatron\Transformer\RelationshipTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Transformer\SpdxMetadataTransformer;
use SBOMinator\Transformatron\Util\ValidationUtil;
use SBOMinator\Transformatron\Validation\SpdxValidator;

/**
 * Converter for transforming SPDX format to CycloneDX format.
 *
 * Uses specialized transformers to convert each section of the SBOM.
 */
class SpdxToCycloneDxConverter extends AbstractConverter
{
    /**
     * @var SpdxValidator
     */
    private SpdxValidator $validator;

    /**
     * @var SpdxMetadataTransformer
     */
    private SpdxMetadataTransformer $metadataTransformer;

    /**
     * @var PackageTransformer
     */
    private PackageTransformer $packageTransformer;

    /**
     * @var RelationshipTransformer
     */
    private RelationshipTransformer $relationshipTransformer;

    /**
     * @var SpdxIdTransformer
     */
    private SpdxIdTransformer $spdxIdTransformer;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->validator = new SpdxValidator();
        $this->spdxIdTransformer = new SpdxIdTransformer();
        $hashTransformer = new HashTransformer();
        $licenseTransformer = new LicenseTransformer();

        $this->metadataTransformer = new SpdxMetadataTransformer();
        $this->packageTransformer = new PackageTransformer(
            $hashTransformer,
            $licenseTransformer,
            $this->spdxIdTransformer
        );
        $this->relationshipTransformer = new RelationshipTransformer($this->spdxIdTransformer);
    }

    /**
     * Get the source format for this converter.
     *
     * @return string The source format (e.g., 'SPDX')
     */
    public function getSourceFormat(): string
    {
        return FormatEnum::FORMAT_SPDX;
    }

    /**
     * Get the target format for this converter.
     *
     * @return string The target format (e.g., 'CycloneDX')
     */
    public function getTargetFormat(): string
    {
        return FormatEnum::FORMAT_CYCLONEDX;
    }

    /**
     * Validate the source data.
     *
     * @param array<string, mixed> $sourceData The source data to validate
     * @return array<ConversionError> Array of validation errors
     */
    protected function validateSourceData(array $sourceData): array
    {
        $errors = [];
        $validationErrors = $this->validator->validate($sourceData);

        foreach ($validationErrors as $errorMessage) {
            $errors[] = ConversionError::createError(
                $errorMessage,
                'SpdxValidator',
                ['field' => $this->extractFieldFromError($errorMessage)],
                'validation_error'
            );
        }

        // Check for critical issues - missing required fields
        if (!isset($sourceData['spdxVersion']) || strpos($sourceData['spdxVersion'], 'SPDX-') !== 0) {
            $errors[] = ConversionError::createCritical(
                'Invalid or missing spdxVersion - must start with "SPDX-"',
                'SpdxValidator',
                ['actual_value' => $sourceData['spdxVersion'] ?? 'missing'],
                'invalid_spdx_version'
            );
        }

        return $errors;
    }

    /**
     * Extract field name from a validation error message.
     *
     * @param string $errorMessage The error message to parse
     * @return string|null The extracted field name or null
     */
    private function extractFieldFromError(string $errorMessage): ?string
    {
        if (preg_match('/field:\s*([a-zA-Z0-9]+)/', $errorMessage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get initial target data structure.
     *
     * @return array<string, mixed> Initial target data structure
     */
    protected function getInitialTargetData(): array
    {
        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            'version' => 1,
            'components' => []
        ];
    }

    /**
     * Map source data to target data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @param array<string, mixed> $targetData Initial target data structure
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @param array<ConversionError> &$errors Array to collect errors during conversion
     * @return array<string, mixed> Updated target data
     */
    protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array
    {
        $mappings = SpdxFieldConfig::getSpdxToCycloneDxMappings();

        foreach ($mappings as $spdxField => $mapping) {
            if (!isset($sourceData[$spdxField])) {
                ValidationUtil::warnIfRequiredField($spdxField, SpdxFieldConfig::getRequiredSpdxFields(), $warnings);
                continue;
            }

            if ($mapping['field'] === null) {
                continue;
            }

            $value = $this->transformFieldValue(
                $sourceData[$spdxField],
                $mapping['transform'],
                $spdxField,
                $warnings,
                $errors
            );

            $targetData[$mapping['field']] = $value;
        }

        // Generate a unique serial number if not present
        if (!isset($targetData['serialNumber'])) {
            $targetData['serialNumber'] = $this->generateSerialNumber($sourceData);
        }

        return $targetData;
    }

    /**
     * Check for unknown fields in the source data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @return array<string> Warnings for unknown fields
     */
    protected function checkUnknownSourceFields(array $sourceData): array
    {
        $knownFields = array_keys(SpdxFieldConfig::getSpdxToCycloneDxMappings());
        return ValidationUtil::collectUnknownFieldWarnings($sourceData, $knownFields, 'SPDX');
    }

    /**
     * Ensure required default data is added to the target data if missing.
     *
     * @param array<string, mixed> $targetData Target data to update
     * @return array<string, mixed> Updated target data with defaults
     */
    protected function ensureRequiredDefaultData(array $targetData): array
    {
        if (!isset($targetData['metadata'])) {
            $targetData['metadata'] = $this->metadataTransformer->createDefaultMetadata();
        }

        return $targetData;
    }

    /**
     * Transform SPDX version to CycloneDX spec version.
     *
     * @param string $spdxVersion The SPDX version
     * @return string The CycloneDX spec version
     */
    protected function transformSpdxVersion(string $spdxVersion): string
    {
        return $this->spdxIdTransformer->transformSpdxVersion($spdxVersion);
    }

    /**
     * Transform SPDX ID to CycloneDX serial number.
     *
     * @param string $spdxId The SPDX ID
     * @return string The CycloneDX serial number
     */
    protected function transformSpdxId(string $spdxId): string
    {
        return $this->spdxIdTransformer->transformSpdxId($spdxId);
    }

    /**
     * Transform SPDX creation info to CycloneDX metadata.
     *
     * @param array<string, mixed> $creationInfo SPDX creation info
     * @return array<string, mixed> CycloneDX metadata
     */
    protected function transformCreationInfo(array $creationInfo): array
    {
        return $this->metadataTransformer->transformCreationInfo($creationInfo);
    }

    /**
     * Transform SPDX packages to CycloneDX components.
     *
     * @param array<array<string, mixed>> $packages SPDX packages array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @param array<ConversionError> &$errors Array to collect errors during conversion
     * @return array<array<string, mixed>> CycloneDX components array
     */
    protected function transformPackagesToComponents(array $packages, array &$warnings, array &$errors): array
    {
        try {
            return $this->packageTransformer->transformPackagesToComponents($packages, $warnings);
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming packages to components: " . $e->getMessage(),
                "PackageTransformer",
                ['package_count' => count($packages)],
                'package_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Transform SPDX relationships to CycloneDX dependencies.
     *
     * @param array<array<string, string>> $relationships SPDX relationships array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @param array<ConversionError> &$errors Array to collect errors during conversion
     * @return array<array<string, mixed>> CycloneDX dependencies array
     */
    protected function transformRelationshipsToDependencies(array $relationships, array &$warnings, array &$errors): array
    {
        try {
            return $this->relationshipTransformer->transformRelationshipsToDependencies($relationships, $warnings);
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming relationships to dependencies: " . $e->getMessage(),
                "RelationshipTransformer",
                ['relationship_count' => count($relationships)],
                'relationship_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Generate a serial number for the CycloneDX document.
     *
     * @param array<string, mixed> $sourceData The source SPDX data
     * @return string The generated serial number
     */
    private function generateSerialNumber(array $sourceData): string
    {
        $name = $sourceData['name'] ?? '';
        return $this->spdxIdTransformer->generateSerialNumber($name);
    }
}