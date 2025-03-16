<?php

namespace SBOMinator\Transformatron\Config;

/**
 * Configuration class for SPDX field mappings and requirements.
 *
 * Contains the field mappings from SPDX to CycloneDX and the list of required SPDX fields.
 */
class SpdxFieldConfig
{
    /**
     * SPDX to CycloneDX field mappings.
     *
     * Maps SPDX fields to their corresponding CycloneDX fields and transformation methods.
     *
     * @var array<string, array<string, string|null>>
     */
    private static array $spdxToCycloneDxMappings = [
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
     * Required fields for valid SPDX documents.
     *
     * @var array<string>
     */
    private static array $requiredSpdxFields = [
        'spdxVersion',
        'dataLicense',
        'SPDXID',
        'name',
        'documentNamespace'
    ];

    /**
     * Get the SPDX to CycloneDX field mappings.
     *
     * @return array<string, array<string, string|null>> The SPDX to CycloneDX field mappings
     */
    public static function getSpdxToCycloneDxMappings(): array
    {
        return self::$spdxToCycloneDxMappings;
    }

    /**
     * Get the required SPDX fields.
     *
     * @return array<string> The required SPDX fields
     */
    public static function getRequiredSpdxFields(): array
    {
        return self::$requiredSpdxFields;
    }

    /**
     * Check if a field is required in SPDX.
     *
     * @param string $field The field name to check
     * @return bool True if the field is required, false otherwise
     */
    public static function isRequiredField(string $field): bool
    {
        return in_array($field, self::$requiredSpdxFields);
    }

    /**
     * Get the CycloneDX field mapping for a given SPDX field.
     *
     * @param string $spdxField The SPDX field name
     * @return array<string, string|null>|null The mapping or null if not found
     */
    public static function getMappingForField(string $spdxField): ?array
    {
        return self::$spdxToCycloneDxMappings[$spdxField] ?? null;
    }
}