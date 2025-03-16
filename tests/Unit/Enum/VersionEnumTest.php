<?php

namespace SBOMinator\Transformatron\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\VersionEnum;

/**
 * Test cases for VersionEnum class.
 */
class VersionEnumTest extends TestCase
{
    /**
     * Test that version constants are defined correctly.
     */
    public function testVersionConstants(): void
    {
        $this->assertEquals('SPDX-2.3', VersionEnum::SPDX_VERSION);
        $this->assertEquals('1.4', VersionEnum::CYCLONEDX_VERSION);
    }

    /**
     * Test that version constants match those in Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testVersionConstantsMatchConverterClass(): void
    {
        // Use reflection to access the constants from Converter class
        $reflectionClass = new \ReflectionClass('SBOMinator\Transformatron\Converter');

        $spdxVersionConstant = $reflectionClass->getConstant('SPDX_VERSION');
        $cyclonedxVersionConstant = $reflectionClass->getConstant('CYCLONEDX_VERSION');

        $this->assertEquals($spdxVersionConstant, VersionEnum::SPDX_VERSION);
        $this->assertEquals($cyclonedxVersionConstant, VersionEnum::CYCLONEDX_VERSION);
    }
}