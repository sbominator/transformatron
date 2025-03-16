<?php

namespace SBOMinator\Transformatron\Config;

/**
 * Configuration class for CycloneDX field mappings and requirements.
 *
 * Contains the field mappings from CycloneDX to SPDX and the list of required CycloneDX fields.
 */
class CycloneDxFieldConfig
{
    /**
     * CycloneDX to SPDX field mappings.
     *
     * Maps CycloneDX fields to their corresponding SPDX fields and transformation methods.
     *
     * @var array<string, array<string, string|null>>
     */
    private static array $cycloneDxToSpdxMappings = [
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
     * Required fields for valid CycloneDX documents.
     *
     * @var array<string>
     */
    private static array $requiredCycloneDxFields = [
        'bomFormat',
        'specVersion',
        'version'
    ];

    /**
     * Get the CycloneDX to SPDX field mappings.
     *
     * @return array<string, array<string, string|null>> The CycloneDX to SPDX field mappings
     */
    public static function getCycloneDxToSpdxMappings(): array
    {
        return self::$cycloneDxToSpdxMappings;
    }

    /**
     * Get the required CycloneDX fields.
     *
     * @return array<string> The required CycloneDX fields
     */
    public static function getRequiredCycloneDxFields(): array
    {
        return self::$requiredCycloneDxFields;
    }

    /**
     * Check if a field is required in CycloneDX.
     *
     * @param string $field The field name to check
     * @return bool True if the field is required, false otherwise
     */
    public static function isRequiredField(string $field): bool
    {
        return in_array($field, self::$requiredCycloneDxFields);
    }

    /**
     * Get the SPDX field mapping for a given CycloneDX field.
     *
     * @param string $cycloneDxField The CycloneDX field name
     * @return array<string, string|null>|null The mapping or null if not found
     */
    public static function getMappingForField(string $cycloneDxField): ?array
    {
        return self::$cycloneDxToSpdxMappings[$cycloneDxField] ?? null;
    }
}