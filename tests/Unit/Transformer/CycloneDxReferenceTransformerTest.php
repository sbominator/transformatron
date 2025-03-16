<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Transformer\CycloneDxReferenceTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

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
     * Test that the transformer implements the TransformerInterface.
     */
    public function testImplementsTransformerInterface(): void
    {
        $this->assertInstanceOf(TransformerInterface::class, $this->transformer);
    }

    /**
     * Test the source and target formats.
     */
    public function testSourceAndTargetFormats(): void
    {
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->transformer->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->transformer->getTargetFormat());
    }

    /**
     * Test transform method with complete source data.
     */
    public function testTransformWithCompleteData(): void
    {
        $sourceData = [
            'specVersion' => '1.4',
            'serialNumber' => 'urn:uuid:3e671687-395b-41f5-a30f-a58921a69b79'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('spdxVersion', $result);
        $this->assertEquals('SPDX-2.3', $result['spdxVersion']);
        $this->assertArrayHasKey('SPDXID', $result);
        $this->assertStringStartsWith('SPDXRef-', $result['SPDXID']);
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transform method with warnings for unknown fields.
     */
    public function testTransformWithUnknownFields(): void
    {
        $sourceData = [
            'specVersion' => '1.4',
            'serialNumber' => 'urn:uuid:3e671687-395b-41f5-a30f-a58921a69b79',
            'unknownField1' => 'value1',
            'unknownField2' => 'value2'
        ];

        $warnings = [];
        $errors = [];
        $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('Unknown or unmapped CycloneDX reference field', $warnings[0]);
        $this->assertStringContainsString('Unknown or unmapped CycloneDX reference field', $warnings[1]);
        $this->assertEmpty($errors);
    }

    /**
     * Test transform method with partial data.
     */
    public function testTransformWithPartialData(): void
    {
        $sourceData = [
            'specVersion' => '1.4'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('spdxVersion', $result);
        $this->assertEquals('SPDX-2.3', $result['spdxVersion']);
        $this->assertArrayNotHasKey('SPDXID', $result);
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transform method with invalid spec version.
     */
    public function testTransformWithInvalidSpecVersion(): void
    {
        $sourceData = [
            'specVersion' => 'invalid-version'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('spdxVersion', $result);
        $this->assertEquals('SPDX-2.3', $result['spdxVersion']); // Default version
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test spec version transformation.
     */
    public function testTransformSpecVersion(): void
    {
        $this->assertEquals('SPDX-2.3', $this->transformer->transformSpecVersion('1.4'));
        $this->assertEquals('SPDX-2.2', $this->transformer->transformSpecVersion('1.3'));
        $this->assertEquals('SPDX-2.1', $this->transformer->transformSpecVersion('1.2'));
        $this->assertEquals('SPDX-2.3', $this->transformer->transformSpecVersion('INVALID-VERSION'));
    }

    /**
     * Test serial number transformation.
     */
    public function testTransformSerialNumber(): void
    {
        // Test with UUID serial number
        $serialNumber = 'urn:uuid:3e671687-395b-41f5-a30f-a58921a69b79';
        $result = $this->transformer->transformSerialNumber($serialNumber);
        $this->assertStringStartsWith('SPDXRef-', $result);
        $this->assertStringContainsString('3e671687-395b-41f5-a30f-a58921a69b79', $result);

        // Test with already prefixed serial number
        $this->assertEquals(
            'SPDXRef-DOCUMENT',
            $this->transformer->transformSerialNumber('SPDXRef-DOCUMENT')
        );

        // Test with non-standard serial number
        $this->assertEquals(
            'SPDXRef-custom-serial-number',
            $this->transformer->transformSerialNumber('custom serial number')
        );
    }

    /**
     * Test valid reference checking.
     */
    public function testIsValidReference(): void
    {
        $this->assertTrue($this->transformer->isValidReference('component-1'));
        $this->assertTrue($this->transformer->isValidReference('pkg:npm/lodash@4.17.21'));
        $this->assertTrue($this->transformer->isValidReference('urn:uuid:3e671687-395b-41f5-a30f-a58921a69b79'));

        $this->assertFalse($this->transformer->isValidReference('component 1')); // Contains space
    }

    /**
     * Test reference formatting.
     */
    public function testFormatAsReference(): void
    {
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component-1'));
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component 1'));
        $this->assertEquals('component-1', $this->transformer->formatAsReference('component#1'));
        $this->assertEquals('', $this->transformer->formatAsReference(''));
    }

    /**
     * Test generating serial number.
     */
    public function testGenerateSerialNumber(): void
    {
        $serialNumber = $this->transformer->generateSerialNumber();
        $this->assertMatchesRegularExpression('/^urn:uuid:[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $serialNumber);

        $serialNumber = $this->transformer->generateSerialNumber('component-1');
        $this->assertMatchesRegularExpression('/^component-1-urn:uuid:[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $serialNumber);
    }
}