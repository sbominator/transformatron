<?php

namespace SBOMinator\Transformatron\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\RelationshipTypeEnum;

/**
 * Test cases for RelationshipTypeEnum class.
 */
class RelationshipTypeEnumTest extends TestCase
{
    /**
     * Test that relationship type constants are defined correctly.
     */
    public function testRelationshipTypeConstants(): void
    {
        $this->assertEquals('DEPENDS_ON', RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON);
        $this->assertEquals('DESCRIBES', RelationshipTypeEnum::RELATIONSHIP_DESCRIBES);
        $this->assertEquals('CONTAINS', RelationshipTypeEnum::RELATIONSHIP_CONTAINS);
        $this->assertEquals('GENERATED_FROM', RelationshipTypeEnum::RELATIONSHIP_GENERATED_FROM);
        $this->assertEquals('DYNAMIC_LINK', RelationshipTypeEnum::RELATIONSHIP_DYNAMIC_LINK);
        $this->assertEquals('STATIC_LINK', RelationshipTypeEnum::RELATIONSHIP_STATIC_LINK);
        $this->assertEquals('BUILD_DEPENDENCY_OF', RelationshipTypeEnum::RELATIONSHIP_BUILD_DEPENDENCY_OF);
        $this->assertEquals('DEV_DEPENDENCY_OF', RelationshipTypeEnum::RELATIONSHIP_DEV_DEPENDENCY_OF);
        $this->assertEquals('RUNTIME_DEPENDENCY_OF', RelationshipTypeEnum::RELATIONSHIP_RUNTIME_DEPENDENCY_OF);
        $this->assertEquals('OPTIONAL_DEPENDENCY_OF', RelationshipTypeEnum::RELATIONSHIP_OPTIONAL_DEPENDENCY_OF);
    }

    /**
     * Test that the DEPENDS_ON constant matches the one in Converter class.
     *
     * This helps ensure consistency during refactoring.
     */
    public function testDependsOnConstantMatchesConverterClass(): void
    {
        // Use reflection to access the constant from Converter class
        $reflectionClass = new \ReflectionClass('SBOMinator\Transformatron\Converter');

        $dependsOnConstant = $reflectionClass->getConstant('RELATIONSHIP_DEPENDS_ON');

        $this->assertEquals($dependsOnConstant, RelationshipTypeEnum::RELATIONSHIP_DEPENDS_ON);
    }
}