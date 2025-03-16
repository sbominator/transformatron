<?php

namespace SBOMinator\Transformatron\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Util\ValidationUtil;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for ValidationUtil class.
 */
class ValidationUtilTest extends TestCase
{
    /**
     * Test validateRequiredFields with all required fields present.
     */
    public function testValidateRequiredFieldsWithAllFieldsPresent(): void
    {
        $data = [
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3'
        ];

        $requiredFields = ['field1', 'field2'];

        // This should not throw an exception
        ValidationUtil::validateRequiredFields($data, $requiredFields, 'Test');

        // Add assertion to satisfy PHPUnit
        $this->assertTrue(true);
    }

    /**
     * Test validateRequiredFields with missing fields.
     */
    public function testValidateRequiredFieldsWithMissingFields(): void
    {
        $data = [
            'field1' => 'value1'
            // field2 is missing
        ];

        $requiredFields = ['field1', 'field2'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required Test fields: field2');

        ValidationUtil::validateRequiredFields($data, $requiredFields, 'Test');
    }

    /**
     * Test collectUnknownFieldWarnings.
     */
    public function testCollectUnknownFieldWarnings(): void
    {
        $data = [
            'knownField1' => 'value1',
            'knownField2' => 'value2',
            'unknownField1' => 'value3',
            'unknownField2' => 'value4'
        ];

        $knownFields = ['knownField1', 'knownField2'];

        $warnings = ValidationUtil::collectUnknownFieldWarnings($data, $knownFields, 'Test');

        $this->assertCount(2, $warnings);
        $this->assertContains('Unknown or unmapped Test field: unknownField1', $warnings);
        $this->assertContains('Unknown or unmapped Test field: unknownField2', $warnings);
    }

    /**
     * Test validateFieldValue with matching value.
     */
    public function testValidateFieldValueWithMatchingValue(): void
    {
        $data = [
            'field' => 'expectedValue'
        ];

        // This should not throw an exception
        ValidationUtil::validateFieldValue($data, 'field', 'expectedValue', 'Error message');

        // Add assertion to satisfy PHPUnit
        $this->assertTrue(true);
    }

    /**
     * Test validateFieldValue with non-matching value.
     */
    public function testValidateFieldValueWithNonMatchingValue(): void
    {
        $data = [
            'field' => 'actualValue'
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Error message');

        ValidationUtil::validateFieldValue($data, 'field', 'expectedValue', 'Error message');
    }

    /**
     * Test validateFieldValue with missing field.
     */
    public function testValidateFieldValueWithMissingField(): void
    {
        $data = [
            // field is missing
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Error message');

        ValidationUtil::validateFieldValue($data, 'field', 'expectedValue', 'Error message');
    }

    /**
     * Test hasRequiredKeys with all required keys present.
     */
    public function testHasRequiredKeysWithAllKeysPresent(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $requiredKeys = ['key1', 'key2'];

        $this->assertTrue(ValidationUtil::hasRequiredKeys($data, $requiredKeys));
    }

    /**
     * Test hasRequiredKeys with missing keys.
     */
    public function testHasRequiredKeysWithMissingKeys(): void
    {
        $data = [
            'key1' => 'value1'
            // key2 is missing
        ];

        $requiredKeys = ['key1', 'key2'];

        $this->assertFalse(ValidationUtil::hasRequiredKeys($data, $requiredKeys));
    }

    /**
     * Test warnIfRequiredField with required field.
     */
    public function testWarnIfRequiredFieldWithRequiredField(): void
    {
        $warnings = [];
        $requiredFields = ['requiredField1', 'requiredField2'];

        ValidationUtil::warnIfRequiredField('requiredField1', $requiredFields, $warnings);

        $this->assertCount(1, $warnings);
        $this->assertEquals('Missing required field: requiredField1', $warnings[0]);
    }

    /**
     * Test warnIfRequiredField with non-required field.
     */
    public function testWarnIfRequiredFieldWithNonRequiredField(): void
    {
        $warnings = [];
        $requiredFields = ['requiredField1', 'requiredField2'];

        ValidationUtil::warnIfRequiredField('nonRequiredField', $requiredFields, $warnings);

        $this->assertEmpty($warnings);
    }

    /**
     * Test warnIfRequiredField adds to existing warnings.
     */
    public function testWarnIfRequiredFieldAddsToExistingWarnings(): void
    {
        $warnings = ['Existing warning'];
        $requiredFields = ['requiredField1', 'requiredField2'];

        ValidationUtil::warnIfRequiredField('requiredField1', $requiredFields, $warnings);

        $this->assertCount(2, $warnings);
        $this->assertEquals('Existing warning', $warnings[0]);
        $this->assertEquals('Missing required field: requiredField1', $warnings[1]);
    }
}