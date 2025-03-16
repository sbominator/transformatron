<?php

namespace SBOMinator\Transformatron\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Converter;
use SBOMinator\Transformatron\Exception\ValidationException;

class ConverterTest extends TestCase
{
    /**
     * Test that the Converter maintains the legacy format constants
     */
    public function testFormatConstants(): void
    {
        $this->assertEquals('SPDX', Converter::FORMAT_SPDX);
        $this->assertEquals('CycloneDX', Converter::FORMAT_CYCLONEDX);
    }

    /**
     * Test that the Converter maintains the legacy version constants
     */
    public function testVersionConstants(): void
    {
        $this->assertEquals('SPDX-2.3', Converter::SPDX_VERSION);
        $this->assertEquals('1.4', Converter::CYCLONEDX_VERSION);
    }

    /**
     * Test that the Converter properly instantiates
     */
    public function testConverterCanBeInstantiated(): void
    {
        $converter = new Converter();
        $this->assertInstanceOf(Converter::class, $converter);
    }

    /**
     * Test that the detectFormat method works correctly
     */
    public function testDetectFormat(): void
    {
        $converter = new Converter();

        // Test SPDX detection
        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0'
        ]);

        $this->assertEquals(Converter::FORMAT_SPDX, $converter->detectFormat($spdxJson));

        // Test CycloneDX detection
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4'
        ]);

        $this->assertEquals(Converter::FORMAT_CYCLONEDX, $converter->detectFormat($cyclonedxJson));

        // Test with invalid format
        $invalidJson = json_encode([
            'someField' => 'value'
        ]);

        $this->assertNull($converter->detectFormat($invalidJson));
    }

    /**
     * Test SPDX to CycloneDX conversion using a minimal example
     */
    public function testConvertSpdxToCyclonedx(): void
    {
        $converter = new Converter();

        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ]);

        $result = $converter->convertSpdxToCyclonedx($spdxJson);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(Converter::FORMAT_CYCLONEDX, $result->getFormat());

        $content = $result->getContentAsArray();
        $this->assertEquals('CycloneDX', $content['bomFormat']);
        $this->assertEquals('1.4', $content['specVersion']);
    }

    /**
     * Test CycloneDX to SPDX conversion using a minimal example
     */
    public function testConvertCyclonedxToSpdx(): void
    {
        $converter = new Converter();

        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1
        ]);

        $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(Converter::FORMAT_SPDX, $result->getFormat());

        $content = $result->getContentAsArray();
        $this->assertEquals('SPDX-2.3', $content['spdxVersion']);
        $this->assertEquals('CC0-1.0', $content['dataLicense']);
    }

    /**
     * Test conversion failure with invalid input
     */
    public function testConversionFailureWithInvalidInput(): void
    {
        $mockFactory = $this->createMock(\SBOMinator\Transformatron\Factory\ConverterFactory::class);
        $mockConverter = $this->createMock(\SBOMinator\Transformatron\Converter\ConverterInterface::class);

        // Setup the mock to throw a ValidationException
        $mockConverter->method('convert')
            ->willThrowException(new ValidationException('Missing required fields'));

        $mockFactory->method('createConverter')
            ->willReturn($mockConverter);

        // Create a converter with mocked factory
        $converter = new Converter();

        // Use reflection to replace the factory
        $reflection = new \ReflectionClass($converter);
        $factoryProperty = $reflection->getProperty('converterFactory');
        $factoryProperty->setAccessible(true);
        $factoryProperty->setValue($converter, $mockFactory);

        // Invalid SPDX JSON (missing required fields)
        $invalidSpdxJson = json_encode([
            'name' => 'test-document'
            // Missing required fields
        ]);

        $this->expectException(ValidationException::class);
        $converter->convertSpdxToCyclonedx($invalidSpdxJson);
    }

    /**
     * Test the generic convert method with auto-detection
     */
    public function testConvertWithAutoDetection(): void
    {
        $converter = new Converter();

        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ]);

        $result = $converter->convertSpdxToCyclonedx($spdxJson);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(Converter::FORMAT_CYCLONEDX, $result->getFormat());
    }

    /**
     * Test the generic convert method with explicit source format
     */
    public function testConvertWithExplicitSourceFormat(): void
    {
        $converter = new Converter();

        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ]);

        $result = $converter->convertSpdxToCyclonedx($spdxJson);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(Converter::FORMAT_CYCLONEDX, $result->getFormat());
    }

    /**
     * Test validation exception handling
     */
    public function testValidationExceptionHandling(): void
    {
        $converter = new Converter();

        // Non-JSON input
        $invalidJson = 'Not a JSON string';

        try {
            $converter->convertSpdxToCyclonedx($invalidJson);
            $this->fail('ValidationException expected but not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid JSON', $e->getMessage());
        }
    }
}