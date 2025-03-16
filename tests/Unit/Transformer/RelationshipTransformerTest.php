<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Transformer\RelationshipTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;

/**
 * Test cases for RelationshipTransformer class.
 */
class RelationshipTransformerTest extends TestCase
{
    /**
     * @var RelationshipTransformer
     */
    private RelationshipTransformer $transformer;

    /**
     * @var SpdxIdTransformer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $spdxIdTransformer;

    protected function setUp(): void
    {
        $this->spdxIdTransformer = $this->createMock(SpdxIdTransformer::class);
        $this->transformer = new RelationshipTransformer($this->spdxIdTransformer);
    }

    /**
     * Test transforming SPDX relationships to CycloneDX dependencies.
     */
    public function testTransformRelationshipsToDependencies(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep2',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Dep1',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep3',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-DOCUMENT',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DESCRIBES
            ] // This should be ignored, not a dependency
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // Check structure of returned dependencies
        $this->assertCount(2, $dependencies);

        // Find main package dependencies
        $mainDependencies = null;
        foreach ($dependencies as $dependency) {
            if ($dependency['ref'] === 'Package-Main') {
                $mainDependencies = $dependency;
                break;
            }
        }

        $this->assertNotNull($mainDependencies);
        $this->assertCount(2, $mainDependencies['dependsOn']);
        $this->assertContains('Package-Dep1', $mainDependencies['dependsOn']);
        $this->assertContains('Package-Dep2', $mainDependencies['dependsOn']);

        // Find dep1 dependencies
        $dep1Dependencies = null;
        foreach ($dependencies as $dependency) {
            if ($dependency['ref'] === 'Package-Dep1') {
                $dep1Dependencies = $dependency;
                break;
            }
        }

        $this->assertNotNull($dep1Dependencies);
        $this->assertCount(1, $dep1Dependencies['dependsOn']);
        $this->assertContains('Package-Dep3', $dep1Dependencies['dependsOn']);

        // Check that there are no warnings
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling of different relationship types.
     */
    public function testHandleDifferentRelationshipTypes(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DYNAMIC_LINK
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep2',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_STATIC_LINK
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep3',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep4',
                'relationshipType' => 'UNKNOWN_RELATIONSHIP_TYPE'
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // We should get one dependency with 3 dependsOn entries (UNKNOWN_RELATIONSHIP_TYPE is ignored)
        $this->assertCount(1, $dependencies);
        $this->assertEquals('Package-Main', $dependencies[0]['ref']);
        $this->assertCount(3, $dependencies[0]['dependsOn']);
        $this->assertContains('Package-Dep1', $dependencies[0]['dependsOn']); // DYNAMIC_LINK
        $this->assertContains('Package-Dep2', $dependencies[0]['dependsOn']); // STATIC_LINK
        $this->assertContains('Package-Dep3', $dependencies[0]['dependsOn']); // CONTAINS
    }

    /**
     * Test handling of duplicate relationships.
     */
    public function testHandleDuplicateRelationships(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ] // Duplicate relationship
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // We should only get one dependency with one dependsOn entry
        $this->assertCount(1, $dependencies);
        $this->assertEquals('Package-Main', $dependencies[0]['ref']);
        $this->assertCount(1, $dependencies[0]['dependsOn']);
        $this->assertContains('Package-Dep1', $dependencies[0]['dependsOn']);
    }

    /**
     * Test handling malformed relationships.
     */
    public function testHandleMalformedRelationships(): void
    {
        $malformedRelationships = [
            [
                // Missing spdxElementId
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                // Missing relatedSpdxElement
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1'
                // Missing relationshipType
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($malformedRelationships, $warnings);

        // No valid dependencies should be generated
        $this->assertEmpty($dependencies);

        // There should be warnings for each malformed relationship
        $this->assertCount(3, $warnings);
        $this->assertStringContainsString('Malformed relationship entry', $warnings[0]);
    }

    /**
     * Test finding relationships for specific element.
     */
    public function testFindRelationshipsForElement(): void
    {
        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep1',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Main',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep2',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-Dep1',
                'relatedSpdxElement' => 'SPDXRef-Package-Dep3',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ]
        ];

        // Find all relationships for Package-Main
        $mainRelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-Package-Main'
        );
        $this->assertCount(2, $mainRelationships);

        // Find only DEPENDS_ON relationships for Package-Main
        $mainDependsOnRelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-Package-Main',
            RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
        );
        $this->assertCount(1, $mainDependsOnRelationships);

        // Find relationships for non-existent element
        $nonExistentRelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-NonExistent'
        );
        $this->assertEmpty($nonExistentRelationships);
    }

    /**
     * Test detecting circular dependencies.
     */
    public function testCheckForCircularDependencies(): void
    {
        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-C',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-C',
                'relatedSpdxElement' => 'SPDXRef-Package-A',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ] // Creates a cycle: A -> B -> C -> A
        ];

        $warnings = $this->transformer->checkForCircularDependencies($relationships);

        // There should be a warning about the circular dependency
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Circular dependency detected', $warnings[0]);

        // Test with no circular dependencies
        $nonCircularRelationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-C',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ]
            // No cycle here
        ];

        $warnings = $this->transformer->checkForCircularDependencies($nonCircularRelationships);
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling complex relationship networks with various types.
     */
    public function testHandleComplexRelationshipNetwork(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            // Package A depends on B, C
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-C',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            // Package B contains D, E (parent-child)
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-D',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-E',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            // Package C has static link to F
            [
                'spdxElementId' => 'SPDXRef-Package-C',
                'relatedSpdxElement' => 'SPDXRef-Package-F',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_STATIC_LINK
            ],
            // Package D has dynamic link to G
            [
                'spdxElementId' => 'SPDXRef-Package-D',
                'relatedSpdxElement' => 'SPDXRef-Package-G',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DYNAMIC_LINK
            ],
            // Document describes A (should be ignored)
            [
                'spdxElementId' => 'SPDXRef-DOCUMENT',
                'relatedSpdxElement' => 'SPDXRef-Package-A',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DESCRIBES
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // Check expected structure
        $this->assertCount(4, $dependencies); // A, B, C, D all have dependencies

        // Find dependencies for Package A
        $packageADeps = $this->findDependenciesForRef($dependencies, 'Package-A');
        $this->assertNotNull($packageADeps);
        $this->assertCount(2, $packageADeps['dependsOn']);
        $this->assertContains('Package-B', $packageADeps['dependsOn']);
        $this->assertContains('Package-C', $packageADeps['dependsOn']);

        // Find dependencies for Package B (contains relationships)
        $packageBDeps = $this->findDependenciesForRef($dependencies, 'Package-B');
        $this->assertNotNull($packageBDeps);
        $this->assertCount(2, $packageBDeps['dependsOn']);
        $this->assertContains('Package-D', $packageBDeps['dependsOn']);
        $this->assertContains('Package-E', $packageBDeps['dependsOn']);

        // Find dependencies for Package C (static link)
        $packageCDeps = $this->findDependenciesForRef($dependencies, 'Package-C');
        $this->assertNotNull($packageCDeps);
        $this->assertCount(1, $packageCDeps['dependsOn']);
        $this->assertContains('Package-F', $packageCDeps['dependsOn']);

        // Find dependencies for Package D (dynamic link)
        $packageDDeps = $this->findDependenciesForRef($dependencies, 'Package-D');
        $this->assertNotNull($packageDDeps);
        $this->assertCount(1, $packageDDeps['dependsOn']);
        $this->assertContains('Package-G', $packageDDeps['dependsOn']);
    }

    /**
     * Helper function to find dependencies for a specific ref.
     *
     * @param array<array<string, mixed>> $dependencies Dependencies array
     * @param string $ref Reference to find
     * @return array<string, mixed>|null Found dependencies or null
     */
    private function findDependenciesForRef(array $dependencies, string $ref): ?array
    {
        foreach ($dependencies as $dependency) {
            if ($dependency['ref'] === $ref) {
                return $dependency;
            }
        }
        return null;
    }

    /**
     * Test the source and target formats of the transformer.
     */
    public function testGetSourceAndTargetFormats(): void
    {
        $this->assertEquals('SPDX', $this->transformer->getSourceFormat());
        $this->assertEquals('CycloneDX', $this->transformer->getTargetFormat());
    }
}