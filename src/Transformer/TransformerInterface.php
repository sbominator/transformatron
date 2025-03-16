<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Interface for SBOM format transformers.
 *
 * Defines the contract for all transformer classes that convert data
 * between different SBOM format representations.
 */
interface TransformerInterface
{
    /**
     * Get the source format this transformer handles.
     *
     * @return string The source format (e.g., 'SPDX', 'CycloneDX')
     */
    public function getSourceFormat(): string;

    /**
     * Get the target format for this transformer.
     *
     * @return string The target format (e.g., 'SPDX', 'CycloneDX')
     */
    public function getTargetFormat(): string;

    /**
     * Transform data from source format to target format.
     *
     * @param array<string, mixed> $sourceData The source data to transform
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> The transformed data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array;
}