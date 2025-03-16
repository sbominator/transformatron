<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Transformer\CycloneDxReferenceTransformer;

/**
 * Test cases for CycloneDxReferenceTransformer class.
 */
class CycloneDxReferenceTransformerTest extends TestCase
{
    /**
     * @var CycloneDxReferenceTransformer
     */
    private CycloneDxReferenceTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CycloneDxReferenceTransformer();
    }

    /**
     * Test transformSpecVersion with valid versions.
     */
    public function testTransformSpecVersionWithValidVersions(): void
    {
        $this->assertEquals('SPDX-2.3', $this->transformer->transformSpecVersion('1.4'));
        $this->assertEquals('SPDX-2.2', $this->transformer->transformSpecVersion('1.3'));
        $this->assertEquals('SPDX-2.1', $this->transformer->transformSpecVersion('1.2'));
    }

    /**
     * Test transformSpecVersion with invalid version.
     */
    public function testTransformSpecVersionWithInvalidVersion(): void
    {
        // Should return default version when given an unrecognized version
        $this->assertEquals('SPDX-2.3', $this->transformer->transformSpecVersion('INVALID-VERSION'));
    }

    /**
     * Test transformSerialNumber with various inputs.
     */
    public function testTransformSerialNumber(): void
    {
        // Test with no prefix
        $this->assertEquals('SPDXRef-DOCUMENT', $this->transformer->transformSerialNumber('DOCUMENT'));

        // Test with SPDXRef- prefix already present
        $this->assertEquals('SPDXRef-Package-1', $this->transformer->transformSerialNumber('SPDXRef-Package-1'));

        // Test with empty string
        $this->assertEquals('SPDXRef-', $this->transformer->transformSerialNumber(''));
    }

    /**
     * Test generateSerialNumber without prefix.
     */
    public function testGenerateSerialNumberWithoutPrefix(): void
    {
        $serialNumber = $this->transformer->generateSerialNumber();

        // Check that serial number contains a UUID
        $pattern = '/urn:uuid:[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/';
        $this->assertMatchesRegularExpression($pattern, $serialNumber);
    }

    /**
     * Test generateSerialNumber with prefix.
     */
    public function testGenerateSerialNumberWithPrefix(): void
    {
        $serialNumber = $this->transformer->generateSerialNumber('component-1');

        // Check that serial number starts with the provided prefix
        $this->assertStringStartsWith('component-1-', $serialNumber);

        // Check that serial number contains a UUID after the prefix
        $pattern = '/component-1-urn:uuid:[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/';
        $this->assertMatchesRegularExpression($pattern, $serialNumber);
    }

    /**
     * Test isValidReference with valid references.
     */
    public function testIsValidReferenceWithValidReferences(): void
    {
        $this->assertTrue($this->transformer->isValidReference('component-1'));
        $this->assertTrue($this->transformer->isValidReference('pkg:npm/lodash@4.17.21'));
        $this->assertTrue($this->transformer->isValidReference('urn:uuid:3e671687-395b-41f5-a30f-a58921a69b79'));
    }

    /**
     * Test isValidReference with invalid references.
     */
    public function testIsValidReferenceWithInvalidReferences(): void
    {
        $this->assertFalse($this->transformer->isValidReference('component 1')); // Contains space
    }

    /**
     * Test formatAsReference with various inputs.
     */
    public function testFormatAsReference(): void
    {
        // Already valid reference
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component-1'));

        // Contains spaces
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component 1'));

        // Contains invalid characters
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component-1'));

        // Empty string
        $this->assertEquals('', $this->transformer->formatAsReference(''));
    }
}