<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;

/**
 * Transformer for CycloneDX metadata.
 *
 * Handles transformation of CycloneDX metadata to SPDX format.
 */
class CycloneDxMetadataTransformer
{
    /**
     * Transform CycloneDX metadata to SPDX creation info.
     *
     * @param array<string, mixed> $metadata CycloneDX metadata
     * @return array<string, mixed> SPDX creation info
     */
    public function transformMetadata(array $metadata): array
    {
        $creationInfo = [
            'created' => $metadata['timestamp'] ?? date('c'),
            'creators' => []
        ];

        // Extract tool information
        if (isset($metadata['tools']) && is_array($metadata['tools'])) {
            $creationInfo['creators'] = $this->convertToolsToCreators($metadata['tools']);
        }

        // Extract authors if present
        if (isset($metadata['authors']) && is_array($metadata['authors'])) {
            $creationInfo['creators'] = array_merge(
                $creationInfo['creators'],
                $this->convertAuthorsToCreators($metadata['authors'])
            );
        }

        // Add component info if present
        if (isset($metadata['component']) && is_array($metadata['component'])) {
            // Store component info in comment
            $componentName = $metadata['component']['name'] ?? 'Unknown';
            $componentVersion = $metadata['component']['version'] ?? '';

            $creationInfo['comment'] = sprintf(
                'SBOM for %s%s',
                $componentName,
                $componentVersion ? " version {$componentVersion}" : ''
            );
        }

        // Add default creator if none found
        if (empty($creationInfo['creators'])) {
            $creationInfo['creators'][] = 'Tool: SBOMinator-Converter-1.0';
        }

        return $creationInfo;
    }

    /**
     * Create default creation info for SPDX.
     *
     * @return array<string, mixed> Default creation info
     */
    public function createDefaultCreationInfo(): array
    {
        return [
            'created' => date('c'),
            'creators' => [
                'Tool: SBOMinator-Converter-1.0'
            ]
        ];
    }

    /**
     * Convert tools array to creators array.
     *
     * @param array<mixed> $tools Tools array
     * @return array<string> Creators array
     */
    private function convertToolsToCreators(array $tools): array
    {
        return array_filter(array_map(function($tool) {
            return $this->convertToolToCreator($tool);
        }, $tools));
    }

    /**
     * Convert a tool to creator string.
     *
     * @param array<string, mixed> $tool Tool data
     * @return string|null Creator string or null
     */
    private function convertToolToCreator(array $tool): ?string
    {
        if (!isset($tool['name'])) {
            return null;
        }

        $vendor = $tool['vendor'] ?? '';
        $name = $tool['name'];
        $version = $tool['version'] ?? '1.0';

        // Format: "Tool: VendorToolName-Version"
        return "Tool: {$vendor}{$name}-{$version}";
    }

    /**
     * Convert authors array to creators array.
     *
     * @param array<mixed> $authors Authors array
     * @return array<string> Creators array
     */
    private function convertAuthorsToCreators(array $authors): array
    {
        return array_filter(array_map(function($author) {
            return $this->convertAuthorToCreator($author);
        }, $authors));
    }

    /**
     * Convert an author to creator string.
     *
     * @param array<string, mixed> $author Author data
     * @return string|null Creator string or null
     */
    private function convertAuthorToCreator(array $author): ?string
    {
        if (!isset($author['name'])) {
            return null;
        }

        $name = $author['name'];
        $email = isset($author['email']) ? " ({$author['email']})" : '';

        // Format: "Person: Name (email)"
        return "Person: {$name}{$email}";
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
}