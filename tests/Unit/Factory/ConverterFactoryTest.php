<?php

namespace SBOMinator\Transformatron\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Converter\CycloneDxToSpdxConverter;
use SBOMinator\Transformatron\Converter\SpdxToCycloneDxConverter;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Factory\ConverterFactory;

/**
 * Test cases for ConverterFactory class.
 */
class ConverterFactoryTest extends TestCase
{
    /**
     * @var ConverterFactory
     */
    private ConverterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ConverterFactory();
    }

    /**
     * Test creating a converter for SPDX to CycloneDX.
     */
    public function testCreateSpdxToCycloneDxConverter(): void
    {
        $converter = $this->factory->createConverter(
            FormatEnum::FORMAT_SPDX,
            FormatEnum::FORMAT_CYCLONEDX
        );

        $this->assertInstanceOf(SpdxToCycloneDxConverter::class, $converter);
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $converter->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $converter->getTargetFormat());
    }

    /**
     * Test creating a converter for CycloneDX to SPDX.
     */
    public function testCreateCycloneDxToSpdxConverter(): void
    {
        $converter = $this->factory->createConverter(
            FormatEnum::FORMAT_CYCLONEDX,
            FormatEnum::FORMAT_SPDX
        );

        $this->assertInstanceOf(CycloneDxToSpdxConverter::class, $converter);
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $converter->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $converter->getTargetFormat());
    }

    /**
     * Test creating a converter for an invalid format combination.
     */
    public function testCreateConverterWithInvalidFormats(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->createConverter('InvalidSource', 'InvalidTarget');
    }

    /**
     * Test creating a converter for same source and target formats.
     */
    public function testCreateConverterWithSameFormats(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->createConverterFromJson(
            '{"spdxVersion": "SPDX-2.3"}',
            FormatEnum::FORMAT_SPDX
        );
    }

    /**
     * Test creating a converter based on JSON content detection (SPDX).
     */
    public function testCreateConverterFromSpdxJson(): void
    {
        $spdxJson = <<<JSON
{
    "spdxVersion": "SPDX-2.3",
    "dataLicense": "CC0-1.0",
    "SPDXID": "SPDXRef-DOCUMENT",
    "name": "test-document",
    "documentNamespace": "https://example.com/test"
}
JSON;

        $converter = $this->factory->createConverterFromJson($spdxJson, FormatEnum::FORMAT_CYCLONEDX);
        $this->assertInstanceOf(SpdxToCycloneDxConverter::class, $converter);
    }

    /**
     * Test creating a converter based on JSON content detection (CycloneDX).
     */
    public function testCreateConverterFromCycloneDxJson(): void
    {
        $cycloneDxJson = <<<JSON
{
    "bomFormat": "CycloneDX",
    "specVersion": "1.4",
    "version": 1,
    "serialNumber": "urn:uuid:123"
}
JSON;

        $converter = $this->factory->createConverterFromJson($cycloneDxJson, FormatEnum::FORMAT_SPDX);
        $this->assertInstanceOf(CycloneDxToSpdxConverter::class, $converter);
    }

    /**
     * Test creating a converter from invalid JSON.
     */
    public function testCreateConverterFromInvalidJson(): void
    {
        $invalidJson = '{invalid:json}';

        $this->expectException(ValidationException::class);
        $this->factory->createConverterFromJson($invalidJson, FormatEnum::FORMAT_SPDX);
    }

    /**
     * Test creating a converter from undetectable JSON.
     */
    public function testCreateConverterFromUndetectableJson(): void
    {
        $genericJson = '{"foo": "bar"}';

        $this->expectException(ValidationException::class);
        $this->factory->createConverterFromJson($genericJson, FormatEnum::FORMAT_SPDX);
    }

    /**
     * Test the detectJsonFormat method with various inputs.
     */
    public function testDetectJsonFormat(): void
    {
        // Valid SPDX detection
        $spdxJson = '{"spdxVersion": "SPDX-2.3"}';
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->factory->detectJsonFormat($spdxJson));

        // Valid CycloneDX detection
        $cycloneDxJson = '{"bomFormat": "CycloneDX"}';
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->factory->detectJsonFormat($cycloneDxJson));

        // Invalid SPDX version format
        $invalidSpdxJson = '{"spdxVersion": "Invalid"}';
        $this->assertNull($this->factory->detectJsonFormat($invalidSpdxJson));

        // Invalid CycloneDX format value
        $invalidCycloneDxJson = '{"bomFormat": "Invalid"}';
        $this->assertNull($this->factory->detectJsonFormat($invalidCycloneDxJson));

        // Unrecognizable format
        $unrecognizableJson = '{"foo": "bar"}';
        $this->assertNull($this->factory->detectJsonFormat($unrecognizableJson));

        // Invalid JSON
        $invalidJson = '{invalid:json}';
        $this->assertNull($this->factory->detectJsonFormat($invalidJson));
    }

    /**
     * Test creating a converter by conversion path.
     */
    public function testCreateConverterForPath(): void
    {
        // Test SPDX to CycloneDX
        $converter = $this->factory->createConverterForPath('spdx-to-cyclonedx');
        $this->assertInstanceOf(SpdxToCycloneDxConverter::class, $converter);

        // Test CycloneDX to SPDX
        $converter = $this->factory->createConverterForPath('cyclonedx-to-spdx');
        $this->assertInstanceOf(CycloneDxToSpdxConverter::class, $converter);

        // Test invalid path
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->createConverterForPath('invalid-path');
    }

    /**
     * Test factory reset functionality.
     */
    public function testFactoryReset(): void
    {
        // Create a converter and capture its instance
        $converter1 = $this->factory->createConverter(
            FormatEnum::FORMAT_SPDX,
            FormatEnum::FORMAT_CYCLONEDX
        );

        // Create the same converter again - should be the cached instance
        $converter2 = $this->factory->createConverter(
            FormatEnum::FORMAT_SPDX,
            FormatEnum::FORMAT_CYCLONEDX
        );

        // Both should be the same instance
        $this->assertSame($converter1, $converter2);

        // Reset the factory
        $this->factory->reset();

        // Create the converter again - should be a new instance
        $converter3 = $this->factory->createConverter(
            FormatEnum::FORMAT_SPDX,
            FormatEnum::FORMAT_CYCLONEDX
        );

        // Should be a different instance
        $this->assertNotSame($converter2, $converter3);
    }
}