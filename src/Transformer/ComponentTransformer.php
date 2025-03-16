<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Config\PackageComponentMappingConfig;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Transformer for CycloneDX components.
 *
 * Handles transformation of CycloneDX components to SPDX packages.
 */
class ComponentTransformer implements TransformerInterface
{
    /**
     * @var HashTransformer
     */
    private HashTransformer $hashTransformer;

    /**
     * @var LicenseTransformer
     */
    private LicenseTransformer $licenseTransformer;

    /**
     * @var SpdxIdTransformer
     */
    private SpdxIdTransformer $spdxIdTransformer;

    /**
     * Constructor.
     *
     * @param HashTransformer $hashTransformer Hash transformer
     * @param LicenseTransformer $licenseTransformer License transformer
     * @param SpdxIdTransformer $spdxIdTransformer SPDX ID transformer
     */
    public function __construct(
        HashTransformer $hashTransformer,
        LicenseTransformer $licenseTransformer,
        SpdxIdTransformer $spdxIdTransformer
    ) {
        $this->hashTransformer = $hashTransformer;
        $this->licenseTransformer = $licenseTransformer;
        $this->spdxIdTransformer = $spdxIdTransformer;
    }

    /**
     * Get the source format this transformer handles.
     *
     * @return string The format (e.g., 'CycloneDX')
     */
    public function getSourceFormat(): string
    {
        return FormatEnum::FORMAT_CYCLONEDX;
    }

    /**
     * Get the target format for this transformer.
     *
     * @return string The target format (e.g., 'SPDX')
     */
    public function getTargetFormat(): string
    {
        return FormatEnum::FORMAT_SPDX;
    }

    /**
     * Transform CycloneDX components to SPDX packages.
     *
     * @param array<string, mixed> $sourceData Source data containing CycloneDX components
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> The transformed SPDX packages data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array
    {
        if (!isset($sourceData['components']) || !is_array($sourceData['components'])) {
            $errors[] = ConversionError::createError(
                'Missing or invalid components array in source data',
                'ComponentTransformer',
                ['sourceData' => $sourceData],
                'invalid_components_data'
            );
            return [];
        }

        try {
            $packages = $this->transformComponentsToPackages($sourceData['components'], $warnings);
            return ['packages' => $packages];
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming components to packages: " . $e->getMessage(),
                "ComponentTransformer",
                ['component_count' => count($sourceData['components'])],
                'component_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Transform CycloneDX components array to SPDX packages array.
     *
     * @param array<array<string, mixed>> $components CycloneDX components array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, mixed>> SPDX packages array
     */
    public function transformComponentsToPackages(array $components, array &$warnings): array
    {
        return array_map(function($component) use (&$warnings) {
            return $this->transformComponentToPackage($component, $warnings);
        }, $components);
    }

    /**
     * Transform a single CycloneDX component to an SPDX package.
     *
     * @param array<string, mixed> $component CycloneDX component
     * @param array<string> &$warnings Array to collect warnings
     * @return array<string, mixed> SPDX package
     */
    public function transformComponentToPackage(array $component, array &$warnings): array
    {
        // Create the package with an SPDXID
        $bomRef = $this->getBomRefFromComponent($component);
        $package = [
            'SPDXID' => $this->spdxIdTransformer->formatAsSpdxId($bomRef),
            'filesAnalyzed' => false // Default for packages without file-specific data
        ];

        // Map component fields to package fields
        $package = $this->mapComponentFieldsToPackage($component, $package, $warnings);
        $this->addUnknownComponentFieldWarnings($component, $warnings);

        // Ensure the package has a name field
        if (!isset($package['name'])) {
            $warnings[] = "Component missing required field: name";
            $package['name'] = 'unknown-' . uniqid();
        }

        return $package;
    }

    /**
     * Get the bom-ref value from a component.
     *
     * @param array<string, mixed> $component The component
     * @return string The bom-ref value or a generated ID
     */
    protected function getBomRefFromComponent(array $component): string
    {
        if (isset($component['bom-ref'])) {
            return $component['bom-ref'];
        }

        // If no bom-ref, use purl if available
        if (isset($component['purl'])) {
            return $component['purl'];
        }

        // Generate a unique ID based on component name and version if available
        $name = $component['name'] ?? 'unknown';
        $version = $component['version'] ?? '';

        return $name . ($version ? "-{$version}" : '') . '-' . uniqid();
    }

    /**
     * Map component fields to package fields based on configuration.
     *
     * @param array<string, mixed> $component Source component
     * @param array<string, mixed> $package Target package
     * @param array<string> &$warnings Warnings array
     * @return array<string, mixed> Updated package
     */
    protected function mapComponentFieldsToPackage(
        array $component,
        array $package,
        array &$warnings
    ): array {
        $mappings = PackageComponentMappingConfig::getComponentToPackageMappings();

        foreach ($mappings as $componentField => $packageField) {
            if (!isset($component[$componentField])) {
                continue;
            }

            $package = $this->handleComponentFieldTransformation(
                $package,
                $componentField,
                $packageField,
                $component[$componentField],
                $warnings
            );
        }

        return $package;
    }

    /**
     * Handle specific component field transformation.
     *
     * @param array<string, mixed> $package Package to update
     * @param string $componentField Component field name
     * @param string $packageField Package field name
     * @param mixed $value Field value
     * @param array<string> &$warnings Warnings array
     * @return array<string, mixed> Updated package
     */
    protected function handleComponentFieldTransformation(
        array $package,
        string $componentField,
        string $packageField,
        $value,
        array &$warnings
    ): array {
        switch ($componentField) {
            case 'hashes':
                $package['checksums'] = $this->hashTransformer->transformCycloneDxHashesToSpdxChecksums(
                    $value,
                    $warnings
                );
                break;

            case 'licenses':
                $package = $this->licenseTransformer->addLicensesToPackage($package, $value, $warnings);
                break;

            case 'purl':
                // Handle special case: Package URL is used as downloadLocation in SPDX
                $package['downloadLocation'] = $value;

                // If we don't have a name, try to extract it from the purl
                if (!isset($package['name']) && is_string($value)) {
                    $package['name'] = $this->extractNameFromPurl($value);
                }
                break;

            default:
                // Direct field mapping
                $package[$packageField] = $value;
        }

        return $package;
    }

    /**
     * Extract package name from a package URL (purl).
     *
     * @param string $purl Package URL
     * @return string Extracted name or fallback
     */
    protected function extractNameFromPurl(string $purl): string
    {
        // Simple extraction for common formats
        // Format: pkg:type/namespace/name@version
        if (preg_match('/pkg:([^\/]+)\/(?:[^\/]+\/)?([^@]+)/', $purl, $matches)) {
            return $matches[2];
        }

        // If we can't extract a name, return a fallback
        return 'package-' . substr(md5($purl), 0, 8);
    }

    /**
     * Add warnings for unknown component fields.
     *
     * @param array<string, mixed> $component Component data
     * @param array<string> &$warnings Warnings array
     */
    protected function addUnknownComponentFieldWarnings(array $component, array &$warnings): void
    {
        $knownFields = array_merge(
            array_keys(PackageComponentMappingConfig::getComponentToPackageMappings()),
            ['bom-ref', 'type', 'components', 'evidence']
        );

        $unknownFields = array_diff(array_keys($component), $knownFields);

        foreach ($unknownFields as $field) {
            $warnings[] = "Unknown or unmapped component field: {$field}";
        }
    }
}