<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;
use SBOMinator\Transformatron\Transformer\DependencyTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;

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
     * Test with complex dependency network.
     */
    public function testComplexDependencyNetwork(): void
    {
        // Set up mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $dependencies = [
            [
                'ref' => 'app',
                'dependsOn' => ['lib-1', 'lib-2', 'lib-3']
            ],
            [
                'ref' => 'lib-1',
                'dependsOn' => ['lib-4', 'lib-5']
            ],
            [
                'ref' => 'lib-2',
                'dependsOn' => ['lib-5'] // Shared dependency with lib-1
            ],
            [
                'ref' => 'lib-3',
                'dependsOn' => ['lib-6']
            ],
            [
                'ref' => 'lib-4',
                'dependsOn' => ['lib-7']
            ],
            [
                'ref' => 'lib-5',
                'dependsOn' => ['lib-7'] // Creates diamond dependency: lib-1 -> lib-5 -> lib-7 and lib-1 -> lib-4 -> lib-7
            ]
        ];

        $warnings = [];
        $relationships = $this->transformer->transformDependenciesToRelationships($dependencies, $warnings);

        // Check count - should be 1 relationship per dependsOn entry
        // 3 (app) + 2 (lib-1) + 1 (lib-2) + 1 (lib-3) + 1 (lib-4) + 1 (lib-5) = 9 relationships
        $this->assertCount(9, $relationships);

        // Check each source node has the correct number of relationships
        $appRelationships = array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-app';
        });
        $this->assertCount(3, $appRelationships);

        $lib1Relationships = array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-lib-1';
        });
        $this->assertCount(2, $lib1Relationships);

        // Check no duplicates even with diamond dependencies
        $lib5Targets = array_map(function($rel) {
            return $rel['relatedSpdxElement'];
        }, array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-lib-5';
        }));
        $this->assertCount(1, $lib5Targets);
        $this->assertContains('SPDXRef-lib-7', $lib5Targets);

        // Spot check a specific relationship
        $lib3ToLib6 = array_filter($relationships, function($rel) {
            return $rel['spdxElementId'] === 'SPDXRef-lib-3' &&
                $rel['relatedSpdxElement'] === 'SPDXRef-lib-6';
        });
        $this->assertCount(1, $lib3ToLib6);
        $this->assertEquals(RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON, $lib3ToLib6[array_key_first($lib3ToLib6)]['relationshipType']);
    }

    /**
     * Test the source and target formats of the transformer.
     */
    public function testGetSourceAndTargetFormats(): void
    {
        $this->assertEquals('CycloneDX', $this->transformer->getSourceFormat());
        $this->assertEquals('SPDX', $this->transformer->getTargetFormat());
    }
}