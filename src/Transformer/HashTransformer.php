<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;

/**
 * Transformer for hash/checksum data.
 *
 * Handles transformation between SPDX checksums and CycloneDX hashes.
 */
class HashTransformer
{
    /**
     * Transform SPDX checksums to CycloneDX hashes.
     *
     * @param array<array<string, string>> $checksums SPDX checksums array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, string>> CycloneDX hashes array
     */
    public function transformSpdxChecksumsToCycloneDxHashes(array $checksums, array &$warnings): array
    {
        return array_filter(array_map(function($checksum) use (&$warnings) {
            return $this->convertSpdxChecksumToCycloneDxHash($checksum, $warnings);
        }, $checksums));
    }

    /**
     * Convert a single SPDX checksum to a CycloneDX hash.
     *
     * @param array<string, string> $checksum SPDX checksum
     * @param array<string> &$warnings Warnings array
     * @return array<string, string>|null CycloneDX hash or null if invalid
     */
    public function convertSpdxChecksumToCycloneDxHash(array $checksum, array &$warnings): ?array
    {
        if (!isset($checksum['algorithm']) || !isset($checksum['checksumValue'])) {
            $warnings[] = "Malformed checksum entry in SPDX package: missing required fields";
            return null;
        }

        $algorithm = $this->mapSpdxHashAlgorithmToCycloneDx($checksum['algorithm']);

        if ($algorithm === null) {
            $warnings[] = "Unsupported hash algorithm: {$checksum['algorithm']}";
            return null;
        }

        return [
            'alg' => $algorithm,
            'content' => $checksum['checksumValue']
        ];
    }

    /**
     * Transform CycloneDX hashes to SPDX checksums.
     *
     * @param array<array<string, string>> $hashes CycloneDX hashes array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, string>> SPDX checksums array
     */
    public function transformCycloneDxHashesToSpdxChecksums(array $hashes, array &$warnings): array
    {
        return array_filter(array_map(function($hash) use (&$warnings) {
            return $this->convertCycloneDxHashToSpdxChecksum($hash, $warnings);
        }, $hashes));
    }

    /**
     * Convert a single CycloneDX hash to SPDX checksum.
     *
     * @param array<string, string> $hash CycloneDX hash
     * @param array<string> &$warnings Warnings array
     * @return array<string, string>|null SPDX checksum or null if invalid
     */
    public function convertCycloneDxHashToSpdxChecksum(array $hash, array &$warnings): ?array
    {
        if (!isset($hash['alg']) || !isset($hash['content'])) {
            $warnings[] = "Malformed hash entry in CycloneDX component: missing required fields";
            return null;
        }

        $algorithm = $this->mapCycloneDxHashAlgorithmToSpdx($hash['alg']);

        if ($algorithm === null) {
            $warnings[] = "Unsupported hash algorithm: {$hash['alg']}";
            return null;
        }

        return [
            'algorithm' => $algorithm,
            'checksumValue' => $hash['content']
        ];
    }

    /**
     * Map SPDX hash algorithm to CycloneDX algorithm.
     *
     * @param string $algorithm SPDX algorithm
     * @return string|null CycloneDX algorithm or null if unsupported
     */
    public function mapSpdxHashAlgorithmToCycloneDx(string $algorithm): ?string
    {
        $normalized = strtoupper($algorithm);

        return match($normalized) {
            'SHA1' => 'SHA-1',
            'SHA224' => 'SHA-224',
            'SHA256' => 'SHA-256',
            'SHA384' => 'SHA-384',
            'SHA512' => 'SHA-512',
            'SHA3-224' => 'SHA3-224',
            'SHA3-256' => 'SHA3-256',
            'SHA3-384' => 'SHA3-384',
            'SHA3-512' => 'SHA3-512',
            'BLAKE2B-256' => 'BLAKE2b-256',
            'BLAKE2B-384' => 'BLAKE2b-384',
            'BLAKE2B-512' => 'BLAKE2b-512',
            'BLAKE3' => 'BLAKE3',
            'MD5' => 'MD5',
            default => null
        };
    }

    /**
     * Map CycloneDX hash algorithm to SPDX algorithm.
     *
     * @param string $algorithm CycloneDX algorithm
     * @return string|null SPDX algorithm or null if unsupported
     */
    public function mapCycloneDxHashAlgorithmToSpdx(string $algorithm): ?string
    {
        $normalized = strtoupper($algorithm);

        // First normalize any dashes
        $normalized = str_replace('-', '', $normalized);

        return match($normalized) {
            'SHA1' => 'SHA1',
            'SHA224' => 'SHA224',
            'SHA256' => 'SHA256',
            'SHA384' => 'SHA384',
            'SHA512' => 'SHA512',
            'SHA3224' => 'SHA3-224',
            'SHA3256' => 'SHA3-256',
            'SHA3384' => 'SHA3-384',
            'SHA3512' => 'SHA3-512',
            'BLAKE2B256' => 'BLAKE2b-256',
            'BLAKE2B384' => 'BLAKE2b-384',
            'BLAKE2B512' => 'BLAKE2b-512',
            'BLAKE3' => 'BLAKE3',
            'MD5' => 'MD5',
            default => null
        };
    }

    /**
     * Get the supported hash algorithms for SPDX format.
     *
     * @return array<string> Array of supported SPDX hash algorithms
     */
    public function getSupportedSpdxAlgorithms(): array
    {
        return [
            'SHA1',
            'SHA224',
            'SHA256',
            'SHA384',
            'SHA512',
            'SHA3-224',
            'SHA3-256',
            'SHA3-384',
            'SHA3-512',
            'BLAKE2b-256',
            'BLAKE2b-384',
            'BLAKE2b-512',
            'BLAKE3',
            'MD5'
        ];
    }

    /**
     * Get the supported hash algorithms for CycloneDX format.
     *
     * @return array<string> Array of supported CycloneDX hash algorithms
     */
    public function getSupportedCycloneDxAlgorithms(): array
    {
        return [
            'SHA-1',
            'SHA-224',
            'SHA-256',
            'SHA-384',
            'SHA-512',
            'SHA3-224',
            'SHA3-256',
            'SHA3-384',
            'SHA3-512',
            'BLAKE2b-256',
            'BLAKE2b-384',
            'BLAKE2b-512',
            'BLAKE3',
            'MD5'
        ];
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
}