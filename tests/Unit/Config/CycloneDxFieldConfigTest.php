<?php

namespace SBOMinator\Transformatron\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Config\CycloneDxFieldConfig;

/**
 * Test cases for CycloneDxFieldConfig class.
 */
class CycloneDxFieldConfigTest extends TestCase
{
    /**
     * Test that the CycloneDX to SPDX mappings are correctly defined.
     */
    public function testCycloneDxToSpdxMappings(): void
    {
        $mappings = CycloneDxFieldConfig::getCycloneDxToSpdxMappings();

        // Check that the mappings array is not empty
        $this->assertNotEmpty($mappings);

        // Check specific key mappings
        $this->assertArrayHasKey('bomFormat', $mappings);
        $this->assertNull($mappings['bomFormat']['field']);
        $this->assertNull($mappings['bomFormat']['transform']);

        $this->assertArrayHasKey('specVersion', $mappings);
        $this->assertEquals('spdxVersion', $mappings['specVersion']['field']);
        $this->assertEquals('transformSpecVersion', $mappings['specVersion']['transform']);

        $this->assertArrayHasKey('components', $mappings);
        $this->assertEquals('packages', $mappings['components']['field']);
        $this->assertEquals('transformComponentsToPackages', $mappings['components']['transform']);

        $this->assertArrayHasKey('dependencies', $mappings);
        $this->assertEquals('relationships', $mappings['dependencies']['field']);
        $this->assertEquals('transformDependenciesToRelationships', $mappings['dependencies']['transform']);
    }

    /**
     * Test that the required CycloneDX fields are correctly defined.
     */
    public function testRequiredCycloneDxFields(): void
    {
        $requiredFields = CycloneDxFieldConfig::getRequiredCycloneDxFields();

        // Check that the required fields array is not empty
        $this->assertNotEmpty($requiredFields);

        // Check that all expected required fields are present
        $this->assertContains('bomFormat', $requiredFields);
        $this->assertContains('specVersion', $requiredFields);
        $this->assertContains('version', $requiredFields);

        // Check exact count to ensure no extra fields were added
        $this->assertCount(3, $requiredFields);
    }

    /**
     * Test the isRequiredField method.
     */
    public function testIsRequiredField(): void
    {
        // Test with required fields
        $this->assertTrue(CycloneDxFieldConfig::isRequiredField('bomFormat'));
        $this->assertTrue(CycloneDxFieldConfig::isRequiredField('specVersion'));
        $this->assertTrue(CycloneDxFieldConfig::isRequiredField('version'));

        // Test with non-required fields
        $this->assertFalse(CycloneDxFieldConfig::isRequiredField('components'));
        $this->assertFalse(CycloneDxFieldConfig::isRequiredField('dependencies'));
        $this->assertFalse(CycloneDxFieldConfig::isRequiredField('nonExistentField'));
    }

    /**
     * Test the getMappingForField method.
     */
    public function testGetMappingForField(): void
    {
        // Test with existing fields
        $specVersionMapping = CycloneDxFieldConfig::getMappingForField('specVersion');
        $this->assertNotNull($specVersionMapping);
        $this->assertEquals('spdxVersion', $specVersionMapping['field']);
        $this->assertEquals('transformSpecVersion', $specVersionMapping['transform']);

        $componentsMapping = CycloneDxFieldConfig::getMappingForField('components');
        $this->assertNotNull($componentsMapping);
        $this->assertEquals('packages', $componentsMapping['field']);
        $this->assertEquals('transformComponentsToPackages', $componentsMapping['transform']);

        // Test with non-existing field
        $this->assertNull(CycloneDxFieldConfig::getMappingForField('nonExistentField'));
    }

    /**
     * Test that the CycloneDX to SPDX mappings match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testCycloneDxToSpdxMappingsMatchConverterClass(): void
    {
        // Use reflection to access the constant from Converter class
        $reflectionClass = new \ReflectionClass('SBOMinator\Transformatron\Converter');
        $converterMappings = $reflectionClass->getConstant('CYCLONEDX_TO_SPDX_MAPPINGS');

        $configMappings = CycloneDxFieldConfig::getCycloneDxToSpdxMappings();

        // Check that all keys in converter mappings exist in config mappings
        foreach (array_keys($converterMappings) as $key) {
            $this->assertArrayHasKey($key, $configMappings);
            $this->assertEquals($converterMappings[$key], $configMappings[$key]);
        }

        // Check that all keys in config mappings exist in converter mappings
        foreach (array_keys($configMappings) as $key) {
            $this->assertArrayHasKey($key, $converterMappings);
        }
    }

    /**
     * Test that the required CycloneDX fields match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testRequiredCycloneDxFieldsMatchConverterClass(): void
    {
        // Use reflection to access the constant from Converter class
        $reflectionClass = new \ReflectionClass('SBOMinator\Transformatron\Converter');
        $converterRequiredFields = $reflectionClass->getConstant('REQUIRED_CYCLONEDX_FIELDS');

        $configRequiredFields = CycloneDxFieldConfig::getRequiredCycloneDxFields();

        // Check that both arrays have the same values (regardless of order)
        $this->assertEqualsCanonicalizing($converterRequiredFields, $configRequiredFields);
    }
}