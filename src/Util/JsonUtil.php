<?php

namespace SBOMinator\Transformatron\Util;

use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Utility class for JSON operations.
 *
 * Provides methods for decoding and validating JSON data.
 */
class JsonUtil
{
    /**
     * Decode a JSON string into an associative array.
     *
     * @param string $json The JSON string to decode
     * @return array<string, mixed> The decoded associative array
     * @throws ValidationException If the JSON is invalid or doesn't decode to an array
     */
    public static function decodeJson(string $json): array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                'Invalid JSON: ' . json_last_error_msg(),
                ['json_error' => json_last_error_msg()]
            );
        }

        if (!is_array($data)) {
            throw new ValidationException(
                'JSON must decode to an array',
                ['type_error' => 'Decoded value is not an array']
            );
        }

        return $data;
    }

    /**
     * Encode data to a JSON string.
     *
     * @param mixed $data The data to encode
     * @param int $options JSON encoding options
     * @return string The encoded JSON string
     * @throws ValidationException If the data cannot be encoded to JSON
     */
    public static function encodeJson($data, int $options = 0): string
    {
        $json = json_encode($data, $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                'Failed to encode data to JSON: ' . json_last_error_msg(),
                ['json_error' => json_last_error_msg()]
            );
        }

        return $json;
    }

    /**
     * Encode data to a pretty-printed JSON string.
     *
     * @param mixed $data The data to encode
     * @return string The pretty-printed JSON string
     * @throws ValidationException If the data cannot be encoded to JSON
     */
    public static function encodePrettyJson($data): string
    {
        return self::encodeJson($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param string $json The string to check
     * @return bool True if the string is valid JSON, false otherwise
     */
    public static function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}