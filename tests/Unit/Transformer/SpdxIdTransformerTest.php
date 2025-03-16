<?php

namespace SBOMinator\Transformatron\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;

/**
 * Test cases for SpdxIdTransformer class.
 */
class SpdxIdTransformerTest extends TestCase
{
    /**
     * @var SpdxIdTransformer
     */
    private SpdxIdTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new SpdxIdTransformer();
    }

    /**
     * Test transformSpdxId with various inputs.
     */
    public function testTransformSpdxId(): void
    {
        // Test with SPDXRef- prefix
        $this->assertEquals('DOCUMENT', $this->transformer->transformSpdxId('SPDXRef-DOCUMENT'));

        // Test with already transformed ID
        $this->assertEquals('Package-1', $this->transformer->transformSpdxId('Package-1'));

        // Test with empty string
        $this->assertEquals('', $this->transformer->transformSpdxId(''));
    }

    /**
     * Test transformSpdxVersion with valid versions.
     */
    public function testTransformSpdxVersionWithValidVersions(): void
    {
        $this->assertEquals('1.4', $this->transformer->transformSpdxVersion('SPDX-2.3'));
        $this->assertEquals('1.3', $this->transformer->transformSpdxVersion('SPDX-2.2'));
        $this->assertEquals('1.2', $this->transformer->transformSpdxVersion('SPDX-2.1'));
    }

    /**
     * Test transformSpdxVersion with invalid version.
     */
    public function testTransformSpdxVersionWithInvalidVersion(): void
    {
        // Should return default version when given an unrecognized version
        $this->assertEquals('1.4', $this->transformer->transformSpdxVersion('INVALID-VERSION'));
    }

    /**
     * Test generateDocumentNamespace.
     */
    public function testGenerateDocumentNamespace(): void
    {
        $namespace = $this->transformer->generateDocumentNamespace('Test Document');

        // Check that namespace contains sanitized name
        $this->assertStringContainsString('Test-Document', $namespace);

        // Check that namespace has the correct prefix
        $this->assertStringStartsWith('https://sbominator.example/spdx/', $namespace);

        // Check that namespace contains a unique ID
        $pattern = '/https:\/\/sbominator\.example\/spdx\/Test-Document-[a-f0-9]+/';
        $this->assertMatchesRegularExpression($pattern, $namespace);

        // Test with custom prefix
        $customNamespace = $this->transformer->generateDocumentNamespace('Test', 'https://example.com/');
        $this->assertStringStartsWith('https://example.com/', $customNamespace);
    }

    /**
     * Test isValidSpdxId with valid IDs.
     */
    public function testIsValidSpdxIdWithValidIds(): void
    {
        $this->assertTrue($this->transformer->isValidSpdxId('SPDXRef-DOCUMENT'));
        $this->assertTrue($this->transformer->isValidSpdxId('SPDXRef-Package-1'));
        $this->assertTrue($this->transformer->isValidSpdxId('SPDXRef-1.2.3'));
    }

    /**
     * Test isValidSpdxId with invalid IDs.
     */
    public function testIsValidSpdxIdWithInvalidIds(): void
    {
        $this->assertFalse($this->transformer->isValidSpdxId('DOCUMENT')); // Missing prefix
        $this->assertFalse($this->transformer->isValidSpdxId('SPDXRef- Package')); // Contains space
        $this->assertFalse($this->transformer->isValidSpdxId('SPDXRef-Package$')); // Contains invalid char
    }

    /**
     * Test formatAsSpdxId with various inputs.
     */
    public function testFormatAsSpdxId(): void
    {
        // Already valid ID
        $this->assertEquals('SPDXRef-DOCUMENT', $this->transformer->formatAsSpdxId('SPDXRef-DOCUMENT'));

        // Has prefix but invalid characters
        $this->assertEquals('SPDXRef-Package-1', $this->transformer->formatAsSpdxId('SPDXRef-Package 1'));

        // No prefix and needs sanitization
        $this->assertEquals('SPDXRef-Package-1', $this->transformer->formatAsSpdxId('Package 1'));

        // Empty string
        $this->assertEquals('SPDXRef-', $this->transformer->formatAsSpdxId(''));
    }
}