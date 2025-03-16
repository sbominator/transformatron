<?php

namespace SBOMinator\Transformatron\Tests\Unit\Converter;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Converter\AbstractConverter;
use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Exception\ConversionException;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for AbstractConverter class.
 */
class AbstractConverterTest extends TestCase
{
    /**
     * Test successful conversion.
     */
    public function testSuccessfulConversion(): void
    {
        $mockConverter = new class extends AbstractConverter {
            public function getSourceFormat(): string
            {
                return 'TestSource';
            }

            public function getTargetFormat(): string
            {
                return 'TestTarget';
            }

            protected function validateSourceData(array $sourceData): array
            {
                // Return empty array for no validation errors
                return [];
            }

            protected function getInitialTargetData(): array
            {
                return ['initialized' => true];
            }

            protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array
            {
                // Simple mapping for testing
                $targetData['mappedValue'] = $sourceData['sourceValue'] ?? 'default';

                if (isset($sourceData['warningTrigger'])) {
                    $warnings[] = 'Test warning';
                }

                if (isset($sourceData['errorTrigger'])) {
                    $errors[] = ConversionError::createWarning('Test error', 'TestComponent');
                }

                return $targetData;
            }

            protected function checkUnknownSourceFields(array $sourceData): array
            {
                $knownFields = ['sourceValue', 'warningTrigger', 'errorTrigger'];
                $unknownFields = array_diff(array_keys($sourceData), $knownFields);

                return array_map(function($field) {
                    return "Unknown field: {$field}";
                }, $unknownFields);
            }

            protected function ensureRequiredDefaultData(array $targetData): array
            {
                // Add a default value if not already set
                if (!isset($targetData['defaultValue'])) {
                    $targetData['defaultValue'] = 'default';
                }

                return $targetData;
            }
        };

        // Test with valid input
        $validJson = json_encode(['sourceValue' => 'test']);
        $result = $mockConverter->convert($validJson);

        // Check result
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals('TestTarget', $result->getFormat());

        // Check converted content
        $content = json_decode($result->getContent(), true);
        $this->assertEquals(true, $content['initialized']);
        $this->assertEquals('test', $content['mappedValue']);
        $this->assertEquals('default', $content['defaultValue']);

        // Should have no warnings
        $this->assertEmpty($result->getWarnings());
        $this->assertEmpty($result->getErrors());

        // Test with warning trigger
        $warningJson = json_encode(['sourceValue' => 'test', 'warningTrigger' => true]);
        $result = $mockConverter->convert($warningJson);

        // Should have warnings
        $this->assertNotEmpty($result->getWarnings());
        $this->assertContains('Test warning', $result->getWarnings());

        // Test with error trigger
        $errorJson = json_encode(['sourceValue' => 'test', 'errorTrigger' => true]);
        $result = $mockConverter->convert($errorJson);

        // Should have conversion errors
        $this->assertNotEmpty($result->getErrors());
        $this->assertEquals(1, count($result->getErrors()));
        $this->assertEquals('Test error', $result->getErrors()[0]->getMessage());

        // Test with unknown field
        $unknownFieldJson = json_encode(['sourceValue' => 'test', 'unknownField' => 'value']);
        $result = $mockConverter->convert($unknownFieldJson);

        // Should have warnings for unknown field
        $this->assertNotEmpty($result->getWarnings());
        $this->assertContains('Unknown field: unknownField', $result->getWarnings());
    }

    /**
     * Test validation exception handling.
     */
    public function testValidationExceptionHandling(): void
    {
        $mockConverter = new class extends AbstractConverter {
            public function getSourceFormat(): string
            {
                return 'TestSource';
            }

            public function getTargetFormat(): string
            {
                return 'TestTarget';
            }

            protected function validateSourceData(array $sourceData): array
            {
                // Simulate validation failure
                if (!isset($sourceData['requiredField'])) {
                    return [
                        ConversionError::createCritical(
                            'Missing required field: requiredField',
                            'Validator',
                            ['field' => 'requiredField'],
                            'missing_required_field'
                        )
                    ];
                }
                return [];
            }

            protected function getInitialTargetData(): array
            {
                return [];
            }

            protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array
            {
                return $targetData;
            }

            protected function checkUnknownSourceFields(array $sourceData): array
            {
                return [];
            }

            protected function ensureRequiredDefaultData(array $targetData): array
            {
                return $targetData;
            }
        };

        // Invalid input (missing required field)
        $invalidJson = json_encode(['otherField' => 'value']);

        // This should not throw an exception anymore, but return a failed result with errors
        $result = $mockConverter->convert($invalidJson);

        $this->assertFalse($result->isSuccessful());
        $this->assertNotEmpty($result->getErrors());
        $this->assertEquals('Missing required field: requiredField', $result->getErrors()[0]->getMessage());
    }

    /**
     * Test conversion exception handling.
     */
    public function testConversionExceptionHandling(): void
    {
        $mockConverter = new class extends AbstractConverter {
            public function getSourceFormat(): string
            {
                return 'TestSource';
            }

            public function getTargetFormat(): string
            {
                return 'TestTarget';
            }

            protected function validateSourceData(array $sourceData): array
            {
                return [];
            }

            protected function getInitialTargetData(): array
            {
                return [];
            }

            protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array
            {
                // Simulate conversion error
                if (isset($sourceData['errorTrigger'])) {
                    throw new \RuntimeException('Conversion error');
                }

                return $targetData;
            }

            protected function checkUnknownSourceFields(array $sourceData): array
            {
                return [];
            }

            protected function ensureRequiredDefaultData(array $targetData): array
            {
                return $targetData;
            }
        };

        // Input that triggers a conversion error
        $errorJson = json_encode(['errorTrigger' => true]);

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Conversion error');

        $mockConverter->convert($errorJson);
    }

    /**
     * Test field transformation.
     */
    public function testTransformFieldValue(): void
    {
        $mockConverter = new class extends AbstractConverter {
            public function getSourceFormat(): string
            {
                return 'TestSource';
            }

            public function getTargetFormat(): string
            {
                return 'TestTarget';
            }

            // Make the protected method public for testing
            public function transformFieldValuePublic($value, ?string $transform, string $fieldName, array &$warnings, array &$errors = [])
            {
                return $this->transformFieldValue($value, $transform, $fieldName, $warnings, $errors);
            }

            // Simple transformation method
            protected function simpleTransform($value)
            {
                return "transformed-{$value}";
            }

            // Transformation method that uses warnings
            protected function transformWithWarnings($value, array &$warnings, array &$errors)
            {
                $warnings[] = "Warning from transformation: {$value}";
                return "transformed-with-warnings-{$value}";
            }

            // Transformation method that throws an exception
            protected function transformWithException($value)
            {
                throw new \RuntimeException("Error transforming: {$value}");
            }

            // Unused abstract method implementations
            protected function validateSourceData(array $sourceData): array { return []; }
            protected function getInitialTargetData(): array { return []; }
            protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array { return []; }
            protected function checkUnknownSourceFields(array $sourceData): array { return []; }
            protected function ensureRequiredDefaultData(array $targetData): array { return []; }
        };

        // Case 1: No transformation (null transform)
        $warnings = [];
        $errors = [];
        $result = $mockConverter->transformFieldValuePublic('testValue', null, 'testField', $warnings, $errors);
        $this->assertEquals('testValue', $result);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);

        // Case 2: Non-existent transform method
        $warnings = [];
        $errors = [];
        $result = $mockConverter->transformFieldValuePublic('testValue', 'nonExistentMethod', 'testField', $warnings, $errors);
        $this->assertEquals('testValue', $result);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);

        // Case 3: Simple transformation
        $warnings = [];
        $errors = [];
        $result = $mockConverter->transformFieldValuePublic('testValue', 'simpleTransform', 'testField', $warnings, $errors);
        $this->assertEquals('transformed-testValue', $result);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);

        // Case 4: Transformation with warnings for special fields
        $warnings = [];
        $errors = [];
        $result = $mockConverter->transformFieldValuePublic('testValue', 'transformWithWarnings', 'packages', $warnings, $errors);
        $this->assertEquals('transformed-with-warnings-testValue', $result);
        $this->assertNotEmpty($warnings);
        $this->assertContains('Warning from transformation: testValue', $warnings);
        $this->assertEmpty($errors);

        // Case 5: Transformation that throws an exception
        $warnings = [];
        $errors = [];
        $result = $mockConverter->transformFieldValuePublic('testValue', 'transformWithException', 'testField', $warnings, $errors);
        $this->assertEquals('testValue', $result); // Should return original value on error
        $this->assertEmpty($warnings);
        $this->assertNotEmpty($errors);
        $this->assertEquals('Error transforming field \'testField\': Error transforming: testValue', $errors[0]->getMessage());
    }
}