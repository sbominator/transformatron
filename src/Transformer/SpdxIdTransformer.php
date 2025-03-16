<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;

/**
 * Transformer for SPDX identifiers.
 *
 * Handles transformation of SPDX identifiers to CycloneDX format.
 */
class SpdxIdTransformer
{
    /**
     * Transform SPDX ID to CycloneDX serial number.
     *
     * @param string $spdxId The SPDX ID
     * @return string The CycloneDX serial number
     */
    public function transformSpdxId(string $spdxId): string
    {
        // Remove the "SPDXRef-" prefix if present
        return str_replace('SPDXRef-', '', $spdxId);
    }

    /**
     * Transform SPDX version to CycloneDX spec version.
     *
     * @param string $spdxVersion The SPDX version
     * @return string The CycloneDX spec version
     */
    public function transformSpdxVersion(string $spdxVersion): string
    {
        return match ($spdxVersion) {
            'SPDX-2.3' => '1.4',
            'SPDX-2.2' => '1.3',
            'SPDX-2.1' => '1.2',
            default => VersionEnum::CYCLONEDX_VERSION, // Default to latest
        };
    }

    /**
     * Generate a unique SPDX document namespace.
     *
     * @param string $name Document name
     * @param string $prefix Namespace prefix
     * @return string The generated document namespace
     */
    public function generateDocumentNamespace(string $name, string $prefix = 'https://sbominator.example/spdx/'): string
    {
        // Sanitize name for use in URL
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-]/', '-', $name);
        $uniqueId = uniqid();

        return $prefix . $sanitizedName . '-' . $uniqueId;
    }

    /**
     * Check if string is a valid SPDX ID.
     *
     * @param string $id The ID to check
     * @return bool True if the ID is a valid SPDX ID
     */
    public function isValidSpdxId(string $id): bool
    {
        return preg_match('/^SPDXRef-[a-zA-Z0-9.\-]+$/', $id) === 1;
    }

    /**
     * Format string as a valid SPDX ID.
     *
     * @param string $input The input string
     * @return string A valid SPDX ID
     */
    public function formatAsSpdxId(string $input): string
    {
        // If already a valid SPDX ID, return as-is
        if ($this->isValidSpdxId($input)) {
            return $input;
        }

        // If already has SPDXRef- prefix but doesn't match the pattern, sanitize the rest
        if (strpos($input, 'SPDXRef-') === 0) {
            $base = substr($input, 8);
            $sanitized = preg_replace('/[^a-zA-Z0-9.\-]/', '-', $base);
            return 'SPDXRef-' . $sanitized;
        }

        // Otherwise, sanitize the whole string and add prefix
        $sanitized = preg_replace('/[^a-zA-Z0-9.\-]/', '-', $input);
        return 'SPDXRef-' . $sanitized;
    }

    /**
     * Generate a serial number for the CycloneDX document.
     *
     * @param string $prefix Optional prefix for the serial number
     * @return string The generated serial number
     */
    public function generateSerialNumber(string $prefix = ''): string
    {
        $sanitizedPrefix = $prefix
            ? preg_replace('/[^a-zA-Z0-9.\-]/', '-', $prefix) . '-'
            : '';

        return $sanitizedPrefix . 'urn:uuid:' . $this->generateUuid();
    }

    /**
     * Get the format this transformer handles.
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
     * Generate a UUID v4.
     *
     * @return string The generated UUID
     */
    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}