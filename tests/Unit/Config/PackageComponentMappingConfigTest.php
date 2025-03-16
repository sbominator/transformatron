<?php

namespace SBOMinator\Transformatron\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Config\PackageComponentMappingConfig;
use SBOMinator\Transformatron\Converter;

/**
 * Test cases for PackageComponentMappingConfig class.
 */
class PackageComponentMappingConfigTest extends TestCase
{
    /**
     * Test that the package to component mappings match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testPackageToComponentMappingsMatchConverterClass(): void
    {
        $configMappings = PackageComponentMappingConfig::getPackageToComponentMappings();
        $converterMappings = Converter::PACKAGE_TO_COMPONENT_MAPPINGS;

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
     * Test that the component to package mappings match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testComponentToPackageMappingsMatchConverterClass(): void
    {
        $configMappings = PackageComponentMappingConfig::getComponentToPackageMappings();
        $converterMappings = Converter::COMPONENT_TO_PACKAGE_MAPPINGS;

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
}