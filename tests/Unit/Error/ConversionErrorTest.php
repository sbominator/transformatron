<?php

namespace SBOMinator\Transformatron\Tests\Unit\Error;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Test cases for ConversionError class.
 */
class ConversionErrorTest extends TestCase
{
    /**
     * Test basic error creation and getters.
     */
    public function testCreateBasicError(): void
    {
        $message = 'Test error message';
        $severity = ConversionError::SEVERITY_ERROR;
        $component = 'TestComponent';
        $context = ['key' => 'value'];
        $code = 'test_error_code';

        $error = new ConversionError($message, $severity, $component, $context, $code);

        $this->assertEquals($message, $error->getMessage());
        $this->assertEquals($severity, $error->getSeverity());
        $this->assertEquals($component, $error->getComponent());
        $this->assertEquals($context, $error->getContext());
        $this->assertEquals('value', $error->getContextValue('key'));
        $this->assertEquals($code, $error->getCode());
        $this->assertNull($error->getPrevious());
    }

    /**
     * Test error creation with an exception.
     */
    public function testCreateErrorWithException(): void
    {
        $exception = new \RuntimeException('Test exception');

        $error = new ConversionError(
            'Error with exception',
            ConversionError::SEVERITY_CRITICAL,
            'TestComponent',
            [],
            'test_code',
            $exception
        );

        $this->assertEquals('Error with exception', $error->getMessage());
        $this->assertSame($exception, $error->getPrevious());
    }

    /**
     * Test factory methods for different severity levels.
     */
    public function testSeverityFactoryMethods(): void
    {
        // Test warning creation
        $warning = ConversionError::createWarning('Warning message', 'TestComponent');
        $this->assertEquals(ConversionError::SEVERITY_WARNING, $warning->getSeverity());
        $this->assertEquals('Warning message', $warning->getMessage());

        // Test error creation
        $error = ConversionError::createError('Error message', 'TestComponent');
        $this->assertEquals(ConversionError::SEVERITY_ERROR, $error->getSeverity());
        $this->assertEquals('Error message', $error->getMessage());

        // Test critical error creation
        $critical = ConversionError::createCritical('Critical message', 'TestComponent');
        $this->assertEquals(ConversionError::SEVERITY_CRITICAL, $critical->getSeverity());
        $this->assertEquals('Critical message', $critical->getMessage());

        // Test info creation
        $info = ConversionError::createInfo('Info message', 'TestComponent');
        $this->assertEquals(ConversionError::SEVERITY_INFO, $info->getSeverity());
        $this->assertEquals('Info message', $info->getMessage());
    }

    /**
     * Test the severity comparison method.
     */
    public function testIsSeverityOrWorse(): void
    {
        $info = ConversionError::createInfo('Info');
        $warning = ConversionError::createWarning('Warning');
        $error = ConversionError::createError('Error');
        $critical = ConversionError::createCritical('Critical');

        // Test info severity
        $this->assertTrue($info->isSeverityOrWorse(ConversionError::SEVERITY_INFO));
        $this->assertFalse($info->isSeverityOrWorse(ConversionError::SEVERITY_WARNING));
        $this->assertFalse($info->isSeverityOrWorse(ConversionError::SEVERITY_ERROR));
        $this->assertFalse($info->isSeverityOrWorse(ConversionError::SEVERITY_CRITICAL));

        // Test warning severity
        $this->assertTrue($warning->isSeverityOrWorse(ConversionError::SEVERITY_INFO));
        $this->assertTrue($warning->isSeverityOrWorse(ConversionError::SEVERITY_WARNING));
        $this->assertFalse($warning->isSeverityOrWorse(ConversionError::SEVERITY_ERROR));
        $this->assertFalse($warning->isSeverityOrWorse(ConversionError::SEVERITY_CRITICAL));

        // Test error severity
        $this->assertTrue($error->isSeverityOrWorse(ConversionError::SEVERITY_INFO));
        $this->assertTrue($error->isSeverityOrWorse(ConversionError::SEVERITY_WARNING));
        $this->assertTrue($error->isSeverityOrWorse(ConversionError::SEVERITY_ERROR));
        $this->assertFalse($error->isSeverityOrWorse(ConversionError::SEVERITY_CRITICAL));

        // Test critical severity
        $this->assertTrue($critical->isSeverityOrWorse(ConversionError::SEVERITY_INFO));
        $this->assertTrue($critical->isSeverityOrWorse(ConversionError::SEVERITY_WARNING));
        $this->assertTrue($critical->isSeverityOrWorse(ConversionError::SEVERITY_ERROR));
        $this->assertTrue($critical->isSeverityOrWorse(ConversionError::SEVERITY_CRITICAL));
    }

    /**
     * Test the string conversion functionality.
     */
    public function testToString(): void
    {
        // Simple error
        $error = ConversionError::createError('Simple error', 'TestComponent');
        $errorString = (string)$error;
        $this->assertStringContainsString('[error]', $errorString);
        $this->assertStringContainsString('TestComponent', $errorString);
        $this->assertStringContainsString('Simple error', $errorString);

        // Error with context
        $error = ConversionError::createWarning(
            'Error with context',
            'TestComponent',
            ['detail' => 'Additional information']
        );
        $errorString = (string)$error;
        $this->assertStringContainsString('[warning]', $errorString);
        $this->assertStringContainsString('Error with context', $errorString);
        $this->assertStringContainsString('Additional information', $errorString);

        // Error with code
        $error = ConversionError::createError(
            'Error with code',
            'TestComponent',
            [],
            'TEST_CODE'
        );
        $errorString = (string)$error;
        $this->assertStringContainsString('(Code: TEST_CODE)', $errorString);
    }

    /**
     * Test getting a default context value.
     */
    public function testGetContextValueWithDefault(): void
    {
        $error = ConversionError::createInfo(
            'Info message',
            'TestComponent',
            ['key1' => 'value1']
        );

        // Existing key
        $this->assertEquals('value1', $error->getContextValue('key1'));

        // Non-existent key with default
        $this->assertEquals('default', $error->getContextValue('nonexistent', 'default'));

        // Non-existent key without default
        $this->assertNull($error->getContextValue('nonexistent'));
    }
}