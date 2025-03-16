<?php

namespace SBOMinator\Transformatron\Converter;

use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Exception\ConversionException;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Util\JsonUtil;

/**
 * Abstract base class for SBOM converters.
 *
 * Provides common functionality for all SBOM converters.
 */
abstract class AbstractConverter implements ConverterInterface
{
    /**
     * Convert SBOM content from source format to target format.
     *
     * @param string $json The JSON string to convert
     * @return ConversionResult The conversion result containing the converted content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails catastrophically
     */
    public function convert(string $json): ConversionResult
    {
        $errors = [];
        $warnings = [];
        $isSuccessful = true;
        $targetJson = '{}'; // Default empty JSON object

        try {
            // Decode the input JSON
            try {
                $sourceData = JsonUtil::decodeJson($json);
            } catch (ValidationException $e) {
                // Critical error - can't even parse the JSON
                $error = ConversionError::createCritical(
                    $e->getMessage(),
                    'JsonParser',
                    ['original_json' => substr($json, 0, 100) . (strlen($json) > 100 ? '...' : '')],
                    'json_parse_error',
                    $e
                );

                throw new ConversionException(
                    $e->getMessage(),
                    $this->getSourceFormat(),
                    $this->getTargetFormat()
                );
            }

            // Validate the source data - collect non-critical validation errors
            $validationErrors = $this->validateSourceData($sourceData);

            if (!empty($validationErrors)) {
                foreach ($validationErrors as $error) {
                    $errors[] = $error;
                    if ($error->isSeverityOrWorse(ConversionError::SEVERITY_ERROR)) {
                        $isSuccessful = false;
                    }
                }

                // If there are critical validation errors, we can't proceed
                $criticalErrors = array_filter($validationErrors, function(ConversionError $error) {
                    return $error->getSeverity() === ConversionError::SEVERITY_CRITICAL;
                });

                if (!empty($criticalErrors)) {
                    $error = reset($criticalErrors);
                    throw new ValidationException(
                        $error->getMessage(),
                        ['validation_errors' => array_map(function($e) { return (string)$e; }, $validationErrors)]
                    );
                }
            }

            // Initialize the target data structure
            $targetData = $this->getInitialTargetData();

            // Perform the conversion - collect conversion errors
            $targetData = $this->mapSourceToTarget($sourceData, $targetData, $warnings, $errors);

            // Add warnings for unknown fields
            $unknownFieldWarnings = $this->checkUnknownSourceFields($sourceData);
            $warnings = array_merge($warnings, $unknownFieldWarnings);

            // Ensure default metadata is added if needed
            $targetData = $this->ensureRequiredDefaultData($targetData);

            // Encode the target data to JSON
            try {
                $targetJson = JsonUtil::encodePrettyJson($targetData);
            } catch (\Exception $e) {
                $errors[] = ConversionError::createError(
                    'Failed to encode target data to JSON: ' . $e->getMessage(),
                    'JsonEncoder',
                    ['partial_target_data' => $targetData],
                    'json_encode_error',
                    $e
                );
                $isSuccessful = false;
            }

            // Return the conversion result with all collected errors and warnings
            return new ConversionResult($targetJson, $this->getTargetFormat(), $warnings, $errors, $isSuccessful);
        } catch (ValidationException $exception) {
            // Handle validation failures - they're expected in some cases
            $errors[] = ConversionError::createCritical(
                $exception->getMessage(),
                'Validator',
                ['validation_errors' => $exception->getValidationErrors()],
                'validation_error',
                $exception
            );

            // Return a failed result with the collected errors
            return new ConversionResult($targetJson, $this->getTargetFormat(), $warnings, $errors, false);
        } catch (ConversionException $exception) {
            // Re-throw conversion exceptions as they're explicitly thrown by our code
            throw $exception;
        } catch (\Exception $exception) {
            // Handle any other unexpected exceptions
            $errors[] = ConversionError::createCritical(
                'Unexpected error during conversion: ' . $exception->getMessage(),
                'Converter',
                ['exception_class' => get_class($exception)],
                'unexpected_error',
                $exception
            );

            // Convert to our standard exception type
            throw new ConversionException(
                $exception->getMessage(),
                $this->getSourceFormat(),
                $this->getTargetFormat()
            );
        }
    }

    /**
     * Validate the source data.
     *
     * @param array<string, mixed> $sourceData The source data to validate
     * @return array<ConversionError> Array of validation errors
     */
    abstract protected function validateSourceData(array $sourceData): array;

    /**
     * Get initial target data structure.
     *
     * @return array<string, mixed> Initial target data structure
     */
    abstract protected function getInitialTargetData(): array;

    /**
     * Map source data to target data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @param array<string, mixed> $targetData Initial target data structure
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @param array<ConversionError> &$errors Array to collect errors during conversion
     * @return array<string, mixed> Updated target data
     */
    abstract protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array;

    /**
     * Check for unknown fields in the source data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @return array<string> Warnings for unknown fields
     */
    abstract protected function checkUnknownSourceFields(array $sourceData): array;

    /**
     * Ensure required default data is added to the target data if missing.
     *
     * @param array<string, mixed> $targetData Target data to update
     * @return array<string, mixed> Updated target data with defaults
     */
    abstract protected function ensureRequiredDefaultData(array $targetData): array;

    /**
     * Transform field value using specified transform method.
     *
     * @param mixed $value Value to transform
     * @param string|null $transform Transform method name
     * @param string $fieldName Field name for special handling
     * @param array<string> &$warnings Warnings array for methods that need it
     * @param array<ConversionError> &$errors Errors array for methods that need it
     * @return mixed Transformed value
     */
    protected function transformFieldValue($value, ?string $transform, string $fieldName, array &$warnings, array &$errors = [])
    {
        if ($transform === null || !method_exists($this, $transform)) {
            return $value;
        }

        try {
            // Special handling for fields that need warnings and errors arrays
            $needsCollections = in_array($fieldName, ['packages', 'relationships', 'components', 'dependencies']);

            if ($needsCollections) {
                return $this->{$transform}($value, $warnings, $errors);
            } else {
                return $this->{$transform}($value);
            }
        } catch (\Exception $e) {
            // Record the error and return the original value
            $errors[] = ConversionError::createError(
                "Error transforming field '{$fieldName}': " . $e->getMessage(),
                "Transform.{$transform}",
                ['original_value' => is_scalar($value) ? $value : gettype($value)],
                'transform_error',
                $e
            );

            return $value;
        }
    }
}