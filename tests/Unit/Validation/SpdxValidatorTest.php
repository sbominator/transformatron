<?php

namespace SBOMinator\Transformatron\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Validation\SpdxValidator;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for SpdxValidator class.
 */
class SpdxValidatorTest extends TestCase
{
    /**
     * @var SpdxValidator
     */
    private SpdxValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SpdxValidator();
    }

    /**
     * Test validation with valid SPDX data.
     */
    public function testValidateWithValidData(): void
    {
        $validData = [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ];

        $errors = $this->validator->validate($validData);

        // Should have no errors
        $this->assertEmpty($errors);
    }

    /**
     * Test validation with missing required fields.
     */
    public function testValidateWithMissingRequiredFields(): void
    {
        $invalidData = [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0'
            // Missing SPDXID, name, and documentNamespace
        ];

        $errors = $this->validator->validate($invalidData);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing required SPDX fields', $errors[0]);
    }

    /**
     * Test validation with invalid SPDX version.
     */
    public function testValidateWithInvalidSpdxVersion(): void
    {
        $invalidData = [
            'spdxVersion' => 'INVALID-VERSION',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ];

        $errors = $this->validator->validate($invalidData);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid SPDX version', $errors[0]);
    }

    /**
     * Test validateAndThrow with valid data.
     */
    public function testValidateAndThrowWithValidData(): void
    {
        $validData = [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ];

        // Should not throw an exception
        $this->validator->validateAndThrow($validData);

        // Add assertion to satisfy PHPUnit
        $this->assertTrue(true);
    }

    /**
     * Test validateAndThrow with invalid data.
     */
    public function testValidateAndThrowWithInvalidData(): void
    {
        $invalidData = [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0'
            // Missing SPDXID, name, and documentNamespace
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required SPDX fields');

        $this->validator->validateAndThrow($invalidData);
    }

    /**
     * Test validatePackage with valid package.
     */
    public function testValidatePackageWithValidPackage(): void
    {
        $validPackage = [
            'name' => 'package-name',
            'SPDXID' => 'SPDXRef-Package-1',
            'versionInfo' => '1.0.0',
            'licenseConcluded' => 'MIT'
        ];

        $errors = $this->validator->validatePackage($validPackage);

        // Should have no errors
        $this->assertEmpty($errors);
    }

    /**
     * Test validatePackage with invalid package.
     */
    public function testValidatePackageWithInvalidPackage(): void
    {
        $invalidPackage = [
            // Missing name
            'SPDXID' => 'SPDXRef-Package-1',
            'versionInfo' => '1.0.0'
        ];

        $errors = $this->validator->validatePackage($invalidPackage);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing required package field: name', $errors[0]);
    }

    /**
     * Test validateRelationship with valid relationship.
     */
    public function testValidateRelationshipWithValidRelationship(): void
    {
        $validRelationship = [
            'spdxElementId' => 'SPDXRef-Package-1',
            'relatedSpdxElement' => 'SPDXRef-Package-2',
            'relationshipType' => 'DEPENDS_ON'
        ];

        $errors = $this->validator->validateRelationship($validRelationship);

        // Should have no errors
        $this->assertEmpty($errors);
    }

    /**
     * Test validateRelationship with invalid relationship.
     */
    public function testValidateRelationshipWithInvalidRelationship(): void
    {
        $invalidRelationship = [
            'spdxElementId' => 'SPDXRef-Package-1',
            // Missing relatedSpdxElement
            'relationshipType' => 'INVALID_TYPE'
        ];

        $errors = $this->validator->validateRelationship($invalidRelationship);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('Missing required relationship field: relatedSpdxElement', $errors[0]);
        $this->assertStringContainsString('Invalid relationship type', $errors[1]);
    }
}