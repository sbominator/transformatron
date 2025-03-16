<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;

/**
 * Transformer for CycloneDX reference identifiers.
 *
 * Handles transformation of CycloneDX identifiers to SPDX format.
 */
class CycloneDxReferenceTransformer
{
    /**
     * Transform CycloneDX spec version to SPDX version.
     *
     * @param string $specVersion The CycloneDX spec version
     * @return string The SPDX version
     */
    public function transformSpecVersion(string $specVersion): string
    {
        return match ($specVersion) {
            '1.4' => 'SPDX-2.3',
            '1.3' => 'SPDX-2.2',
            '1.2' => 'SPDX-2.1',
            default => VersionEnum::SPDX_VERSION, // Default to latest
        };
    }

    /**
     * Transform CycloneDX serial number to SPDX ID.
     *
     * @param string $serialNumber The CycloneDX serial number
     * @return string The SPDX ID
     */
    public function transformSerialNumber(string $serialNumber): string
    {
        // Add "SPDXRef-" prefix if not present
        return strpos($serialNumber, 'SPDXRef-') === 0
            ? $serialNumber
            : 'SPDXRef-' . $serialNumber;
    }

    /**
     * Generate a unique CycloneDX serial number.
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
     * Check if string is a valid CycloneDX reference.
     *
     * @param string $reference The reference to check
     * @return bool True if the reference is valid
     */
    public function isValidReference(string $reference): bool
    {
        // CycloneDX references can be any string without spaces
        return strpos($reference, ' ') === false;
    }

    /**
     * Format string as a valid CycloneDX reference.
     *
     * @param string $input The input string
     * @return string A valid CycloneDX reference
     */
    public function formatAsReference(string $input): string
    {
        // If already a valid reference, return as-is
        if ($this->isValidReference($input)) {
            // We also need to replace invalid characters with dashes
            return preg_replace('/[^a-zA-Z0-9.\-]/', '-', $input);
        }

        // Replace spaces and invalid characters with dashes
        return preg_replace('/[^a-zA-Z0-9.\-]/', '-', $input);
    }

    /**
     * Get the format this transformer handles.
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