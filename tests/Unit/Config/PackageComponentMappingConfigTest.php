<?php

namespace SBOMinator\Transformatron\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Config\PackageComponentMappingConfig;

/**
 * Test cases for PackageComponentMappingConfig class.
 */
class PackageComponentMappingConfigTest extends TestCase
{
    /**
     * Test that the package to component mappings are correctly defined.
     */
    public function testPackageToComponentMappings(): void
    {
        $mappings = PackageComponentMappingConfig::getPackageToComponentMappings();

        // Check that the mappings array is not empty
        $this->assertNotEmpty($mappings);

        // Check specific key mappings
        $this->assertArrayHasKey('name', $mappings);
        $this->assertEquals('name', $mappings['name']);

        $this->assertArrayHasKey('versionInfo', $mappings);
        $this->assertEquals('version', $mappings['versionInfo']);

        $this->assertArrayHasKey('licenseConcluded', $mappings);
        $this->assertEquals('licenses', $mappings['licenseConcluded']);

        $this->assertArrayHasKey('checksums', $mappings);
        $this->assertEquals('hashes', $mappings['checksums']);
    }

    /**
     * Test that the component to package mappings are correctly defined.
     */
    public function testComponentToPackageMappings(): void
    {
        $mappings = PackageComponentMappingConfig::getComponentToPackageMappings();

        // Check that the mappings array is not empty
        $this->assertNotEmpty($mappings);

        // Check specific key mappings
        $this->assertArrayHasKey('name', $mappings);
        $this->assertEquals('name', $mappings['name']);

        $this->assertArrayHasKey('version', $mappings);
        $this->assertEquals('versionInfo', $mappings['version']);

        $this->assertArrayHasKey('licenses', $mappings);
        $this->assertEquals('licenseConcluded', $mappings['licenses']);

        $this->assertArrayHasKey('hashes', $mappings);
        $this->assertEquals('checksums', $mappings['hashes']);
    }

    /**
     * Test getComponentFieldForPackageField method.
     */
    public function testGetComponentFieldForPackageField(): void
    {
        // Test with existing fields
        $this->assertEquals('name', PackageComponentMappingConfig::getComponentFieldForPackageField('name'));
        $this->assertEquals('version', PackageComponentMappingConfig::getComponentFieldForPackageField('versionInfo'));
        $this->assertEquals('licenses', PackageComponentMappingConfig::getComponentFieldForPackageField('licenseConcluded'));
        $this->assertEquals('hashes', PackageComponentMappingConfig::getComponentFieldForPackageField('checksums'));

        // Test with non-existent field
        $this->assertNull(PackageComponentMappingConfig::getComponentFieldForPackageField('nonExistentField'));
    }

    /**
     * Test getPackageFieldForComponentField method.
     */
    public function testGetPackageFieldForComponentField(): void
    {
        // Test with existing fields
        $this->assertEquals('name', PackageComponentMappingConfig::getPackageFieldForComponentField('name'));
        $this->assertEquals('versionInfo', PackageComponentMappingConfig::getPackageFieldForComponentField('version'));
        $this->assertEquals('licenseConcluded', PackageComponentMappingConfig::getPackageFieldForComponentField('licenses'));
        $this->assertEquals('checksums', PackageComponentMappingConfig::getPackageFieldForComponentField('hashes'));

        // Test with non-existent field
        $this->assertNull(PackageComponentMappingConfig::getPackageFieldForComponentField('nonExistentField'));
    }

    /**
     * Test getAllPackageFields method.
     */
    public function testGetAllPackageFields(): void
    {
        $fields = PackageComponentMappingConfig::getAllPackageFields();

        // Check that the fields array is not empty
        $this->assertNotEmpty($fields);

        // Check that key fields are present
        $this->assertContains('name', $fields);
        $this->assertContains('versionInfo', $fields);
        $this->assertContains('licenseConcluded', $fields);
        $this->assertContains('licenseDeclared', $fields);
        $this->assertContains('checksums', $fields);
    }

    /**
     * Test getAllComponentFields method.
     */
    public function testGetAllComponentFields(): void
    {
        $fields = PackageComponentMappingConfig::getAllComponentFields();

        // Check that the fields array is not empty
        $this->assertNotEmpty($fields);

        // Check that key fields are present
        $this->assertContains('name', $fields);
        $this->assertContains('version', $fields);
        $this->assertContains('purl', $fields);
        $this->assertContains('licenses', $fields);
        $this->assertContains('hashes', $fields);
    }
}