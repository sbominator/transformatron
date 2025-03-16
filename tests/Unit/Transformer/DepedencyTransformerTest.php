<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\DependencyTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for DependencyTransformer class.
 */
class DependencyTransformerTest extends TestCase
{
    /**
     * @var DependencyTransformer
     */
    private DependencyTransformer $transformer;

    /**
     * @var SpdxIdTransformer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $spdxIdTransformer;

    protected function setUp(): void
    {
        $this->spdxIdTransformer = $this->createMock(SpdxIdTransformer::class);
        $this->transformer = new DependencyTransformer($this->spdxIdTransformer);
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
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->transformer->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->transformer->getTargetFormat());
    }

    /**
     * Test the transform method with valid dependencies.
     */
    public function testTransformWithValidDependencies(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        // Create test data
        $sourceData = [
            'dependencies' => [
                [
                    'ref' => 'component-1',
                    'dependsOn' => ['component-2', 'component-3']
                ],
                [
                    'ref' => 'component-2',
                    'dependsOn' => ['component-4']
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Verify results
        $this->assertArrayHasKey('relationships', $result);
        $this->assertCount(3, $result['relationships']);

        // Check that component-1 depends on component-2 and component-3
        $component1Relationships = array_filter($result['relationships'], function ($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-component-1';
        });
        $this->assertCount(2, $component1Relationships);

        // Check that component-2 depends on component-4
        $component2Relationships = array_filter($result['relationships'], function ($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-component-2';
        });
        $this->assertCount(1, $component2Relationships);

        // Check that all relationships are DEPENDS_ON
        foreach ($result['relationships'] as $relationship) {
            $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON, $relationship['relationshipType']);
        }

        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with missing dependencies.
     */
    public function testTransformWithMissingDependencies(): void
    {
        $sourceData = [
            'notDependencies' => []
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid dependencies array in source data', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid dependencies.
     */
    public function testTransformWithInvalidDependencies(): void
    {
        $sourceData = [
            'dependencies' => 'not an array'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid dependencies array in source data', $errors[0]->getMessage());
    }

    /**
     * Test transforming CycloneDX dependencies to SPDX relationships.
     */
    public function testTransformDependenciesToRelationships(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $dependencies = [
            [
                'ref' => 'component-1',
                'dependsOn' => ['component-2', 'component-3']
            ],
            [
                'ref' => 'component-2',
                'dependsOn' => ['component-4']
            ]
        ];

        $warnings = [];
        $relationships = $this->transformer->transformDependenciesToRelationships($dependencies, $warnings);

        // Check structure of returned relationships
        $this->assertCount(3, $relationships);

        // Verify component-1 relationships
        $component1Relationships = array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-component-1';
        });
        $this->assertCount(2, $component1Relationships);

        // Verify component-2 relationships
        $component2Relationships = array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-component-2';
        });
        $this->assertCount(1, $component2Relationships);

        // Check relationship type is always DEPENDS_ON
        foreach ($relationships as $relationship) {
            $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON, $relationship['relationshipType']);
        }

        // Check no warnings
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling duplicate dependencies.
     */
    public function testHandleDuplicateDependencies(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $dependencies = [
            [
                'ref' => 'component-1',
                'dependsOn' => ['component-2']
            ],
            [
                'ref' => 'component-1',
                'dependsOn' => ['component-2']
            ] // Duplicate dependency
        ];

        $warnings = [];
        $relationships = $this->transformer->transformDependenciesToRelationships($dependencies, $warnings);

        // Should only create one relationship
        $this->assertCount(1, $relationships);
        $this->assertEquals('SPDXRef-component-1', $relationships[0]['spdxElementId']);
        $this->assertEquals('SPDXRef-component-2', $relationships[0]['relatedSpdxElement']);
    }

    /**
     * Test handling malformed dependencies.
     */
    public function testHandleMalformedDependencies(): void
    {
        $malformedDependencies = [
            [
                // Missing ref
                'dependsOn' => ['component-2']
            ],
            [
                'ref' => 'component-1'
                // Missing dependsOn
            ],
            [
                'ref' => 'component-1',
                'dependsOn' => 'component-2' // Not an array
            ]
        ];

        $warnings = [];
        $relationships = $this->transformer->transformDependenciesToRelationships($malformedDependencies, $warnings);

        // No valid relationships should be generated
        $this->assertEmpty($relationships);

        // Should have warnings for each malformed dependency
        $this->assertCount(3, $warnings);
        $this->assertStringContainsString('Malformed dependency entry', $warnings[0]);
    }

    /**
     * Test handling empty dependsOn arrays.
     */
    public function testHandleEmptyDependsOn(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $dependencies = [
            [
                'ref' => 'component-1',
                'dependsOn' => [] // Empty dependsOn array
            ]
        ];

        $warnings = [];
        $relationships = $this->transformer->transformDependenciesToRelationships($dependencies, $warnings);

        // No relationships should be generated
        $this->assertEmpty($relationships);
        $this->assertEmpty($warnings); // This is not considered an error
    }

    /**
     * Test generating additional relationships based on component structure.
     */
    public function testGenerateAdditionalRelationships(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $components = [
            [
                'bom-ref' => 'component-1',
                'name' => 'Component 1',
                'components' => [
                    [
                        'bom-ref' => 'component-1-1',
                        'name' => 'Component 1.1'
                    ],
                    [
                        'bom-ref' => 'component-1-2',
                        'name' => 'Component 1.2',
                        'components' => [
                            [
                                'bom-ref' => 'component-1-2-1',
                                'name' => 'Component 1.2.1'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'bom-ref' => 'component-2',
                'name' => 'Component 2'
            ]
        ];

        $documentId = 'SPDXRef-DOCUMENT';
        $warnings = [];

        $relationships = $this->transformer->generateAdditionalRelationships($components, $documentId, $warnings);

        // Check structure of returned relationships
        // 2 DESCRIBES + 3 CONTAINS = 5 relationships
        $this->assertCount(5, $relationships);

        // Check document DESCRIBES relationships - one for each top-level component
        $describesRelationships = array_filter($relationships, function($rel) use ($documentId) {
            return $rel['spdxElementId'] === $documentId &&
                $rel['relationshipType'] === RelationshipTypeEnum::RELATIONSHIP_DESCRIBES;
        });
        $this->assertCount(2, $describesRelationships);

        // Check CONTAINS relationships - one for each nested component
        $containsRelationships = array_filter($relationships, function($rel) {
            return $rel['relationshipType'] === RelationshipTypeEnum::RELATIONSHIP_CONTAINS;
        });
        $this->assertCount(3, $containsRelationships);

        // Check specific parent-child relationship
        $nestedContains = array_filter($containsRelationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-component-1-2' &&
                $rel['relatedSpdxElement'] === 'SPDXRef-component-1-2-1';
        });
        $this->assertCount(1, $nestedContains);
    }

    /**
     * Test sanitizing dependencies with invalid references.
     */
    public function testSanitizeDependencies(): void
    {
        $components = [
            ['bom-ref' => 'component-1'],
            ['bom-ref' => 'component-2'],
            ['bom-ref' => 'component-3']
            // component-4 doesn't exist
        ];

        $dependencies = [
            [
                'ref' => 'component-1',
                'dependsOn' => ['component-2', 'component-3', 'component-4'] // component-4 is invalid
            ],
            [
                'ref' => 'component-4', // Invalid ref
                'dependsOn' => ['component-2']
            ],
            [
                'ref' => 'component-2',
                'dependsOn' => ['component-5'] // All dependencies invalid
            ]
        ];

        $warnings = [];
        $sanitizedDependencies = $this->transformer->sanitizeDependencies($dependencies, $components, $warnings);

        // Should have one valid dependency after sanitization
        $this->assertCount(1, $sanitizedDependencies);

        // Check the sanitized dependency still has valid references
        $this->assertEquals('component-1', $sanitizedDependencies[0]['ref']);
        $this->assertCount(2, $sanitizedDependencies[0]['dependsOn']);
        $this->assertContains('component-2', $sanitizedDependencies[0]['dependsOn']);
        $this->assertContains('component-3', $sanitizedDependencies[0]['dependsOn']);

        // Should have warnings for each invalid reference
        $this->assertCount(4, $warnings);
        $this->assertStringContainsString('component-4', $warnings[0]);
        $this->assertStringContainsString('component-4', $warnings[1]);
        $this->assertStringContainsString('component-5', $warnings[2]);
        $this->assertStringContainsString('component-2', $warnings[3]);
    }

    /**
     * Test processing nested components.
     */
    public function testProcessNestedComponents(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        // Create a test-accessible version of the method
        $method = new \ReflectionMethod(DependencyTransformer::class, 'processNestedComponents');
        $method->setAccessible(true);

        $parentComponent = [
            'bom-ref' => 'parent',
            'name' => 'Parent Component',
            'components' => [
                [
                    'bom-ref' => 'child1',
                    'name' => 'Child 1'
                ],
                [
                    'bom-ref' => 'child2',
                    'name' => 'Child 2',
                    'components' => [
                        [
                            'bom-ref' => 'grandchild1',
                            'name' => 'Grandchild 1'
                        ]
                    ]
                ]
            ]
        ];

        $warnings = [];
        $relationships = $method->invokeArgs($this->transformer, [$parentComponent, &$warnings]);

        // Should generate 3 CONTAINS relationships
        $this->assertCount(3, $relationships);

        // Check parent-child relationships
        $parentToChild1 = $this->findRelationship($relationships, 'SPDXRef-parent', 'SPDXRef-child1');
        $this->assertNotNull($parentToChild1);
        $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_CONTAINS, $parentToChild1['relationshipType']);

        $parentToChild2 = $this->findRelationship($relationships, 'SPDXRef-parent', 'SPDXRef-child2');
        $this->assertNotNull($parentToChild2);
        $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_CONTAINS, $parentToChild2['relationshipType']);

        // Check child-grandchild relationship
        $child2ToGrandchild = $this->findRelationship($relationships, 'SPDXRef-child2', 'SPDXRef-grandchild1');
        $this->assertNotNull($child2ToGrandchild);
        $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_CONTAINS, $child2ToGrandchild['relationshipType']);
    }

    /**
     * Helper method to find a specific relationship.
     *
     * @param array<array<string, string>> $relationships Array of relationships
     * @param string $source Source SPDX ID
     * @param string $target Target SPDX ID
     * @return array<string, string>|null Found relationship or null
     */
    private function findRelationship(array $relationships, string $source, string $target): ?array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['spdxElementId'] === $source &&
                $relationship['relatedSpdxElement'] === $target) {
                return $relationship;
            }
        }
        return null;
    }
}