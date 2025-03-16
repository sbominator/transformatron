<?php

namespace SBOMinator\Transformatron\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Util\JsonUtil;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for JsonUtil class.
 */
class JsonUtilTest extends TestCase
{
    /**
     * Test decodeJson with valid JSON.
     */
    public function testDecodeJsonWithValidJson(): void
    {
        // Test with simple array
        $validJson = '["item1", "item2", "item3"]';
        $result = JsonUtil::decodeJson($validJson);
        $this->assertEquals(['item1', 'item2', 'item3'], $result);

        // Test with associative array (object)
        $validObjectJson = '{"key1": "value1", "key2": "value2"}';
        $result = JsonUtil::decodeJson($validObjectJson);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $result);

        // Test with nested structure
        $validNestedJson = '{"name": "test", "items": ["item1", "item2"], "metadata": {"created": "2023-01-01"}}';
        $result = JsonUtil::decodeJson($validNestedJson);
        $this->assertEquals([
            'name' => 'test',
            'items' => ['item1', 'item2'],
            'metadata' => ['created' => '2023-01-01']
        ], $result);
    }

    /**
     * Test decodeJson with invalid JSON.
     */
    public function testDecodeJsonWithInvalidJson(): void
    {
        // Test with invalid JSON (missing closing bracket)
        $invalidJson = '{"key": "value"';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid JSON:');
        JsonUtil::decodeJson($invalidJson);
    }

    /**
     * Test decodeJson with non-array JSON.
     */
    public function testDecodeJsonWithNonArrayJson(): void
    {
        // Test with string
        $stringJson = '"just a string"';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('JSON must decode to an array');
        JsonUtil::decodeJson($stringJson);
    }

    /**
     * Test decodeJson with empty JSON.
     */
    public function testDecodeJsonWithEmptyJson(): void
    {
        // Test with empty array (valid)
        $emptyArrayJson = '[]';
        $result = JsonUtil::decodeJson($emptyArrayJson);
        $this->assertEquals([], $result);

        // Test with empty object (valid)
        $emptyObjectJson = '{}';
        $result = JsonUtil::decodeJson($emptyObjectJson);
        $this->assertEquals([], $result);

        // Test with empty string (invalid)
        $emptyString = '';

        $this->expectException(ValidationException::class);
        JsonUtil::decodeJson($emptyString);
    }

    /**
     * Test encodeJson with valid data.
     */
    public function testEncodeJsonWithValidData(): void
    {
        // Test with array
        $data = ['item1', 'item2', 'item3'];
        $result = JsonUtil::encodeJson($data);
        $this->assertEquals('["item1","item2","item3"]', $result);

        // Test with associative array
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $result = JsonUtil::encodeJson($data);
        $this->assertEquals('{"key1":"value1","key2":"value2"}', $result);

        // Test with nested structure
        $data = [
            'name' => 'test',
            'items' => ['item1', 'item2'],
            'metadata' => ['created' => '2023-01-01']
        ];
        $result = JsonUtil::encodeJson($data);
        $this->assertEquals('{"name":"test","items":["item1","item2"],"metadata":{"created":"2023-01-01"}}', $result);
    }

    /**
     * Test encodePrettyJson.
     */
    public function testEncodePrettyJson(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $result = JsonUtil::encodePrettyJson($data);

        // Check that the result contains line breaks and spaces (pretty-printed)
        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString("  ", $result);

        // Check that we can decode it back to the original data
        $decodedData = json_decode($result, true);
        $this->assertEquals($data, $decodedData);
    }

    /**
     * Test isValidJson.
     */
    public function testIsValidJson(): void
    {
        // Test with valid JSON
        $this->assertTrue(JsonUtil::isValidJson('{"key": "value"}'));
        $this->assertTrue(JsonUtil::isValidJson('["item1", "item2"]'));
        $this->assertTrue(JsonUtil::isValidJson('{}'));
        $this->assertTrue(JsonUtil::isValidJson('[]'));

        // Test with invalid JSON
        $this->assertFalse(JsonUtil::isValidJson('{"key": "value"'));
        $this->assertFalse(JsonUtil::isValidJson('not json'));
        $this->assertFalse(JsonUtil::isValidJson(''));
    }
}