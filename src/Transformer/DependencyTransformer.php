<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Transformer for CycloneDX dependencies.
 *
 * Handles transformation of CycloneDX dependencies to SPDX relationships format.
 */
class DependencyTransformer implements TransformerInterface
{
    /**
     * @var SpdxIdTransformer
     */
    private SpdxIdTransformer $spdxIdTransformer;

    /**
     * Constructor.
     *
     * @param SpdxIdTransformer $spdxIdTransformer SPDX ID transformer for handling IDs
     */
    public function __construct(SpdxIdTransformer $spdxIdTransformer)
    {
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
     * Transform CycloneDX dependencies to SPDX relationships.
     *
     * @param array<string, mixed> $sourceData Source data containing CycloneDX dependencies
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> The transformed SPDX relationships data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array
    {
        if (!isset($sourceData['dependencies']) || !is_array($sourceData['dependencies'])) {
            $errors[] = ConversionError::createError(
                'Missing or invalid dependencies array in source data',
                'DependencyTransformer',
                ['sourceData' => $sourceData],
                'invalid_dependencies_data'
            );
            return [];
        }

        try {
            $relationships = $this->transformDependenciesToRelationships($sourceData['dependencies'], $warnings);
            return ['relationships' => $relationships];
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming dependencies to relationships: " . $e->getMessage(),
                "DependencyTransformer",
                ['dependency_count' => count($sourceData['dependencies'])],
                'dependency_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Transform CycloneDX dependencies to SPDX relationships.
     *
     * @param array<array<string, mixed>> $dependencies CycloneDX dependencies array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, string>> SPDX relationships array
     */
    public function transformDependenciesToRelationships(array $dependencies, array &$warnings): array
    {
        $relationships = [];
        $processedRelationships = [];

        foreach ($dependencies as $dependency) {
            $dependencyRelationships = $this->convertDependencyToRelationships($dependency, $warnings);

            foreach ($dependencyRelationships as $relationship) {
                // Generate a unique key for this relationship to detect duplicates
                $relationshipKey = "{$relationship['spdxElementId']}|{$relationship['relationshipType']}|{$relationship['relatedSpdxElement']}";

                if (!in_array($relationshipKey, $processedRelationships)) {
                    $relationships[] = $relationship;
                    $processedRelationships[] = $relationshipKey;
                }
            }
        }

        return $relationships;
    }

    /**
     * Convert a single dependency to relationships.
     *
     * @param array<string, mixed> $dependency Dependency data
     * @param array<string> &$warnings Warnings array
     * @return array<array<string, string>> Relationships array
     */
    protected function convertDependencyToRelationships(array $dependency, array &$warnings): array
    {
        if (!$this->isValidDependency($dependency)) {
            $warnings[] = "Malformed dependency entry in CycloneDX: missing required fields";
            return [];
        }

        $dependent = $this->spdxIdTransformer->formatAsSpdxId($dependency['ref']);
        $relationships = [];

        if (empty($dependency['dependsOn'])) {
            return $relationships;
        }

        foreach ($dependency['dependsOn'] as $dependencyRef) {
            $relationship = [
                'spdxElementId' => $dependent,
                'relatedSpdxElement' => $this->spdxIdTransformer->formatAsSpdxId($dependencyRef),
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ];

            $relationships[] = $relationship;
        }

        return $relationships;
    }

    /**
     * Check if dependency has all required fields.
     *
     * @param array<string, mixed> $dependency Dependency to check
     * @return bool True if valid
     */
    protected function isValidDependency(array $dependency): bool
    {
        return isset($dependency['ref']) &&
            isset($dependency['dependsOn']) &&
            is_array($dependency['dependsOn']);
    }

    /**
     * Generate additional relationships based on component properties.
     *
     * @param array<array<string, mixed>> $components CycloneDX components
     * @param string $documentId SPDX document ID
     * @param array<string> &$warnings Warnings array
     * @return array<array<string, string>> Additional relationships
     */
    public function generateAdditionalRelationships(
        array $components,
        string $documentId,
        array &$warnings
    ): array {
        $relationships = [];
        $processedComponents = [];

        // Check if components have parent-child relationships
        foreach ($components as $component) {
            if (!isset($component['bom-ref']) || !isset($component['name'])) {
                continue;
            }

            $componentId = $this->spdxIdTransformer->formatAsSpdxId($component['bom-ref']);
            $processedComponents[] = $componentId;

            // Add DESCRIBES relationship between document and top-level components
            // (In CycloneDX, not all components have parent-child relationships)
            $relationships[] = [
                'spdxElementId' => $documentId,
                'relatedSpdxElement' => $componentId,
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DESCRIBES
            ];

            // Handle nested components (if any)
            if (isset($component['components']) && is_array($component['components'])) {
                $relationships = array_merge(
                    $relationships,
                    $this->processNestedComponents($component, $warnings)
                );
            }
        }

        return $relationships;
    }

    /**
     * Process nested components to generate CONTAINS relationships.
     *
     * @param array<string, mixed> $parentComponent Parent component
     * @param array<string> &$warnings Warnings array
     * @return array<array<string, string>> Generated relationships
     */
    protected function processNestedComponents(array $parentComponent, array &$warnings): array
    {
        $relationships = [];

        if (!isset($parentComponent['bom-ref']) || !isset($parentComponent['components'])) {
            return $relationships;
        }

        $parentId = $this->spdxIdTransformer->formatAsSpdxId($parentComponent['bom-ref']);

        foreach ($parentComponent['components'] as $childComponent) {
            if (!isset($childComponent['bom-ref'])) {
                $warnings[] = "Nested component missing bom-ref in parent: {$parentComponent['name']}";
                continue;
            }

            $childId = $this->spdxIdTransformer->formatAsSpdxId($childComponent['bom-ref']);

            // Add CONTAINS relationship
            $relationships[] = [
                'spdxElementId' => $parentId,
                'relatedSpdxElement' => $childId,
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ];

            // Process deeper nested components if any
            if (isset($childComponent['components']) && is_array($childComponent['components'])) {
                $relationships = array_merge(
                    $relationships,
                    $this->processNestedComponents($childComponent, $warnings)
                );
            }
        }

        return $relationships;
    }

    /**
     * Detect and fix invalid reference IDs in dependencies.
     *
     * @param array<array<string, mixed>> $dependencies CycloneDX dependencies
     * @param array<array<string, mixed>> $components CycloneDX components
     * @param array<string> &$warnings Warnings array
     * @return array<array<string, mixed>> Fixed dependencies
     */
    public function sanitizeDependencies(
        array $dependencies,
        array $components,
        array &$warnings
    ): array {
        // Extract all valid component references
        $validRefs = [];
        foreach ($components as $component) {
            if (isset($component['bom-ref'])) {
                $validRefs[] = $component['bom-ref'];
            }
        }

        $sanitizedDependencies = [];

        foreach ($dependencies as $dependency) {
            if (!isset($dependency['ref'])) {
                $warnings[] = "Dependency missing 'ref' field";
                continue;
            }

            // Check if the dependency's ref exists in components
            if (!in_array($dependency['ref'], $validRefs)) {
                $warnings[] = "Dependency references non-existent component: {$dependency['ref']}";
                continue;
            }

            $sanitizedDependsOn = [];
            if (isset($dependency['dependsOn']) && is_array($dependency['dependsOn'])) {
                foreach ($dependency['dependsOn'] as $dependsOn) {
                    if (in_array($dependsOn, $validRefs)) {
                        $sanitizedDependsOn[] = $dependsOn;
                    } else {
                        $warnings[] = "Dependency '{$dependency['ref']}' references non-existent component: {$dependsOn}";
                    }
                }
            }

            if (!empty($sanitizedDependsOn)) {
                $sanitizedDependencies[] = [
                    'ref' => $dependency['ref'],
                    'dependsOn' => $sanitizedDependsOn
                ];
            } else {
                $warnings[] = "Dependency '{$dependency['ref']}' has no valid dependencies after sanitization";
            }
        }

        return $sanitizedDependencies;
    }
}