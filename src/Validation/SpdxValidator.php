<?php

namespace SBOMinator\Transformatron\Validation;

use SBOMinator\Transformatron\Config\SpdxFieldConfig;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Util\ValidationUtil;

/**
 * Validator for SPDX format.
 *
 * Provides validation methods for SPDX data.
 */
class SpdxValidator
{
    /**
     * Validate SPDX data.
     *
     * @param array<string, mixed> $data The SPDX data to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields using ValidationUtil
        $requiredFields = SpdxFieldConfig::getRequiredSpdxFields();
        $missingFields = array_filter($requiredFields, function($field) use ($data) {
            return !isset($data[$field]);
        });

        if (!empty($missingFields)) {
            $errors[] = sprintf('Missing required SPDX fields: %s', implode(', ', $missingFields));
        }

        // Validate specific field values if needed
        if (isset($data['spdxVersion']) && !$this->isValidSpdxVersion($data['spdxVersion'])) {
            $errors[] = sprintf('Invalid SPDX version: %s', $data['spdxVersion']);
        }

        // Add more specific validations as needed

        return $errors;
    }

    /**
     * Validate SPDX data and throw exception if invalid.
     *
     * @param array<string, mixed> $data The SPDX data to validate
     * @throws ValidationException If validation fails
     */
    public function validateAndThrow(array $data): void
    {
        $errors = $this->validate($data);

        if (!empty($errors)) {
            throw new ValidationException($errors[0], ['validation_errors' => $errors]);
        }
    }

    /**
     * Check if SPDX version is valid.
     *
     * @param string $version The SPDX version to check
     * @return bool True if the version is valid, false otherwise
     */
    private function isValidSpdxVersion(string $version): bool
    {
        // Add supported SPDX versions
        $supportedVersions = ['SPDX-2.3', 'SPDX-2.2', 'SPDX-2.1'];

        return in_array($version, $supportedVersions);
    }

    /**
     * Validate a package in SPDX data.
     *
     * @param array<string, mixed> $package The package to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validatePackage(array $package): array
    {
        $errors = [];

        // Define required package fields
        $requiredPackageFields = ['name', 'SPDXID'];

        // Use ValidationUtil to check if all required keys are present
        foreach ($requiredPackageFields as $field) {
            if (!isset($package[$field])) {
                $errors[] = sprintf('Missing required package field: %s', $field);
            }
        }

        // Add more specific package validations as needed

        return $errors;
    }

    /**
     * Validate a relationship in SPDX data.
     *
     * @param array<string, mixed> $relationship The relationship to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validateRelationship(array $relationship): array
    {
        $errors = [];

        // Define required relationship fields
        $requiredRelationshipFields = ['spdxElementId', 'relatedSpdxElement', 'relationshipType'];

        // Use ValidationUtil to check required fields
        if (!ValidationUtil::hasRequiredKeys($relationship, $requiredRelationshipFields)) {
            foreach ($requiredRelationshipFields as $field) {
                if (!isset($relationship[$field])) {
                    $errors[] = sprintf('Missing required relationship field: %s', $field);
                }
            }
        }

        // Validate relationship type if present
        if (isset($relationship['relationshipType']) &&
            !$this->isValidRelationshipType($relationship['relationshipType'])) {
            $errors[] = sprintf('Invalid relationship type: %s', $relationship['relationshipType']);
        }

        return $errors;
    }

    /**
     * Check if relationship type is valid.
     *
     * @param string $type The relationship type to check
     * @return bool True if the type is valid, false otherwise
     */
    private function isValidRelationshipType(string $type): bool
    {
        // Add supported relationship types
        $supportedTypes = [
            'DEPENDS_ON', 'DEPENDENCY_OF', 'DESCRIBES', 'DESCRIBED_BY',
            'CONTAINS', 'CONTAINED_BY', 'GENERATES', 'GENERATED_FROM',
            'DYNAMIC_LINK', 'STATIC_LINK', 'BUILD_DEPENDENCY_OF', 'DEV_DEPENDENCY_OF',
            'RUNTIME_DEPENDENCY_OF', 'OPTIONAL_DEPENDENCY_OF'
        ];

        return in_array($type, $supportedTypes);
    }
}