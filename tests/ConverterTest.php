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
     * Test SPDX to CycloneDX conversion with field mapping
     */
    public function testConvertSpdxToCyclonedxFieldMapping(): void
    {
        $converter = new Converter();
        
        // Create SPDX input with known fields for mapping
        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test',
            'creationInfo' => [
                'created' => '2023-01-01T12:00:00Z',
                'creators' => [
                    'Tool: ExampleTool-1.0'
                ]
            ],
            'nonMappableField' => 'This field has no direct mapping',
            'anotherCustomField' => 'Another unmapped field'
        ]);
        
        // Perform conversion
        $result = $converter->convertSpdxToCyclonedx($spdxJson);
        
        // Assert basic result properties
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('CycloneDX', $result->getFormat());
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Test mapped fields
        $this->assertEquals('1.4', $content['specVersion']); // Transformed from SPDX-2.3
        $this->assertEquals('CC0-1.0', $content['license']); // Direct mapping
        $this->assertEquals('test-document', $content['name']); // Direct mapping
        $this->assertEquals('DOCUMENT', $content['serialNumber']); // Transformed from SPDXRef-DOCUMENT
        $this->assertEquals('https://example.com/test', $content['documentNamespace']); // Direct mapping
        
        // Test that warnings were generated for unmapped fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);
        
        // Check for specific warning messages
        $this->assertTrue(in_array('Unknown or unmapped SPDX field: nonMappableField', $warnings));
        $this->assertTrue(in_array('Unknown or unmapped SPDX field: anotherCustomField', $warnings));
    }
    
    /**
     * Test CycloneDX to SPDX conversion with field mapping
     */
    public function testConvertCyclonedxToSpdxFieldMapping(): void
    {
        $converter = new Converter();
        
        // Create CycloneDX input with known fields for mapping
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'serialNumber' => 'DOCUMENT-123',
            'name' => 'test-cyclonedx-document',
            'metadata' => [
                'timestamp' => '2023-02-01T12:00:00Z',
                'tools' => [
                    [
                        'vendor' => 'Example',
                        'name' => 'Tool',
                        'version' => '2.0'
                    ]
                ]
            ],
            'unmappedField' => 'This field has no mapping',
            'components' => [] // Known field but not mapped in our implementation
        ]);
        
        // Perform conversion
        $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);
        
        // Assert basic result properties
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('SPDX', $result->getFormat());
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Test mapped fields
        $this->assertEquals('SPDX-2.3', $content['spdxVersion']); // Transformed from 1.4
        $this->assertEquals('test-cyclonedx-document', $content['name']); // Direct mapping
        $this->assertEquals('SPDXRef-DOCUMENT-123', $content['SPDXID']); // Transformed from DOCUMENT-123
        
        // Test that metadata was properly transformed to creationInfo
        $this->assertArrayHasKey('creationInfo', $content);
        $this->assertEquals('2023-02-01T12:00:00Z', $content['creationInfo']['created']);
        $this->assertContains('Tool: ExampleTool-2.0', $content['creationInfo']['creators']);
        
        // Test that warnings were generated for unmapped fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);
        
        // Check for specific warning messages
        $this->assertTrue(in_array('Unknown or unmapped CycloneDX field: unmappedField', $warnings));
        $this->assertTrue(in_array('Unknown or unmapped CycloneDX field: components', $warnings));
    }
    
    /**
     * Test ConversionResult with warnings functionality
     */
    public function testConversionResultWarnings(): void
    {
        // Test empty warnings
        $result = new ConversionResult('content', 'format');
        $this->assertEmpty($result->getWarnings());
        $this->assertFalse($result->hasWarnings());
        
        // Test with initial warnings
        $warnings = ['Warning 1', 'Warning 2'];
        $result = new ConversionResult('content', 'format', $warnings);
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertTrue($result->hasWarnings());
        
        // Test adding warnings
        $result = new ConversionResult('content', 'format');
        $result->addWarning('New warning');
        $this->assertEquals(['New warning'], $result->getWarnings());
        $this->assertTrue($result->hasWarnings());
        
        // Test JSON serialization includes warnings
        $result = new ConversionResult(json_encode(['test' => 'value']), 'format', ['Warning']);
        $serialized = json_encode($result);
        $deserialized = json_decode($serialized, true);
        $this->assertArrayHasKey('warnings', $deserialized);
        $this->assertEquals(['Warning'], $deserialized['warnings']);
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