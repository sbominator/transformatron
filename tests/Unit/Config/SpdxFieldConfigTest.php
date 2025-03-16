<?php

namespace SBOMinator\Transformatron\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Config\SpdxFieldConfig;

/**
 * Test cases for SpdxFieldConfig class.
 */
class SpdxFieldConfigTest extends TestCase
{
    /**
     * Test that the SPDX to CycloneDX mappings are correctly defined.
     */
    public function testSpdxToCycloneDxMappings(): void
    {
        $mappings = SpdxFieldConfig::getSpdxToCycloneDxMappings();

        // Check that the mappings array is not empty
        $this->assertNotEmpty($mappings);

        // Check specific key mappings
        $this->assertArrayHasKey('spdxVersion', $mappings);
        $this->assertEquals('specVersion', $mappings['spdxVersion']['field']);
        $this->assertEquals('transformSpdxVersion', $mappings['spdxVersion']['transform']);

        $this->assertArrayHasKey('dataLicense', $mappings);
        $this->assertEquals('license', $mappings['dataLicense']['field']);
        $this->assertNull($mappings['dataLicense']['transform']);

        $this->assertArrayHasKey('packages', $mappings);
        $this->assertEquals('components', $mappings['packages']['field']);
        $this->assertEquals('transformPackagesToComponents', $mappings['packages']['transform']);

        $this->assertArrayHasKey('relationships', $mappings);
        $this->assertEquals('dependencies', $mappings['relationships']['field']);
        $this->assertEquals('transformRelationshipsToDependencies', $mappings['relationships']['transform']);
    }

    /**
     * Test that the required SPDX fields are correctly defined.
     */
    public function testRequiredSpdxFields(): void
    {
        $requiredFields = SpdxFieldConfig::getRequiredSpdxFields();

        // Check that the required fields array is not empty
        $this->assertNotEmpty($requiredFields);

        // Check that all expected required fields are present
        $this->assertContains('spdxVersion', $requiredFields);
        $this->assertContains('dataLicense', $requiredFields);
        $this->assertContains('SPDXID', $requiredFields);
        $this->assertContains('name', $requiredFields);
        $this->assertContains('documentNamespace', $requiredFields);

        // Check exact count to ensure no extra fields were added
        $this->assertCount(5, $requiredFields);
    }

    /**
     * Test the isRequiredField method.
     */
    public function testIsRequiredField(): void
    {
        // Test with required fields
        $this->assertTrue(SpdxFieldConfig::isRequiredField('spdxVersion'));
        $this->assertTrue(SpdxFieldConfig::isRequiredField('dataLicense'));
        $this->assertTrue(SpdxFieldConfig::isRequiredField('SPDXID'));
        $this->assertTrue(SpdxFieldConfig::isRequiredField('name'));
        $this->assertTrue(SpdxFieldConfig::isRequiredField('documentNamespace'));

        // Test with non-required fields
        $this->assertFalse(SpdxFieldConfig::isRequiredField('packages'));
        $this->assertFalse(SpdxFieldConfig::isRequiredField('relationships'));
        $this->assertFalse(SpdxFieldConfig::isRequiredField('nonExistentField'));
    }

    /**
     * Test the getMappingForField method.
     */
    public function testGetMappingForField(): void
    {
        // Test with existing fields
        $spdxVersionMapping = SpdxFieldConfig::getMappingForField('spdxVersion');
        $this->assertNotNull($spdxVersionMapping);
        $this->assertEquals('specVersion', $spdxVersionMapping['field']);
        $this->assertEquals('transformSpdxVersion', $spdxVersionMapping['transform']);

        $packagesMapping = SpdxFieldConfig::getMappingForField('packages');
        $this->assertNotNull($packagesMapping);
        $this->assertEquals('components', $packagesMapping['field']);
        $this->assertEquals('transformPackagesToComponents', $packagesMapping['transform']);

        // Test with non-existing field
        $this->assertNull(SpdxFieldConfig::getMappingForField('nonExistentField'));
    }
}