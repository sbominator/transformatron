<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Transformer for license information.
 *
 * Handles transformation between SPDX and CycloneDX license formats.
 * Supports both simple license IDs and complex license expressions.
 */
class LicenseTransformer implements TransformerInterface
{
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
     * Transform license data between formats.
     *
     * @param array<string, mixed> $sourceData Source license data
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> Transformed license data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array
    {
        $sourceFormat = $this->detectSourceFormat($sourceData);

        if ($sourceFormat === FormatEnum::FORMAT_SPDX) {
            // For SPDX, we expect a string in the sourceData
            if (isset($sourceData['license']) && is_string($sourceData['license'])) {
                return $this->transformSpdxLicenseToCycloneDx($sourceData['license'], $warnings);
            }

            $errors[] = ConversionError::createError(
                'Invalid SPDX license data format',
                'LicenseTransformer',
                ['data' => $sourceData],
                'invalid_spdx_license_format'
            );
            return [];
        }

        if ($sourceFormat === FormatEnum::FORMAT_CYCLONEDX) {
            // For CycloneDX, we expect an array of license objects
            return [
                'license' => $this->transformCycloneDxLicenseToSpdx($sourceData, $warnings)
            ];
        }

        $errors[] = ConversionError::createError(
            'Unknown license data format',
            'LicenseTransformer',
            ['data' => $sourceData],
            'unknown_license_format'
        );

        return [];
    }

    /**
     * Detect the format of the license data.
     *
     * @param array<string, mixed> $data License data to analyze
     * @return string|null Detected format (SPDX or CycloneDX) or null if unknown
     */
    private function detectSourceFormat(array $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        // Check for SPDX format - typically a single string with a license identifier
        if (isset($data['license']) && is_string($data['license'])) {
            return FormatEnum::FORMAT_SPDX;
        }

        // Check for CycloneDX format - typically an array of license objects
        $isLicenseArray = true;
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['license'])) {
                $isLicenseArray = false;
                break;
            }
        }

        if ($isLicenseArray && !empty($data)) {
            return FormatEnum::FORMAT_CYCLONEDX;
        }

        return null;
    }

    /**
     * Transform SPDX license information to CycloneDX license format.
     *
     * @param string $license SPDX license ID or expression
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, array<string, string>>> CycloneDX licenses array
     */
    public function transformSpdxLicenseToCycloneDx(string $license, array &$warnings): array
    {
        if (empty($license) || $license === 'NOASSERTION' || $license === 'NONE') {
            return [];
        }

        // Check if it's a simple license ID or a complex expression
        if ($this->isComplexLicenseExpression($license)) {
            return $this->transformComplexLicenseExpression($license, $warnings);
        }

        // Simple license ID
        return [
            [
                'license' => [
                    'id' => $license
                ]
            ]
        ];
    }

    /**
     * Transform CycloneDX license information to SPDX license format.
     *
     * @param array<array<string, array<string, string>>> $licenses CycloneDX licenses array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return string SPDX license ID or expression
     */
    public function transformCycloneDxLicenseToSpdx(array $licenses, array &$warnings): string
    {
        if (empty($licenses)) {
            return 'NOASSERTION';
        }

        $spdxLicenses = [];

        foreach ($licenses as $license) {
            if (!isset($license['license'])) {
                $warnings[] = "Malformed license entry in CycloneDX component: missing license object";
                continue;
            }

            $licenseObj = $license['license'];

            if (isset($licenseObj['id'])) {
                $spdxLicenses[] = $licenseObj['id'];
            } elseif (isset($licenseObj['name'])) {
                $spdxLicenses[] = $licenseObj['name'];
            } elseif (isset($licenseObj['expression'])) {
                // If we find an expression, just return it directly as it's already in SPDX format
                return $licenseObj['expression'];
            } else {
                $warnings[] = "Malformed license entry in CycloneDX component: missing license id/name";
            }
        }

        if (empty($spdxLicenses)) {
            return 'NOASSERTION';
        }

        // If we have multiple licenses, combine them with OR
        if (count($spdxLicenses) > 1) {
            return '(' . implode(' OR ', $spdxLicenses) . ')';
        }

        return $spdxLicenses[0];
    }

    /**
     * Transform a complex SPDX license expression to CycloneDX format.
     *
     * @param string $expression SPDX license expression
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, array<string, string>>> CycloneDX licenses array
     */
    private function transformComplexLicenseExpression(string $expression, array &$warnings): array
    {
        // For complex expressions, CycloneDX supports an 'expression' field
        return [
            [
                'license' => [
                    'expression' => $expression
                ]
            ]
        ];
    }

    /**
     * Check if a license string is a complex SPDX expression.
     *
     * @param string $license License string to check
     * @return bool True if it's a complex expression
     */
    private function isComplexLicenseExpression(string $license): bool
    {
        // Check for parentheses or logical operators which indicate an expression
        return preg_match('/[\(\)]|\sAND\s|\sOR\s|\sWITH\s/', $license) === 1;
    }

    /**
     * Add license to CycloneDX component if not already set.
     *
     * @param array<string, mixed> $component Component to modify
     * @param string $license SPDX license identifier
     * @return array<string, mixed> Updated component
     */
    public function addLicenseToComponent(array $component, string $license): array
    {
        if (isset($component['licenses']) || empty($license)) {
            return $component;
        }

        $warnings = [];
        $component['licenses'] = $this->transformSpdxLicenseToCycloneDx($license, $warnings);
        return $component;
    }

    /**
     * Add licenses to SPDX package.
     *
     * @param array<string, mixed> $package Package to modify
     * @param array<array<string, array<string, string>>> $licenses CycloneDX licenses array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<string, mixed> Updated package
     */
    public function addLicensesToPackage(array $package, array $licenses, array &$warnings): array
    {
        if (empty($licenses)) {
            return $package;
        }

        $spdxLicense = $this->transformCycloneDxLicenseToSpdx($licenses, $warnings);

        if ($spdxLicense !== 'NOASSERTION') {
            $package['licenseConcluded'] = $spdxLicense;

            // Also set licenseDeclared if not present
            if (!isset($package['licenseDeclared'])) {
                $package['licenseDeclared'] = $spdxLicense;
            }
        }

        return $package;
    }

    /**
     * Get common SPDX license IDs.
     *
     * @return array<string> Array of common SPDX license IDs
     */
    public function getCommonSpdxLicenseIds(): array
    {
        return [
            'MIT',
            'Apache-2.0',
            'GPL-2.0-only',
            'GPL-3.0-only',
            'LGPL-2.1-only',
            'LGPL-3.0-only',
            'BSD-2-Clause',
            'BSD-3-Clause',
            'MPL-2.0',
            'CC0-1.0',
            'CC-BY-4.0'
        ];
    }
}