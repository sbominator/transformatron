<?php

namespace SBOMinator\Transformatron\Util;

use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Utility class for validation operations.
 *
 * Provides methods for validating SBOM data structures.
 */
class ValidationUtil
{
    /**
     * Validate that required fields are present in the data.
     *
     * @param array<string, mixed> $data The data to validate
     * @param array<string> $requiredFields List of required field names
     * @param string $format Format name for error messages (e.g., 'SPDX', 'CycloneDX')
     * @throws ValidationException If required fields are missing
     */
    public static function validateRequiredFields(array $data, array $requiredFields, string $format): void
    {
        $missingFields = array_filter($requiredFields, function($field) use ($data) {
            return !isset($data[$field]);
        });

        if (!empty($missingFields)) {
            throw new ValidationException(
                sprintf('Missing required %s fields: %s', $format, implode(', ', $missingFields)),
                ['missing_fields' => $missingFields]
            );
        }
    }

    /**
     * Check for unknown fields in the data.
     *
     * @param array<string, mixed> $data Data to check
     * @param array<string> $knownFields Known field names
     * @param string $formatName Format name for warning message
     * @return array<string> Warnings for unknown fields
     */
    public static function collectUnknownFieldWarnings(array $data, array $knownFields, string $formatName): array
    {
        $unknownFields = array_diff(array_keys($data), $knownFields);

        return array_map(function($field) use ($formatName) {
            return sprintf('Unknown or unmapped %s field: %s', $formatName, $field);
        }, $unknownFields);
    }

    /**
     * Validate that a specific field matches an expected value.
     *
     * @param array<string, mixed> $data The data to validate
     * @param string $field The field name to check
     * @param mixed $expectedValue The expected value
     * @param string $errorMessage The error message if validation fails
     * @throws ValidationException If the field doesn't match the expected value
     */
    public static function validateFieldValue(array $data, string $field, $expectedValue, string $errorMessage): void
    {
        if (!isset($data[$field]) || $data[$field] !== $expectedValue) {
            throw new ValidationException(
                $errorMessage,
                ['invalid_field' => $field]
            );
        }
    }

    /**
     * Check if an array has all required keys.
     *
     * @param array<string, mixed> $data The array to check
     * @param array<string> $requiredKeys The required keys
     * @return bool True if the array has all required keys, false otherwise
     */
    public static function hasRequiredKeys(array $data, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add warning if field is required.
     *
     * @param string $field Field name
     * @param array<string> $requiredFields List of required fields
     * @param array<string> &$warnings Warnings array to update
     */
    public static function warnIfRequiredField(string $field, array $requiredFields, array &$warnings): void
    {
        if (in_array($field, $requiredFields)) {
            $warnings[] = "Missing required field: {$field}";
        }
    }
}