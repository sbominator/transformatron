<?php

namespace SBOMinator\Transformatron\Transformer;

use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Transformer for SPDX relationships.
 *
 * Handles transformation of SPDX relationships to CycloneDX dependencies format.
 * Supports various relationship types beyond simple dependencies.
 */
class RelationshipTransformer implements TransformerInterface
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
     * Transform SPDX relationships to CycloneDX dependencies.
     *
     * @param array<string, mixed> $sourceData Source data containing SPDX relationships
     * @param array<string> &$warnings Array to collect warnings during transformation
     * @param array<ConversionError> &$errors Array to collect errors during transformation
     * @return array<string, mixed> The transformed CycloneDX dependencies data
     */
    public function transform(array $sourceData, array &$warnings, array &$errors): array
    {
        if (!isset($sourceData['relationships']) || !is_array($sourceData['relationships'])) {
            $errors[] = ConversionError::createError(
                'Missing or invalid relationships array in source data',
                'RelationshipTransformer',
                ['sourceData' => $sourceData],
                'invalid_relationships_data'
            );
            return [];
        }

        try {
            // Check for circular dependencies before transformation
            $circularDependencyWarnings = $this->checkForCircularDependencies($sourceData['relationships']);
            if (!empty($circularDependencyWarnings)) {
                $warnings = array_merge($warnings, $circularDependencyWarnings);
            }

            $dependencies = $this->transformRelationshipsToDependencies($sourceData['relationships'], $warnings);
            return ['dependencies' => $dependencies];
        } catch (\Exception $e) {
            $errors[] = ConversionError::createError(
                "Error transforming relationships to dependencies: " . $e->getMessage(),
                "RelationshipTransformer",
                ['relationship_count' => count($sourceData['relationships'])],
                'relationship_transform_error',
                $e
            );
            return [];
        }
    }

    /**
     * Transform SPDX relationships to CycloneDX dependencies.
     *
     * @param array<array<string, string>> $relationships SPDX relationships array
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @return array<array<string, mixed>> CycloneDX dependencies array
     */
    public function transformRelationshipsToDependencies(array $relationships, array &$warnings): array
    {
        $dependencyMap = $this->buildDependencyMap($relationships, $warnings);
        return $this->formatDependencyMap($dependencyMap);
    }

    /**
     * Build a dependency map from SPDX relationships.
     *
     * @param array<array<string, string>> $relationships SPDX relationships array
     * @param array<string> &$warnings Warnings array
     * @return array<string, array<string>> Map of dependencies by component reference
     */
    protected function buildDependencyMap(array $relationships, array &$warnings): array
    {
        $dependencyMap = [];
        $processedRelationships = [];

        foreach ($relationships as $relationship) {
            if (!$this->isValidRelationship($relationship)) {
                $warnings[] = "Malformed relationship entry in SPDX: missing required fields";
                continue;
            }

            // Process relationship based on its type
            $relationshipType = $relationship['relationshipType'];
            $sourceId = $relationship['spdxElementId'];
            $targetId = $relationship['relatedSpdxElement'];

            // Generate a unique key for this relationship to detect duplicates
            $relationshipKey = "{$sourceId}|{$relationshipType}|{$targetId}";
            if (in_array($relationshipKey, $processedRelationships)) {
                // Skip duplicates
                continue;
            }
            $processedRelationships[] = $relationshipKey;

            // Handle different relationship types
            switch ($relationshipType) {
                case RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON:
                case RelationshipTypeEnum::RELATIONSHIP_DYNAMIC_LINK:
                case RelationshipTypeEnum::RELATIONSHIP_STATIC_LINK:
                case RelationshipTypeEnum::RELATIONSHIP_RUNTIME_DEPENDENCY_OF:
                case RelationshipTypeEnum::RELATIONSHIP_DEV_DEPENDENCY_OF:
                case RelationshipTypeEnum::RELATIONSHIP_BUILD_DEPENDENCY_OF:
                case RelationshipTypeEnum::RELATIONSHIP_OPTIONAL_DEPENDENCY_OF:
                    $this->addDependencyToMap($sourceId, $targetId, $dependencyMap);
                    break;
                case RelationshipTypeEnum::RELATIONSHIP_CONTAINS:
                    // Parent-child relationship can be represented as a dependency too
                    $this->addDependencyToMap($sourceId, $targetId, $dependencyMap);
                    break;
                default:
                    // Skip relationships that don't translate to dependencies
                    if (str_contains(strtoupper($relationshipType), 'DEPEND')) {
                        $warnings[] = "Unsupported dependency relationship type: {$relationshipType}";
                    }
                    break;
            }
        }

        return $dependencyMap;
    }

    /**
     * Format dependency map into CycloneDX dependencies array.
     *
     * @param array<string, array<string>> $dependencyMap Dependency map
     * @return array<array<string, mixed>> Dependencies array
     */
    protected function formatDependencyMap(array $dependencyMap): array
    {
        if (empty($dependencyMap)) {
            return [];
        }

        return array_map(function($ref, $deps) {
            return [
                'ref' => $ref,
                'dependsOn' => $deps
            ];
        }, array_keys($dependencyMap), array_values($dependencyMap));
    }

    /**
     * Add dependency to dependency map.
     *
     * @param string $sourceId Source element ID
     * @param string $targetId Target element ID
     * @param array<string, array<string>> &$dependencyMap Dependency map to update
     */
    protected function addDependencyToMap(string $sourceId, string $targetId, array &$dependencyMap): void
    {
        $dependent = $this->spdxIdTransformer->transformSpdxId($sourceId);
        $dependency = $this->spdxIdTransformer->transformSpdxId($targetId);

        if (!isset($dependencyMap[$dependent])) {
            $dependencyMap[$dependent] = [];
        }

        if (!in_array($dependency, $dependencyMap[$dependent])) {
            $dependencyMap[$dependent][] = $dependency;
        }
    }

    /**
     * Check if relationship has all required fields.
     *
     * @param array<string, mixed> $relationship Relationship to check
     * @return bool True if valid
     */
    protected function isValidRelationship(array $relationship): bool
    {
        return isset($relationship['spdxElementId']) &&
            isset($relationship['relatedSpdxElement']) &&
            isset($relationship['relationshipType']);
    }

    /**
     * Find all relationships for a given element.
     *
     * @param array<array<string, string>> $relationships All relationships
     * @param string $elementId Element ID to find relationships for
     * @param string|null $relationshipType Optional relationship type to filter by
     * @return array<array<string, string>> Filtered relationships
     */
    public function findRelationshipsForElement(
        array $relationships,
        string $elementId,
        ?string $relationshipType = null
    ): array {
        return array_filter($relationships, function($relationship) use ($elementId, $relationshipType) {
            $matchesId = $relationship['spdxElementId'] === $elementId;

            if ($relationshipType === null) {
                return $matchesId;
            }

            return $matchesId && $relationship['relationshipType'] === $relationshipType;
        });
    }

    /**
     * Check for circular dependencies in a relationship array.
     *
     * @param array<array<string, string>> $relationships All relationships
     * @return array<string> Warnings about circular dependencies
     */
    public function checkForCircularDependencies(array $relationships): array
    {
        $warnings = [];
        $dependencyGraph = [];

        // Build dependency graph
        foreach ($relationships as $relationship) {
            if (!$this->isValidRelationship($relationship)) {
                continue;
            }

            if ($relationship['relationshipType'] === RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON) {
                $source = $relationship['spdxElementId'];
                $target = $relationship['relatedSpdxElement'];

                if (!isset($dependencyGraph[$source])) {
                    $dependencyGraph[$source] = [];
                }

                $dependencyGraph[$source][] = $target;
            }
        }

        // Check for cycles
        foreach (array_keys($dependencyGraph) as $node) {
            $visited = [];
            $path = [];

            if ($this->hasCycle($node, $dependencyGraph, $visited, $path)) {
                $warnings[] = "Circular dependency detected involving: " . implode(' -> ', $path);
            }
        }

        return $warnings;
    }

    /**
     * Helper method for cycle detection in a graph.
     *
     * @param string $node Current node
     * @param array<string, array<string>> $graph Dependency graph
     * @param array<string, bool> &$visited Already visited nodes
     * @param array<string> &$path Current path
     * @return bool True if a cycle is detected
     */
    private function hasCycle(
        string $node,
        array $graph,
        array &$visited,
        array &$path
    ): bool {
        if (!isset($visited[$node])) {
            $visited[$node] = true;
            $path[] = $node;

            if (isset($graph[$node])) {
                foreach ($graph[$node] as $neighbor) {
                    if (!isset($visited[$neighbor]) && $this->hasCycle($neighbor, $graph, $visited, $path)) {
                        return true;
                    } elseif (in_array($neighbor, $path)) {
                        $path[] = $neighbor; // Add the node that completes the cycle
                        return true;
                    }
                }
            }

            array_pop($path);
        }

        return false;
    }
}