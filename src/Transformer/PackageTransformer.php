<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Config\PackageComponentMappingConfig;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Transformer for SPDX packages.
 *
 * Handles transformation of SPDX packages to CycloneDX components.
 */
class PackageTransformer implements TransformerInterface
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
     * @return string The format (e.g., 'SPDX')
     */
    public function getSourceFormat(): string
    {
        return FormatEnum::FORMAT_SPDX;
    }

    /**
     * Get the target format for this transformer.
     *
     * @return string The target format (e.g., 'CycloneDX')
     */
    public function getTargetFormat(): string
    {
        return FormatEnum::FORMAT_CYCLONEDX;
    }

    /**
     * Transform SPDX packages to CycloneDX components.
     *
     * @param array<string, mixed> $sourceData Source data containing SPDX packages
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> The transformed CycloneDX components data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array
    {
        if (!isset($sourceData['packages']) || !is_array($sourceData['packages'])) {
            $errors[] = ConversionError::createError(
                'Missing or invalid packages array in source data',
                'PackageTransformer',
                ['sourceData' => $sourceData],
                'invalid_packages_data'
            );
            return [];
        }

        try {
            $components = $this->transformPackagesToComponents($sourceData['packages'], $warnings);
            return ['components' => $components];
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming packages to components: " . $e->getMessage(),
                "PackageTransformer",
                ['package_count' => count($sourceData['packages'])],
                'package_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Transform SPDX packages array to CycloneDX components array.
     *
     * @param array<array<string, mixed>> $packages SPDX packages array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, mixed>> CycloneDX components array
     */
    public function transformPackagesToComponents(array $packages, array &$warnings): array
    {
        return array_map(function($package) use (&$warnings) {
            return $this->transformPackageToComponent($package, $warnings);
        }, $packages);
    }

    /**
     * Transform a single SPDX package to a CycloneDX component.
     *
     * @param array<string, mixed> $package SPDX package
     * @param array<string> &$warnings Array to collect warnings
     * @return array<string, mixed> CycloneDX component
     */
    public function transformPackageToComponent(array $package, array &$warnings): array
    {
        $component = [
            'type' => $this->determineComponentType($package),
            'bom-ref' => isset($package['SPDXID'])
                ? $this->spdxIdTransformer->transformSpdxId($package['SPDXID'])
                : uniqid('component-')
        ];

        $component = $this->mapPackageFieldsToComponent($package, $component, $warnings);
        $this->addUnknownPackageFieldWarnings($package, $warnings);

        if (!isset($component['name'])) {
            $warnings[] = "Package missing required field: name";
            $component['name'] = 'unknown-' . uniqid();
        }

        return $component;
    }

    /**
     * Determine component type from package information.
     *
     * @param array<string, mixed> $package SPDX package
     * @return string Component type
     */
    protected function determineComponentType(array $package): string
    {
        // Default type is library
        $type = 'library';

        // Try to determine from package information
        if (isset($package['comment']) && stripos($package['comment'], 'application') !== false) {
            $type = 'application';
        } elseif (isset($package['name'])) {
            // Try to determine from package name patterns
            $name = strtolower($package['name']);
            if (
                str_ends_with($name, '-app') ||
                str_ends_with($name, '-application') ||
                str_contains($name, 'app-') ||
                str_contains($name, 'application-')
            ) {
                $type = 'application';
            } elseif (
                str_ends_with($name, '-fw') ||
                str_ends_with($name, '-framework') ||
                str_contains($name, 'framework-')
            ) {
                $type = 'framework';
            } elseif (
                str_ends_with($name, '-os') ||
                str_contains($name, 'linux') ||
                str_contains($name, 'windows') ||
                str_contains($name, 'macos')
            ) {
                $type = 'operating-system';
            }
        }

        return $type;
    }

    /**
     * Map package fields to component fields based on configuration.
     *
     * @param array<string, mixed> $package Source package
     * @param array<string, mixed> $component Target component
     * @param array<string> &$warnings Warnings array
     * @return array<string, mixed> Updated component
     */
    protected function mapPackageFieldsToComponent(
        array $package,
        array $component,
        array &$warnings
    ): array {
        $mappings = PackageComponentMappingConfig::getPackageToComponentMappings();

        foreach ($mappings as $packageField => $componentField) {
            if (!isset($package[$packageField])) {
                continue;
            }

            $component = $this->handlePackageFieldTransformation(
                $component,
                $packageField,
                $componentField,
                $package[$packageField],
                $warnings
            );
        }

        return $component;
    }

    /**
     * Handle specific package field transformation.
     *
     * @param array<string, mixed> $component Component to update
     * @param string $packageField Package field name
     * @param string $componentField Component field name
     * @param mixed $value Field value
     * @param array<string> &$warnings Warnings array
     * @return array<string, mixed> Updated component
     */
    protected function handlePackageFieldTransformation(
        array $component,
        string $packageField,
        string $componentField,
        $value,
        array &$warnings
    ): array {
        switch ($packageField) {
            case 'checksums':
                $component['hashes'] = $this->hashTransformer->transformSpdxChecksumsToCycloneDxHashes(
                    $value,
                    $warnings
                );
                break;

            case 'packageVerificationCode':
                $component = $this->addPackageVerificationCodeAsHash($component, $value);
                break;

            case 'licenseConcluded':
                $component['licenses'] = $this->licenseTransformer->transformSpdxLicenseToCycloneDx(
                    $value,
                    $warnings
                );
                break;

            case 'licenseDeclared':
                // Only add if licenseConcluded wasn't already processed
                if (!isset($component['licenses'])) {
                    $component['licenses'] = $this->licenseTransformer->transformSpdxLicenseToCycloneDx(
                        $value,
                        $warnings
                    );
                }
                break;

            case 'downloadLocation':
                // Handle special case of downloadLocation to purl mapping
                $component['purl'] = $this->transformDownloadLocationToPurl($value);
                break;

            default:
                // Direct field mapping
                $component[$componentField] = $value;
        }

        return $component;
    }

    /**
     * Add package verification code as a hash.
     *
     * @param array<string, mixed> $component Component to modify
     * @param array<string, string> $verificationCode Verification code data
     * @return array<string, mixed> Updated component
     */
    protected function addPackageVerificationCodeAsHash(
        array $component,
        array $verificationCode
    ): array {
        if (!isset($verificationCode['value'])) {
            return $component;
        }

        if (!isset($component['hashes'])) {
            $component['hashes'] = [];
        }

        $component['hashes'][] = [
            'alg' => 'SHA1',
            'content' => $verificationCode['value']
        ];

        return $component;
    }

    /**
     * Transform SPDX download location to package URL (purl).
     *
     * @param string $downloadLocation SPDX download location
     * @return string Package URL
     */
    protected function transformDownloadLocationToPurl(string $downloadLocation): string
    {
        // If the download location is already a purl, return as is
        if (strpos($downloadLocation, 'pkg:') === 0) {
            return $downloadLocation;
        }

        // Try to convert common download location formats to purl
        if (preg_match('/git\+https:\/\/github\.com\/([^\/]+)\/([^\/\@]+)(?:@(.+))?/', $downloadLocation, $matches)) {
            $owner = $matches[1];
            $repo = $matches[2];
            $version = $matches[3] ?? '';

            if ($version) {
                return "pkg:github/{$owner}/{$repo}@{$version}";
            } else {
                return "pkg:github/{$owner}/{$repo}";
            }
        }

        // Return as is if we can't convert it
        return $downloadLocation;
    }

    /**
     * Add warnings for unknown package fields.
     *
     * @param array<string, mixed> $package Package data
     * @param array<string> &$warnings Warnings array
     */
    protected function addUnknownPackageFieldWarnings(array $package, array &$warnings): void
    {
        $knownFields = array_merge(
            array_keys(PackageComponentMappingConfig::getPackageToComponentMappings()),
            ['SPDXID', 'filesAnalyzed']
        );

        $unknownFields = array_diff(array_keys($package), $knownFields);

        foreach ($unknownFields as $field) {
            $warnings[] = "Unknown or unmapped package field: {$field}";
        }
    }
}