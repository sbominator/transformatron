<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\RelationshipTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

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
     * Test that the transformer implements the TransformerInterface.
     */
    public function testImplementsTransformerInterface(): void
    {
        $this->assertInstanceOf(TransformerInterface::class, $this->transformer);
    }

    /**
     * Test the source and target formats of the transformer.
     */
    public function testGetSourceAndTargetFormats(): void
    {
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->transformer->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->transformer->getTargetFormat());
    }

    /**
     * Test the transform method with valid relationships.
     */
    public function testTransformWithValidRelationships(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        // Create test data
        $sourceData = [
            'relationships' => [
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
                [
                    'spdxElementId' => 'SPDXRef-Package-B',
                    'relatedSpdxElement' => 'SPDXRef-Package-D',
                    'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Verify results
        $this->assertArrayHasKey('dependencies', $result);
        $this->assertCount(2, $result['dependencies']);

        // Find Package-A dependency
        $packageADep = $this->findDependencyByRef($result['dependencies'], 'Package-A');
        $this->assertNotNull($packageADep);
        $this->assertCount(2, $packageADep['dependsOn']);
        $this->assertContains('Package-B', $packageADep['dependsOn']);
        $this->assertContains('Package-C', $packageADep['dependsOn']);

        // Find Package-B dependency
        $packageBDep = $this->findDependencyByRef($result['dependencies'], 'Package-B');
        $this->assertNotNull($packageBDep);
        $this->assertCount(1, $packageBDep['dependsOn']);
        $this->assertContains('Package-D', $packageBDep['dependsOn']);

        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with circular dependencies.
     */
    public function testTransformWithCircularDependencies(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        // Create test data with circular dependency: A -> B -> C -> A
        $sourceData = [
            'relationships' => [
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
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Transformation should still succeed with warnings
        $this->assertArrayHasKey('dependencies', $result);
        $this->assertCount(3, $result['dependencies']);

        // Should have warning about circular dependency
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Circular dependency detected', $warnings[0]);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with missing relationships.
     */
    public function testTransformWithMissingRelationships(): void
    {
        $sourceData = [
            'notRelationships' => []
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid relationships array in source data', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid relationships.
     */
    public function testTransformWithInvalidRelationships(): void
    {
        $sourceData = [
            'relationships' => 'not an array'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid relationships array in source data', $errors[0]->getMessage());
    }

    /**
     * Test transforming SPDX relationships to CycloneDX dependencies.
     */
    public function testTransformRelationshipsToDependencies(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
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
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-D',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // Should have two dependencies (Package-A and Package-B)
        $this->assertCount(2, $dependencies);

        // Find Package-A dependency
        $packageADep = $this->findDependencyByRef($dependencies, 'Package-A');
        $this->assertNotNull($packageADep);
        $this->assertCount(2, $packageADep['dependsOn']);
        $this->assertContains('Package-B', $packageADep['dependsOn']);
        $this->assertContains('Package-C', $packageADep['dependsOn']);

        // Find Package-B dependency
        $packageBDep = $this->findDependencyByRef($dependencies, 'Package-B');
        $this->assertNotNull($packageBDep);
        $this->assertCount(1, $packageBDep['dependsOn']);
        $this->assertContains('Package-D', $packageBDep['dependsOn']);
    }

    /**
     * Test handling different relationship types.
     */
    public function testHandleDifferentRelationshipTypes(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DYNAMIC_LINK
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-C',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_STATIC_LINK
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-D',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-E',
                'relationshipType' => 'UNKNOWN_RELATIONSHIP_TYPE'
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // Should have one dependency with 3 dependsOn entries (UNKNOWN_RELATIONSHIP_TYPE is ignored)
        $this->assertCount(1, $dependencies);
        $this->assertEquals('Package-A', $dependencies[0]['ref']);
        $this->assertCount(3, $dependencies[0]['dependsOn']);
        $this->assertContains('Package-B', $dependencies[0]['dependsOn']); // DYNAMIC_LINK
        $this->assertContains('Package-C', $dependencies[0]['dependsOn']); // STATIC_LINK
        $this->assertContains('Package-D', $dependencies[0]['dependsOn']); // CONTAINS
    }

    /**
     * Test handling duplicate relationships.
     */
    public function testHandleDuplicateRelationships(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ] // Duplicate relationship
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($relationships, $warnings);

        // Should only create one dependency with one dependsOn
        $this->assertCount(1, $dependencies);
        $this->assertEquals('Package-A', $dependencies[0]['ref']);
        $this->assertCount(1, $dependencies[0]['dependsOn']);
        $this->assertContains('Package-B', $dependencies[0]['dependsOn']);
    }

    /**
     * Test handling malformed relationships.
     */
    public function testHandleMalformedRelationships(): void
    {
        $malformedRelationships = [
            [
                // Missing spdxElementId
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                // Missing relatedSpdxElement
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B'
                // Missing relationshipType
            ]
        ];

        $warnings = [];
        $dependencies = $this->transformer->transformRelationshipsToDependencies($malformedRelationships, $warnings);

        // No valid dependencies should be generated
        $this->assertEmpty($dependencies);

        // Should have warnings for each malformed relationship
        $this->assertCount(3, $warnings);
        $this->assertStringContainsString('Malformed relationship entry', $warnings[0]);
    }

    /**
     * Test finding relationships for a specific element.
     */
    public function testFindRelationshipsForElement(): void
    {
        $relationships = [
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-B',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-A',
                'relatedSpdxElement' => 'SPDXRef-Package-C',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_CONTAINS
            ],
            [
                'spdxElementId' => 'SPDXRef-Package-B',
                'relatedSpdxElement' => 'SPDXRef-Package-D',
                'relationshipType' => RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
            ]
        ];

        // Find all relationships for Package-A
        $packageARelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-Package-A'
        );
        $this->assertCount(2, $packageARelationships);

        // Find only DEPENDS_ON relationships for Package-A
        $packageADependsOnRelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-Package-A',
            RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON
        );
        $this->assertCount(1, $packageADependsOnRelationships);

        // Find relationships for non-existent element
        $nonExistentRelationships = $this->transformer->findRelationshipsForElement(
            $relationships,
            'SPDXRef-NonExistent'
        );
        $this->assertEmpty($nonExistentRelationships);
    }

    /**
     * Test checking for circular dependencies.
     */
    public function testCheckForCircularDependencies(): void
    {
        $circularRelationships = [
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

        $warnings = $this->transformer->checkForCircularDependencies($circularRelationships);

        // Should have a warning about the circular dependency
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
        // Setup mock expectations
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
        $packageADeps = $this->findDependencyByRef($dependencies, 'Package-A');
        $this->assertNotNull($packageADeps);
        $this->assertCount(2, $packageADeps['dependsOn']);
        $this->assertContains('Package-B', $packageADeps['dependsOn']);
        $this->assertContains('Package-C', $packageADeps['dependsOn']);

        // Find dependencies for Package B (contains relationships)
        $packageBDeps = $this->findDependencyByRef($dependencies, 'Package-B');
        $this->assertNotNull($packageBDeps);
        $this->assertCount(2, $packageBDeps['dependsOn']);
        $this->assertContains('Package-D', $packageBDeps['dependsOn']);
        $this->assertContains('Package-E', $packageBDeps['dependsOn']);

        // Find dependencies for Package C (static link)
        $packageCDeps = $this->findDependencyByRef($dependencies, 'Package-C');
        $this->assertNotNull($packageCDeps);
        $this->assertCount(1, $packageCDeps['dependsOn']);
        $this->assertContains('Package-F', $packageCDeps['dependsOn']);

        // Find dependencies for Package D (dynamic link)
        $packageDDeps = $this->findDependencyByRef($dependencies, 'Package-D');
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
    private function findDependencyByRef(array $dependencies, string $ref): ?array
    {
        foreach ($dependencies as $dependency) {
            if ($dependency['ref'] === $ref) {
                return $dependency;
            }
        }
        return null;
    }
}