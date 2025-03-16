<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;

/**
 * Transformer for SPDX metadata.
 *
 * Handles transformation of SPDX metadata to CycloneDX format.
 */
class SpdxMetadataTransformer
{
    /**
     * Transform SPDX creation info to CycloneDX metadata.
     *
     * @param array<string, mixed> $creationInfo SPDX creation info
     * @return array<string, mixed> CycloneDX metadata
     */
    public function transformCreationInfo(array $creationInfo): array
    {
        $metadata = [
            'timestamp' => $creationInfo['created'] ?? date('c'),
            'tools' => []
        ];

        // Extract tool information from creators
        if (!isset($creationInfo['creators']) || !is_array($creationInfo['creators'])) {
            return $this->addDefaultTool($metadata);
        }

        $tools = $this->extractToolsFromCreators($creationInfo['creators']);
        if (!empty($tools)) {
            $metadata['tools'] = $tools;
        }

        // Extract authors if present
        $authors = $this->extractAuthorsFromCreators($creationInfo['creators']);
        if (!empty($authors)) {
            $metadata['authors'] = $authors;
        }

        // Add default tool if none found
        if (empty($metadata['tools'])) {
            $metadata = $this->addDefaultTool($metadata);
        }

        return $metadata;
    }

    /**
     * Create default metadata for CycloneDX.
     *
     * @return array<string, mixed> Default metadata
     */
    public function createDefaultMetadata(): array
    {
        return [
            'timestamp' => date('c'),
            'tools' => [
                [
                    'vendor' => 'SBOMinator',
                    'name' => 'Converter',
                    'version' => '1.0.0'
                ]
            ]
        ];
    }

    /**
     * Extract tool information from creators array.
     *
     * @param array<string> $creators Creators array
     * @return array<array<string, string>> Tools array
     */
    private function extractToolsFromCreators(array $creators): array
    {
        $tools = [];
        foreach ($creators as $creator) {
            $tool = $this->extractToolFromCreator($creator);
            if ($tool !== null) {
                $tools[] = $tool;
            }
        }
        return $tools;
    }

    /**
     * Extract tool from creator string.
     *
     * @param string $creator Creator string
     * @return array<string, string>|null Tool data or null
     */
    private function extractToolFromCreator(string $creator): ?array
    {
        if (strpos($creator, 'Tool:') !== 0) {
            return null;
        }

        $toolInfo = trim(substr($creator, 5));
        $parts = explode('-', $toolInfo);

        if (count($parts) < 2) {
            return null;
        }

        // For the case of "ExampleTool-1.0", $parts[0] is "ExampleTool" and $parts[1] is "1.0"
        return [
            'vendor' => $parts[0],
            'name' => $parts[0],
            'version' => $parts[1] ?? '1.0'
        ];
    }

    /**
     * Extract authors from creators array.
     *
     * @param array<string> $creators Creators array
     * @return array<array<string, string>> Authors array
     */
    private function extractAuthorsFromCreators(array $creators): array
    {
        $authors = [];
        foreach ($creators as $creator) {
            $author = $this->extractAuthorFromCreator($creator);
            if ($author !== null) {
                $authors[] = $author;
            }
        }
        return $authors;
    }

    /**
     * Extract author from creator string.
     *
     * @param string $creator Creator string
     * @return array<string, string>|null Author data or null
     */
    private function extractAuthorFromCreator(string $creator): ?array
    {
        if (strpos($creator, 'Person:') !== 0) {
            return null;
        }

        $personInfo = trim(substr($creator, 7));

        // Check if there's email information in parentheses
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $personInfo, $matches)) {
            return [
                'name' => trim($matches[1]),
                'email' => trim($matches[2])
            ];
        }

        return [
            'name' => $personInfo
        ];
    }

    /**
     * Add default tool to metadata.
     *
     * @param array<string, mixed> $metadata Metadata to update
     * @return array<string, mixed> Updated metadata
     */
    private function addDefaultTool(array $metadata): array
    {
        $metadata['tools'][] = [
            'vendor' => 'SBOMinator',
            'name' => 'Converter',
            'version' => '1.0.0'
        ];

        return $metadata;
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
}