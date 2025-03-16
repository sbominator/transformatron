<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\CycloneDxMetadataTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for CycloneDxMetadataTransformer class.
 */
class CycloneDxMetadataTransformerTest extends TestCase
{
    /**
     * @var CycloneDxMetadataTransformer
     */
    private CycloneDxMetadataTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CycloneDxMetadataTransformer();
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
     * Test the transform method with valid metadata.
     */
    public function testTransformWithValidMetadata(): void
    {
        $sourceData = [
            'metadata' => [
                'timestamp' => '2023-01-15T12:03:28Z',
                'tools' => [
                    [
                        'vendor' => 'Test',
                        'name' => 'Generator',
                        'version' => '1.0.0'
                    ]
                ],
                'authors' => [
                    [
                        'name' => 'Jane Doe',
                        'email' => 'jane.doe@example.com'
                    ]
                ],
                'component' => [
                    'name' => 'TestComponent',
                    'version' => '1.0.0'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Check result structure
        $this->assertArrayHasKey('creationInfo', $result);
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);

        // Check creationInfo fields
        $creationInfo = $result['creationInfo'];
        $this->assertEquals('2023-01-15T12:03:28Z', $creationInfo['created']);
        $this->assertCount(2, $creationInfo['creators']);
        $this->assertStringContainsString('Tool: TestGenerator-1.0.0', $creationInfo['creators'][0]);
        $this->assertStringContainsString('Person: Jane Doe (jane.doe@example.com)', $creationInfo['creators'][1]);
        $this->assertStringContainsString('SBOM for TestComponent version 1.0.0', $creationInfo['comment']);
    }

    /**
     * Test the transform method with missing metadata.
     */
    public function testTransformWithMissingMetadata(): void
    {
        $sourceData = [
            'notMetadata' => []
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid metadata in source data', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid metadata.
     */
    public function testTransformWithInvalidMetadata(): void
    {
        $sourceData = [
            'metadata' => 'not an array'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid metadata in source data', $errors[0]->getMessage());
    }

    /**
     * Test transformMetadata with complete metadata.
     */
    public function testTransformMetadataWithCompleteData(): void
    {
        $metadata = [
            'timestamp' => '2023-01-15T12:03:28Z',
            'tools' => [
                [
                    'vendor' => 'Test',
                    'name' => 'Generator',
                    'version' => '1.0.0'
                ]
            ],
            'authors' => [
                [
                    'name' => 'Jane Doe',
                    'email' => 'jane.doe@example.com'
                ]
            ],
            'component' => [
                'name' => 'TestComponent',
                'version' => '1.0.0'
            ]
        ];

        $creationInfo = $this->transformer->transformMetadata($metadata);

        // Check timestamp
        $this->assertEquals('2023-01-15T12:03:28Z', $creationInfo['created']);

        // Check creators
        $this->assertCount(2, $creationInfo['creators']);
        $this->assertEquals('Tool: TestGenerator-1.0.0', $creationInfo['creators'][0]);
        $this->assertEquals('Person: Jane Doe (jane.doe@example.com)', $creationInfo['creators'][1]);

        // Check component info in comment
        $this->assertEquals('SBOM for TestComponent version 1.0.0', $creationInfo['comment']);
    }

    /**
     * Test transformMetadata with minimal metadata.
     */
    public function testTransformMetadataWithMinimalData(): void
    {
        $metadata = [
            'timestamp' => '2023-01-15T12:03:28Z'
        ];

        $creationInfo = $this->transformer->transformMetadata($metadata);

        // Check timestamp
        $this->assertEquals('2023-01-15T12:03:28Z', $creationInfo['created']);

        // Check default creator
        $this->assertCount(1, $creationInfo['creators']);
        $this->assertEquals('Tool: SBOMinator-Converter-1.0', $creationInfo['creators'][0]);
    }

    /**
     * Test createDefaultCreationInfo method.
     */
    public function testCreateDefaultCreationInfo(): void
    {
        $creationInfo = $this->transformer->createDefaultCreationInfo();

        $this->assertArrayHasKey('created', $creationInfo);
        $this->assertArrayHasKey('creators', $creationInfo);
        $this->assertCount(1, $creationInfo['creators']);
        $this->assertEquals('Tool: SBOMinator-Converter-1.0', $creationInfo['creators'][0]);
    }

    /**
     * Test converting tools to creators.
     */
    public function testConvertToolsToCreators(): void
    {
        $tools = [
            [
                'vendor' => 'Test',
                'name' => 'Tool1',
                'version' => '1.0'
            ],
            [
                'name' => 'Tool2'
                // Missing vendor and version
            ],
            [
                'vendor' => 'Another',
                // Missing name
                'version' => '2.0'
            ]
        ];

        // Use reflection to access private method
        $method = new \ReflectionMethod(CycloneDxMetadataTransformer::class, 'convertToolsToCreators');
        $method->setAccessible(true);

        $creators = $method->invoke($this->transformer, $tools);

        // Should have 2 valid creators (one for Tool1, one for Tool2)
        $this->assertCount(2, $creators);
        $this->assertEquals('Tool: TestTool1-1.0', $creators[0]);
        $this->assertEquals('Tool: Tool2-1.0', $creators[1]);
    }

    /**
     * Test converting authors to creators.
     */
    public function testConvertAuthorsToCreators(): void
    {
        $authors = [
            [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com'
            ],
            [
                'name' => 'John Smith'
                // Missing email
            ],
            [
                // Missing name
                'email' => 'unknown@example.com'
            ]
        ];

        // Use reflection to access private method
        $method = new \ReflectionMethod(CycloneDxMetadataTransformer::class, 'convertAuthorsToCreators');
        $method->setAccessible(true);

        $creators = $method->invoke($this->transformer, $authors);

        // Should have 2 valid creators (Jane and John)
        $this->assertCount(2, $creators);
        $this->assertEquals('Person: Jane Doe (jane@example.com)', $creators[0]);
        $this->assertEquals('Person: John Smith', $creators[1]);
    }
}