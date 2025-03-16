<?php

namespace SBOMinator\Transformatron\Config;

/**
 * Configuration class for SPDX package to CycloneDX component field mappings.
 *
 * Contains the field mappings between SPDX packages and CycloneDX components.
 */
class PackageComponentMappingConfig
{
    /**
     * SPDX package field to CycloneDX component field mappings.
     *
     * @var array<string, string>
     */
    private static array $packageToComponentMappings = [
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
     * CycloneDX component field to SPDX package field mappings.
     *
     * @var array<string, string>
     */
    private static array $componentToPackageMappings = [
        'name' => 'name',
        'version' => 'versionInfo',
        'purl' => 'downloadLocation', // Will need transformation
        'supplier' => 'supplier', // Will need transformation
        'licenses' => 'licenseConcluded', // Will need transformation
        'description' => 'description',
        'hashes' => 'checksums' // Will need transformation
    ];

    /**
     * Get the SPDX package to CycloneDX component field mappings.
     *
     * @return array<string, string> The package to component field mappings
     */
    public static function getPackageToComponentMappings(): array
    {
        return self::$packageToComponentMappings;
    }

    /**
     * Get the CycloneDX component to SPDX package field mappings.
     *
     * @return array<string, string> The component to package field mappings
     */
    public static function getComponentToPackageMappings(): array
    {
        return self::$componentToPackageMappings;
    }

    /**
     * Get the CycloneDX component field for a given SPDX package field.
     *
     * @param string $packageField The SPDX package field name
     * @return string|null The corresponding CycloneDX component field or null if not found
     */
    public static function getComponentFieldForPackageField(string $packageField): ?string
    {
        return self::$packageToComponentMappings[$packageField] ?? null;
    }

    /**
     * Get the SPDX package field for a given CycloneDX component field.
     *
     * @param string $componentField The CycloneDX component field name
     * @return string|null The corresponding SPDX package field or null if not found
     */
    public static function getPackageFieldForComponentField(string $componentField): ?string
    {
        return self::$componentToPackageMappings[$componentField] ?? null;
    }

    /**
     * Get all known SPDX package field names.
     *
     * @return array<string> Array of package field names
     */
    public static function getAllPackageFields(): array
    {
        return array_keys(self::$packageToComponentMappings);
    }

    /**
     * Get all known CycloneDX component field names.
     *
     * @return array<string> Array of component field names
     */
    public static function getAllComponentFields(): array
    {
        return array_keys(self::$componentToPackageMappings);
    }
}