<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\SpdxMetadataTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for SpdxMetadataTransformer class.
 */
class SpdxMetadataTransformerTest extends TestCase
{
    /**
     * @var SpdxMetadataTransformer
     */
    private SpdxMetadataTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new SpdxMetadataTransformer();
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
     * Test the transform method with valid SPDX creation info.
     */
    public function testTransformWithValidCreationInfo(): void
    {
        $sourceData = [
            'creationInfo' => [
                'created' => '2023-01-15T12:03:28Z',
                'creators' => [
                    'Tool: ExampleTool-1.0',
                    'Person: Jane Doe (jane.doe@example.com)',
                    'Organization: Example Org'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Check that result contains metadata
        $this->assertArrayHasKey('metadata', $result);
        $metadata = $result['metadata'];

        // Check that timestamp was copied
        $this->assertEquals('2023-01-15T12:03:28Z', $metadata['timestamp']);

        // Check that tools were extracted correctly
        $this->assertNotEmpty($metadata['tools']);
        $this->assertTrue(isset($metadata['tools'][0]));
        $this->assertArrayHasKey('name', $metadata['tools'][0]);
        $this->assertEquals('ExampleTool', $metadata['tools'][0]['name']);
        $this->assertEquals('1.0', $metadata['tools'][0]['version']);

        // Check that authors were extracted correctly
        $this->assertArrayHasKey('authors', $metadata);
        $this->assertNotEmpty($metadata['authors']);
        $this->assertTrue(isset($metadata['authors'][0]));
        $this->assertArrayHasKey('name', $metadata['authors'][0]);
        $this->assertEquals('Jane Doe', $metadata['authors'][0]['name']);
        $this->assertEquals('jane.doe@example.com', $metadata['authors'][0]['email']);

        // Check that there are no warnings or errors
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with missing creation info.
     */
    public function testTransformWithMissingCreationInfo(): void
    {
        $sourceData = [
            // No creationInfo
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Check that result contains default metadata
        $this->assertArrayHasKey('metadata', $result);
        $metadata = $result['metadata'];

        // Check default tool was added
        $this->assertNotEmpty($metadata['tools']);
        $this->assertCount(1, $metadata['tools']);
        $this->assertEquals('SBOMinator', $metadata['tools'][0]['vendor']);
        $this->assertEquals('Converter', $metadata['tools'][0]['name']);

        // Should have a warning about missing creationInfo
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Missing creationInfo', $warnings[0]);
        $this->assertEmpty($errors);
    }

    /**
     * Test error handling in transform method.
     */
    public function testTransformWithErrorHandling(): void
    {
        // Create a partial mock to simulate an exception in transformCreationInfo
        $mockTransformer = $this->getMockBuilder(SpdxMetadataTransformer::class)
            ->onlyMethods(['transformCreationInfo'])
            ->getMock();

        // Set up the mock to throw an exception
        $mockTransformer->method('transformCreationInfo')
            ->willThrowException(new \Exception('Test exception'));

        $sourceData = [
            'creationInfo' => [
                'created' => '2023-01-15T12:03:28Z',
                'creators' => []
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $mockTransformer->transform($sourceData, $warnings, $errors);

        // Check that result contains default metadata as fallback
        $this->assertArrayHasKey('metadata', $result);

        // Should have an error
        $this->assertNotEmpty($errors);
        $this->assertInstanceOf(ConversionError::class, $errors[0]);
        $this->assertStringContainsString('Test exception', $errors[0]->getMessage());
        $this->assertEmpty($warnings);
    }

    /**
     * Test transformCreationInfo with complete data.
     */
    public function testTransformCreationInfoWithCompleteData(): void
    {
        $creationInfo = [
            'created' => '2023-01-15T12:03:28Z',
            'creators' => [
                'Tool: ExampleTool-1.0',
                'Person: Jane Doe (jane.doe@example.com)',
                'Organization: Example Org'
            ]
        ];

        $metadata = $this->transformer->transformCreationInfo($creationInfo);

        // Check that timestamp was copied
        $this->assertEquals('2023-01-15T12:03:28Z', $metadata['timestamp']);

        // Check that tools were extracted correctly
        $this->assertNotEmpty($metadata['tools']);
        $this->assertTrue(isset($metadata['tools'][0]));
        $this->assertArrayHasKey('name', $metadata['tools'][0]);
        $this->assertEquals('ExampleTool', $metadata['tools'][0]['name']);
        $this->assertEquals('1.0', $metadata['tools'][0]['version']);

        // Check that authors were extracted correctly
        $this->assertArrayHasKey('authors', $metadata);
        $this->assertNotEmpty($metadata['authors']);
        $this->assertTrue(isset($metadata['authors'][0]));
        $this->assertArrayHasKey('name', $metadata['authors'][0]);
        $this->assertEquals('Jane Doe', $metadata['authors'][0]['name']);
        $this->assertEquals('jane.doe@example.com', $metadata['authors'][0]['email']);
    }

    /**
     * Test transformCreationInfo with missing creators.
     */
    public function testTransformCreationInfoWithMissingCreators(): void
    {
        $creationInfo = [
            'created' => '2023-01-15T12:03:28Z'
            // No creators
        ];

        $metadata = $this->transformer->transformCreationInfo($creationInfo);

        // Check that timestamp was copied
        $this->assertEquals('2023-01-15T12:03:28Z', $metadata['timestamp']);

        // Check that default tool was added
        $this->assertNotEmpty($metadata['tools']);
        $this->assertCount(1, $metadata['tools']);
        $this->assertEquals('SBOMinator', $metadata['tools'][0]['vendor']);
        $this->assertEquals('Converter', $metadata['tools'][0]['name']);
    }

    /**
     * Test transformCreationInfo with empty creators array.
     */
    public function testTransformCreationInfoWithEmptyCreatorsArray(): void
    {
        $creationInfo = [
            'created' => '2023-01-15T12:03:28Z',
            'creators' => []
        ];

        $metadata = $this->transformer->transformCreationInfo($creationInfo);

        // Check that timestamp was copied
        $this->assertEquals('2023-01-15T12:03:28Z', $metadata['timestamp']);

        // Check that default tool was added
        $this->assertNotEmpty($metadata['tools']);
        $this->assertCount(1, $metadata['tools']);
        $this->assertEquals('SBOMinator', $metadata['tools'][0]['vendor']);
        $this->assertEquals('Converter', $metadata['tools'][0]['name']);
    }

    /**
     * Test transformCreationInfo with invalid creator format.
     */
    public function testTransformCreationInfoWithInvalidCreatorFormat(): void
    {
        $creationInfo = [
            'created' => '2023-01-15T12:03:28Z',
            'creators' => [
                'Invalid format',
                'Tool: ExampleTool-1.0',
            ]
        ];

        $metadata = $this->transformer->transformCreationInfo($creationInfo);

        // Check that timestamp was copied
        $this->assertEquals('2023-01-15T12:03:28Z', $metadata['timestamp']);

        // Check that only valid tool was extracted
        $this->assertNotEmpty($metadata['tools']);
        $this->assertCount(1, $metadata['tools']);
        $this->assertEquals('ExampleTool', $metadata['tools'][0]['name']);
    }

    /**
     * Test createDefaultMetadata.
     */
    public function testCreateDefaultMetadata(): void
    {
        $metadata = $this->transformer->createDefaultMetadata();

        // Check that timestamp is present
        $this->assertArrayHasKey('timestamp', $metadata);

        // Check that tools array contains default tool
        $this->assertArrayHasKey('tools', $metadata);
        $this->assertNotEmpty($metadata['tools']);
        $this->assertEquals('SBOMinator', $metadata['tools'][0]['vendor']);
        $this->assertEquals('Converter', $metadata['tools'][0]['name']);
        $this->assertEquals('1.0.0', $metadata['tools'][0]['version']);
    }
}