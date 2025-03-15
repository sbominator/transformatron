<?php

namespace SBOMinator\Converter\Tests;

use PHPUnit\Framework\TestCase;
use SBOMinator\Converter\Converter;
use SBOMinator\Converter\ConversionResult;
use SBOMinator\Converter\ValidationException;
use SBOMinator\Converter\ConversionException;

class ConverterTest extends TestCase
{
    /**
     * Test that all classes can be autoloaded and instantiated
     */
    public function testClassesCanBeInstantiated(): void
    {
        // Test Converter instantiation
        $converter = new Converter();
        $this->assertInstanceOf(Converter::class, $converter);
        
        // Test ConversionResult instantiation
        $result = new ConversionResult('content', 'format');
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('content', $result->getContent());
        $this->assertEquals('format', $result->getFormat());
        
        // Test ValidationException instantiation
        $validationErrors = ['Error 1', 'Error 2'];
        $validationException = new ValidationException('Validation failed', $validationErrors);
        $this->assertInstanceOf(ValidationException::class, $validationException);
        $this->assertEquals('Validation failed', $validationException->getMessage());
        $this->assertEquals($validationErrors, $validationException->getValidationErrors());
        
        // Test ConversionException instantiation
        $conversionException = new ConversionException('Conversion failed', 'SPDX', 'CycloneDX');
        $this->assertInstanceOf(ConversionException::class, $conversionException);
        $this->assertEquals('Conversion failed', $conversionException->getMessage());
        $this->assertEquals('SPDX', $conversionException->getSourceFormat());
        $this->assertEquals('CycloneDX', $conversionException->getTargetFormat());
    }
    
    /**
     * Test JSON decoding with valid JSON
     */
    public function testDecodeJsonWithValidJson(): void
    {
        $converter = new ConverterTestProxy();
        
        // Test with simple array
        $validJson = '["item1", "item2", "item3"]';
        $result = $converter->decodeJsonProxy($validJson);
        $this->assertEquals(['item1', 'item2', 'item3'], $result);
        
        // Test with associative array (object)
        $validObjectJson = '{"key1": "value1", "key2": "value2"}';
        $result = $converter->decodeJsonProxy($validObjectJson);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $result);
        
        // Test with nested structure
        $validNestedJson = '{"name": "test", "items": ["item1", "item2"], "metadata": {"created": "2023-01-01"}}';
        $result = $converter->decodeJsonProxy($validNestedJson);
        $this->assertEquals([
            'name' => 'test', 
            'items' => ['item1', 'item2'], 
            'metadata' => ['created' => '2023-01-01']
        ], $result);
    }
    
    /**
     * Test JSON decoding with invalid JSON
     */
    public function testDecodeJsonWithInvalidJson(): void
    {
        $converter = new ConverterTestProxy();
        
        // Test with invalid JSON (missing closing bracket)
        $invalidJson = '{"key": "value"';
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid JSON:');
        $converter->decodeJsonProxy($invalidJson);
    }
    
    /**
     * Test JSON decoding with non-array JSON
     */
    public function testDecodeJsonWithNonArrayJson(): void
    {
        $converter = new ConverterTestProxy();
        
        // Test with string
        $stringJson = '"just a string"';
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('JSON must decode to an array');
        $converter->decodeJsonProxy($stringJson);
    }
    
    /**
     * Test JSON decoding with empty JSON
     */
    public function testDecodeJsonWithEmptyJson(): void
    {
        $converter = new ConverterTestProxy();
        
        // Test with empty array (valid)
        $emptyArrayJson = '[]';
        $result = $converter->decodeJsonProxy($emptyArrayJson);
        $this->assertEquals([], $result);
        
        // Test with empty object (valid)
        $emptyObjectJson = '{}';
        $result = $converter->decodeJsonProxy($emptyObjectJson);
        $this->assertEquals([], $result);
        
        // Test with empty string (invalid)
        $emptyString = '';
        $this->expectException(ValidationException::class);
        $converter->decodeJsonProxy($emptyString);
    }
    
    /**
     * Test SPDX to CycloneDX conversion
     */
    public function testConvertSpdxToCyclonedx(): void
    {
        $converter = new Converter();
        
        // Minimal valid SPDX
        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ]);
        
        // Perform conversion
        $result = $converter->convertSpdxToCyclonedx($spdxJson);
        
        // Assert results
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('CycloneDX', $result->getFormat());
        
        // Check content is valid JSON
        $content = json_decode($result->getContent(), true);
        $this->assertIsArray($content);
        
        // Verify placeholder content
        $this->assertArrayHasKey('bomFormat', $content);
        $this->assertEquals('CycloneDX', $content['bomFormat']);
        $this->assertArrayHasKey('specVersion', $content);
        $this->assertArrayHasKey('serialNumber', $content);
        $this->assertArrayHasKey('metadata', $content);
        $this->assertArrayHasKey('tools', $content['metadata']);
        $this->assertArrayHasKey('original', $content);
        $this->assertEquals('Converted from SPDX format', $content['original']);
    }
    
    /**
     * Test CycloneDX to SPDX conversion
     */
    public function testConvertCyclonedxToSpdx(): void
    {
        $converter = new Converter();
        
        // Minimal valid CycloneDX
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'serialNumber' => 'test-serial-number',
            'metadata' => [
                'timestamp' => '2023-01-01T00:00:00Z'
            ]
        ]);
        
        // Perform conversion
        $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);
        
        // Assert results
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('SPDX', $result->getFormat());
        
        // Check content is valid JSON
        $content = json_decode($result->getContent(), true);
        $this->assertIsArray($content);
        
        // Verify placeholder content
        $this->assertArrayHasKey('spdxVersion', $content);
        $this->assertEquals('SPDX-2.3', $content['spdxVersion']);
        $this->assertArrayHasKey('dataLicense', $content);
        $this->assertEquals('CC0-1.0', $content['dataLicense']);
        $this->assertArrayHasKey('SPDXID', $content);
        $this->assertArrayHasKey('creationInfo', $content);
        $this->assertArrayHasKey('packages', $content);
        $this->assertArrayHasKey('original', $content);
        $this->assertEquals('Converted from CycloneDX format', $content['original']);
    }
    
    /**
     * Test that ConversionResult implements JsonSerializable
     */
    public function testConversionResultIsJsonSerializable(): void
    {
        // Create a ConversionResult with simple content
        $content = json_encode(['test' => 'value']);
        $result = new ConversionResult($content, 'TestFormat');
        
        // Test serialization
        $serialized = json_encode($result);
        $this->assertIsString($serialized);
        
        // Test deserialization
        $deserialized = json_decode($serialized, true);
        $this->assertIsArray($deserialized);
        
        // Verify structure
        $this->assertArrayHasKey('format', $deserialized);
        $this->assertEquals('TestFormat', $deserialized['format']);
        $this->assertArrayHasKey('content', $deserialized);
        $this->assertIsArray($deserialized['content']);
        $this->assertEquals('value', $deserialized['content']['test']);
    }
}

/**
 * Proxy class to test protected methods in Converter
 */
class ConverterTestProxy extends Converter
{
    /**
     * Proxy method to test protected decodeJson method
     */
    public function decodeJsonProxy(string $json): array
    {
        return $this->decodeJson($json);
    }
}