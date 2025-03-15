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
    }
    
    /**
     * Test SPDX packages to CycloneDX components conversion
     */
    public function testConvertSpdxPackagesToCyclonedxComponents(): void
    {
        $converter = new Converter();
        
        // Create SPDX input with packages array
        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test',
            'packages' => [
                [
                    'name' => 'package1',
                    'SPDXID' => 'SPDXRef-Package-1',
                    'versionInfo' => '1.0.0',
                    'downloadLocation' => 'https://example.com/package1',
                    'licenseConcluded' => 'MIT',
                    'description' => 'Test package 1',
                    'checksums' => [
                        [
                            'algorithm' => 'SHA1',
                            'checksumValue' => 'a1b2c3d4e5f6'
                        ],
                        [
                            'algorithm' => 'SHA256',
                            'checksumValue' => '1a2b3c4d5e6f'
                        ]
                    ],
                    'customPackageField' => 'Custom value'
                ],
                [
                    'name' => 'package2',
                    'SPDXID' => 'SPDXRef-Package-2',
                    'versionInfo' => '2.0.0',
                    'licenseDeclared' => 'Apache-2.0',
                    'packageVerificationCode' => [
                        'value' => '123456789abcdef'
                    ]
                ]
            ]
        ]);
        
        // Perform conversion
        $result = $converter->convertSpdxToCyclonedx($spdxJson);
        
        // Assert basic result properties
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('CycloneDX', $result->getFormat());
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Test components array
        $this->assertArrayHasKey('components', $content);
        $this->assertIsArray($content['components']);
        $this->assertCount(2, $content['components']);
        
        // Test first component mapping
        $component1 = $content['components'][0];
        $this->assertEquals('Package-1', $component1['bom-ref']);
        $this->assertEquals('package1', $component1['name']);
        $this->assertEquals('1.0.0', $component1['version']);
        $this->assertEquals('Test package 1', $component1['description']);
        
        // Test license mapping
        $this->assertArrayHasKey('licenses', $component1);
        $this->assertEquals('MIT', $component1['licenses'][0]['license']['id']);
        
        // Test hash mapping
        $this->assertArrayHasKey('hashes', $component1);
        $this->assertCount(2, $component1['hashes']);
        $this->assertEquals('SHA-1', $component1['hashes'][0]['alg']);
        $this->assertEquals('a1b2c3d4e5f6', $component1['hashes'][0]['content']);
        $this->assertEquals('SHA-256', $component1['hashes'][1]['alg']);
        $this->assertEquals('1a2b3c4d5e6f', $component1['hashes'][1]['content']);
        
        // Test second component mapping
        $component2 = $content['components'][1];
        $this->assertEquals('Package-2', $component2['bom-ref']);
        $this->assertEquals('package2', $component2['name']);
        $this->assertEquals('2.0.0', $component2['version']);
        
        // Test verification code mapping to hash
        $this->assertArrayHasKey('hashes', $component2);
        $this->assertEquals('SHA1', $component2['hashes'][0]['alg']);
        $this->assertEquals('123456789abcdef', $component2['hashes'][0]['content']);
        
        // Test that warnings were generated for unmapped fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertTrue(in_array('Unknown or unmapped package field: customPackageField', $warnings));
    }
    
    /**
     * Test CycloneDX components to SPDX packages conversion
     */
    public function testConvertCyclonedxComponentsToSpdxPackages(): void
    {
        $converter = new Converter();
        
        // Create CycloneDX input with components array
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'serialNumber' => 'DOCUMENT-123',
            'components' => [
                [
                    'type' => 'library',
                    'bom-ref' => 'component-1',
                    'name' => 'component1',
                    'version' => '1.0.0',
                    'description' => 'Test component 1',
                    'licenses' => [
                        [
                            'license' => [
                                'id' => 'MIT'
                            ]
                        ]
                    ],
                    'hashes' => [
                        [
                            'alg' => 'SHA-1',
                            'content' => 'abcdef123456'
                        ],
                        [
                            'alg' => 'MD5',
                            'content' => '123456abcdef'
                        ]
                    ],
                    'customComponentField' => 'Custom value'
                ],
                [
                    'type' => 'application',
                    'bom-ref' => 'component-2',
                    'name' => 'component2',
                    'version' => '2.0.0',
                    'supplier' => 'Company X',
                    'unsupportedHashAlg' => [
                        [
                            'alg' => 'UNSUPPORTED-ALG',
                            'content' => '123456'
                        ]
                    ]
                ]
            ]
        ]);
        
        // Perform conversion
        $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);
        
        // Assert basic result properties
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('SPDX', $result->getFormat());
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Test packages array
        $this->assertArrayHasKey('packages', $content);
        $this->assertIsArray($content['packages']);
        $this->assertCount(2, $content['packages']);
        
        // Test first package mapping
        $package1 = $content['packages'][0];
        $this->assertEquals('SPDXRef-component-1', $package1['SPDXID']);
        $this->assertEquals('component1', $package1['name']);
        $this->assertEquals('1.0.0', $package1['versionInfo']);
        $this->assertEquals('Test component 1', $package1['description']);
        
        // Test license mapping
        $this->assertEquals('MIT', $package1['licenseConcluded']);
        
        // Test hash mapping
        $this->assertArrayHasKey('checksums', $package1);
        $this->assertCount(2, $package1['checksums']);
        $this->assertEquals('SHA1', $package1['checksums'][0]['algorithm']);
        $this->assertEquals('abcdef123456', $package1['checksums'][0]['checksumValue']);
        $this->assertEquals('MD5', $package1['checksums'][1]['algorithm']);
        $this->assertEquals('123456abcdef', $package1['checksums'][1]['checksumValue']);
        
        // Test second package mapping
        $package2 = $content['packages'][1];
        $this->assertEquals('SPDXRef-component-2', $package2['SPDXID']);
        $this->assertEquals('component2', $package2['name']);
        $this->assertEquals('2.0.0', $package2['versionInfo']);
        $this->assertEquals('Company X', $package2['supplier']);
        
        // Test that warnings were generated for unmapped fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertTrue(in_array('Unknown or unmapped component field: customComponentField', $warnings));
        // We might also expect warnings for unsupported hash algorithm, but it's not directly tested here
        // as it depends on how exactly unsupportedHashAlg is processed
    }
    
    /**
     * Test conversion with missing required fields
     */
    public function testConversionWithMissingRequiredFields(): void
    {
        $converter = new Converter();
        
        // SPDX with package missing name
        $spdxJson = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'packages' => [
                [
                    'SPDXID' => 'SPDXRef-Package-1',
                    'versionInfo' => '1.0.0',
                    // name is missing
                    'licenseConcluded' => 'MIT'
                ]
            ]
        ]);
        
        // Perform conversion
        $result = $converter->convertSpdxToCyclonedx($spdxJson);
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Check that component was created despite missing name
        $this->assertArrayHasKey('components', $content);
        $this->assertCount(1, $content['components']);
        
        // Check that the component has an auto-generated name
        $component = $content['components'][0];
        $this->assertStringStartsWith('unknown-', $component['name']);
        
        // Check for warning about missing name
        $warnings = $result->getWarnings();
        $this->assertTrue(in_array('Package missing required field: name', $warnings));
        
        // CycloneDX with component missing name
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'components' => [
                [
                    'type' => 'library',
                    'bom-ref' => 'component-1',
                    // name is missing
                    'version' => '1.0.0'
                ]
            ]
        ]);
        
        // Perform conversion
        $result = $converter->convertCyclonedxToSpdx($cyclonedxJson);
        
        // Parse the content
        $content = json_decode($result->getContent(), true);
        
        // Check that package was created despite missing name
        $this->assertArrayHasKey('packages', $content);
        $this->assertCount(1, $content['packages']);
        
        // Check that the package has an auto-generated name
        $package = $content['packages'][0];
        $this->assertStringStartsWith('unknown-', $package['name']);
        
        // Check for warning about missing name
        $warnings = $result->getWarnings();
        $this->assertTrue(in_array('Component missing required field: name', $warnings));
    }
    
    /**
     * Test ConversionResult warnings functionality
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