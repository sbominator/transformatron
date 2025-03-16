<?php

namespace SBOMinator\Transformatron\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;

/**
 * Test cases for FormatEnum class.
 */
class FormatEnumTest extends TestCase
{
    /**
     * Test that format constants are defined correctly.
     */
    public function testFormatConstants(): void
    {
        $this->assertEquals('SPDX', FormatEnum::FORMAT_SPDX);
        $this->assertEquals('CycloneDX', FormatEnum::FORMAT_CYCLONEDX);
    }

    /**
     * Test that format constants match those in Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testFormatConstantsMatchConverterClass(): void
    {
        // Use reflection to access the constants from Converter class
        $reflectionClass = new \ReflectionClass('SBOMinator\Transformatron\Converter');

        $spdxConstant = $reflectionClass->getConstant('FORMAT_SPDX');
        $cyclonedxConstant = $reflectionClass->getConstant('FORMAT_CYCLONEDX');

        $this->assertEquals($spdxConstant, FormatEnum::FORMAT_SPDX);
        $this->assertEquals($cyclonedxConstant, FormatEnum::FORMAT_CYCLONEDX);
    }
}