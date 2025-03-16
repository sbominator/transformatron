<?php

namespace SBOMinator\Transformatron\Validation;

use SBOMinator\Transformatron\Config\CycloneDxFieldConfig;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Util\ValidationUtil;

/**
 * Validator for CycloneDX format.
 *
 * Provides validation methods for CycloneDX data.
 */
class CycloneDxValidator
{
    /**
     * Validate CycloneDX data.
     *
     * @param array<string, mixed> $data The CycloneDX data to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields using ValidationUtil
        $requiredFields = CycloneDxFieldConfig::getRequiredCycloneDxFields();

        // Use ValidationUtil to check for missing required fields
        $missingFields = array_filter($requiredFields, function($field) use ($data) {
            return !isset($data[$field]);
        });

        if (!empty($missingFields)) {
            $errors[] = sprintf('Missing required CycloneDX fields: %s', implode(', ', $missingFields));
        }

        // Validate bomFormat using ValidationUtil
        if (isset($data['bomFormat'])) {
            try {
                ValidationUtil::validateFieldValue(
                    $data,
                    'bomFormat',
                    'CycloneDX',
                    sprintf('Invalid CycloneDX bomFormat: %s', $data['bomFormat'])
                );
            } catch (ValidationException $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $errors[] = 'Missing required CycloneDX field: bomFormat';
        }

        // Validate specVersion if present
        if (isset($data['specVersion']) && !$this->isValidSpecVersion($data['specVersion'])) {
            $errors[] = sprintf('Invalid CycloneDX specVersion: %s', $data['specVersion']);
        }

        // Add more specific validations as needed

        return $errors;
    }

    /**
     * Validate CycloneDX data and throw exception if invalid.
     *
     * @param array<string, mixed> $data The CycloneDX data to validate
     * @throws ValidationException If validation fails
     */
    public function validateAndThrow(array $data): void
    {
        $errors = $this->validate($data);

        if (!empty($errors)) {
            throw new ValidationException(
                $errors[0],
                ['validation_errors' => $errors]
            );
        }
    }

    /**
     * Check if CycloneDX specVersion is valid.
     *
     * @param string $version The CycloneDX specVersion to check
     * @return bool True if the version is valid, false otherwise
     */
    private function isValidSpecVersion(string $version): bool
    {
        // Add supported CycloneDX versions
        $supportedVersions = ['1.4', '1.3', '1.2', '1.1', '1.0'];

        return in_array($version, $supportedVersions);
    }

    /**
     * Validate a component in CycloneDX data.
     *
     * @param array<string, mixed> $component The component to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validateComponent(array $component): array
    {
        $errors = [];

        // Define required component fields
        $requiredComponentFields = ['name', 'type'];

        // Use ValidationUtil to check required fields
        if (!ValidationUtil::hasRequiredKeys($component, $requiredComponentFields)) {
            foreach ($requiredComponentFields as $field) {
                if (!isset($component[$field])) {
                    $errors[] = sprintf('Missing required component field: %s', $field);
                }
            }
        }

        // Validate component type if present
        if (isset($component['type']) && !$this->isValidComponentType($component['type'])) {
            $errors[] = sprintf('Invalid component type: %s', $component['type']);
        }

        // Validate licenses if present
        if (isset($component['licenses']) && !$this->areValidLicenses($component['licenses'])) {
            $errors[] = 'Invalid licenses format in component';
        }

        // Add more specific component validations as needed

        return $errors;
    }

    /**
     * Check if component type is valid.
     *
     * @param string $type The component type to check
     * @return bool True if the type is valid, false otherwise
     */
    private function isValidComponentType(string $type): bool
    {
        // Add supported component types
        $supportedTypes = [
            'application', 'framework', 'library', 'container',
            'operating-system', 'device', 'firmware', 'file'
        ];

        return in_array(strtolower($type), $supportedTypes);
    }

    /**
     * Validate a dependency in CycloneDX data.
     *
     * @param array<string, mixed> $dependency The dependency to validate
     * @return array<string> Empty array if valid, or array of validation error messages
     */
    public function validateDependency(array $dependency): array
    {
        $errors = [];

        // Define required dependency fields
        $requiredDependencyFields = ['ref', 'dependsOn'];

        // Use ValidationUtil to check required fields
        foreach ($requiredDependencyFields as $field) {
            if (!isset($dependency[$field])) {
                $errors[] = sprintf('Missing required dependency field: %s', $field);
            }
        }

        // Validate dependsOn field is an array
        if (isset($dependency['dependsOn']) && !is_array($dependency['dependsOn'])) {
            $errors[] = 'Missing or invalid required dependency field: dependsOn';
        }

        return $errors;
    }

    /**
     * Check if licenses format is valid.
     *
     * @param array<mixed> $licenses The licenses array to check
     * @return bool True if the licenses format is valid, false otherwise
     */
    private function areValidLicenses(array $licenses): bool
    {
        if (empty($licenses)) {
            return true;
        }

        foreach ($licenses as $license) {
            if (!is_array($license) || !isset($license['license'])) {
                return false;
            }

            if (!isset($license['license']['id']) && !isset($license['license']['name'])) {
                return false;
            }
        }

        return true;
    }
}