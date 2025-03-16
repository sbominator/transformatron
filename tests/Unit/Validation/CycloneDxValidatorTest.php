<?php

namespace SBOMinator\Transformatron\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Validation\CycloneDxValidator;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for CycloneDxValidator class.
 */
class CycloneDxValidatorTest extends TestCase
{
    /**
     * @var CycloneDxValidator
     */
    private CycloneDxValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CycloneDxValidator();
    }

    /**
     * Test validation with valid CycloneDX data.
     */
    public function testValidateWithValidData(): void
    {
        $validData = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1
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
            'bomFormat' => 'CycloneDX'
            // Missing specVersion and version
        ];

        $errors = $this->validator->validate($invalidData);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing required CycloneDX fields', $errors[0]);
    }

    /**
     * Test validation with invalid bomFormat.
     */
    public function testValidateWithInvalidBomFormat(): void
    {
        $invalidData = [
            'bomFormat' => 'InvalidFormat',
            'specVersion' => '1.4',
            'version' => 1
        ];

        $errors = $this->validator->validate($invalidData);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid CycloneDX bomFormat', $errors[0]);
    }

    /**
     * Test validation with invalid specVersion.
     */
    public function testValidateWithInvalidSpecVersion(): void
    {
        $invalidData = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => 'invalid-version',
            'version' => 1
        ];

        $errors = $this->validator->validate($invalidData);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid CycloneDX specVersion', $errors[0]);
    }

    /**
     * Test validateAndThrow with valid data.
     */
    public function testValidateAndThrowWithValidData(): void
    {
        $validData = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1
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
            'bomFormat' => 'InvalidFormat',
            'specVersion' => '1.4',
            'version' => 1
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid CycloneDX bomFormat');

        $this->validator->validateAndThrow($invalidData);
    }

    /**
     * Test validateComponent with valid component.
     */
    public function testValidateComponentWithValidComponent(): void
    {
        $validComponent = [
            'name' => 'component-name',
            'type' => 'library',
            'version' => '1.0.0',
            'bom-ref' => 'component-1'
        ];

        $errors = $this->validator->validateComponent($validComponent);

        // Should have no errors
        $this->assertEmpty($errors);
    }

    /**
     * Test validateComponent with invalid component.
     */
    public function testValidateComponentWithInvalidComponent(): void
    {
        $invalidComponent = [
            // Missing name
            'type' => 'invalid-type',
            'version' => '1.0.0'
        ];

        $errors = $this->validator->validateComponent($invalidComponent);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('Missing required component field: name', $errors[0]);
        $this->assertStringContainsString('Invalid component type', $errors[1]);
    }

    /**
     * Test validateComponent with invalid licenses.
     */
    public function testValidateComponentWithInvalidLicenses(): void
    {
        $invalidComponent = [
            'name' => 'component-name',
            'type' => 'library',
            'version' => '1.0.0',
            'licenses' => [
                // Missing license object
                ['id' => 'MIT']
            ]
        ];

        $errors = $this->validator->validateComponent($invalidComponent);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid licenses format', $errors[0]);
    }

    /**
     * Test validateDependency with valid dependency.
     */
    public function testValidateDependencyWithValidDependency(): void
    {
        $validDependency = [
            'ref' => 'component-1',
            'dependsOn' => ['component-2', 'component-3']
        ];

        $errors = $this->validator->validateDependency($validDependency);

        // Should have no errors
        $this->assertEmpty($errors);
    }

    /**
     * Test validateDependency with invalid dependency.
     */
    public function testValidateDependencyWithInvalidDependency(): void
    {
        $invalidDependency = [
            'ref' => 'component-1',
            // dependsOn is not an array
            'dependsOn' => 'component-2'
        ];

        $errors = $this->validator->validateDependency($invalidDependency);

        // Should have errors
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Missing or invalid required dependency field: dependsOn', $errors[0]);
    }
}