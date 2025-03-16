<?php

namespace SBOMinator\Transformatron\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Config\SpdxFieldConfig;
use SBOMinator\Transformatron\Converter;

/**
 * Test cases for SpdxFieldConfig class.
 */
class SpdxFieldConfigTest extends TestCase
{
    /**
     * Test that the SPDX to CycloneDX mappings match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testSpdxToCycloneDxMappingsMatchConverterClass(): void
    {
        $configMappings = SpdxFieldConfig::getSpdxToCycloneDxMappings();
        $converterMappings = Converter::SPDX_TO_CYCLONEDX_MAPPINGS;

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
     * Test that the required SPDX fields match those in the Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testRequiredSpdxFieldsMatchConverterClass(): void
    {
        $configRequiredFields = SpdxFieldConfig::getRequiredSpdxFields();
        $converterRequiredFields = Converter::REQUIRED_SPDX_FIELDS;

        // Check that both arrays have the same values (regardless of order)
        $this->assertEqualsCanonicalizing($converterRequiredFields, $configRequiredFields);
    }
}