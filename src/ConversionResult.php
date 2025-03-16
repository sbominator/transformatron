<?php

namespace SBOMinator\Transformatron;

use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Class to hold the result of an SBOM conversion
 */
class ConversionResult implements \JsonSerializable
{
    /**
     * @var string The converted SBOM content
     */
    private string $content;

    /**
     * @var string The format of the converted SBOM (SPDX or CycloneDX)
     */
    private string $format;

    /**
     * @var array<string> Warnings generated during conversion
     */
    private array $warnings = [];

    /**
     * @var array<ConversionError> Errors encountered during conversion
     */
    private array $errors = [];

    /**
     * @var bool Whether the conversion was successful
     */
    private bool $isSuccessful;

    /**
     * Constructor for the ConversionResult class
     *
     * @param string $content The converted SBOM content
     * @param string $format The format of the converted SBOM
     * @param array<string> $warnings Warnings generated during conversion
     * @param array<ConversionError> $errors Errors encountered during conversion
     * @param bool $isSuccessful Whether the conversion was successful
     */
    public function __construct(
        string $content,
        string $format,
        array $warnings = [],
        array $errors = [],
        bool $isSuccessful = true
    ) {
        $this->content = $content;
        $this->format = $format;
        $this->warnings = $warnings;
        $this->errors = $errors;
        $this->isSuccessful = $isSuccessful;
    }

    /**
     * Get the converted SBOM content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the decoded SBOM content as an array
     *
     * @return array<string, mixed>
     */
    public function getContentAsArray(): array
    {
        return json_decode($this->content, true) ?? [];
    }

    /**
     * Get the format of the converted SBOM
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get the warnings generated during conversion
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Add a warning to the warnings list
     *
     * @param string $warning The warning message
     * @return self
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;

        return $this;
    }

    /**
     * Add multiple warnings to the warnings list
     *
     * @param array<string> $warnings The warning messages
     * @return self
     */
    public function addWarnings(array $warnings): self
    {
        $this->warnings = array_merge($this->warnings, $warnings);

        return $this;
    }

    /**
     * Check if there are any warnings
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get all errors encountered during conversion
     *
     * @return array<ConversionError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors of a specific severity or higher
     *
     * @param string $minSeverity Minimum severity level
     * @return array<ConversionError>
     */
    public function getErrorsBySeverity(string $minSeverity): array
    {
        return array_filter($this->errors, function(ConversionError $error) use ($minSeverity) {
            return $error->isSeverityOrWorse($minSeverity);
        });
    }

    /**
     * Get errors for a specific component
     *
     * @param string $component Component name
     * @return array<ConversionError>
     */
    public function getErrorsByComponent(string $component): array
    {
        return array_filter($this->errors, function(ConversionError $error) use ($component) {
            return $error->getComponent() === $component;
        });
    }

    /**
     * Add an error to the errors list
     *
     * @param ConversionError $error The error
     * @return self
     */
    public function addError(ConversionError $error): self
    {
        $this->errors[] = $error;

        if ($error->isSeverityOrWorse(ConversionError::SEVERITY_ERROR)) {
            $this->isSuccessful = false;
        }

        return $this;
    }

    /**
     * Add multiple errors to the errors list
     *
     * @param array<ConversionError> $errors The errors
     * @return self
     */
    public function addErrors(array $errors): self
    {
        foreach ($errors as $error) {
            $this->addError($error);
        }

        return $this;
    }

    /**
     * Check if there are any errors
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are critical errors
     *
     * @return bool
     */
    public function hasCriticalErrors(): bool
    {
        return !empty($this->getErrorsBySeverity(ConversionError::SEVERITY_CRITICAL));
    }

    /**
     * Check if the conversion was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return json_decode($this->content, true);
    }

    /**
     * Get the result as a string representation of the JSON
     *
     * @param int $options JSON encoding options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return $this->content;
    }

    /**
     * Get a result summary including content and warnings
     *
     * @param bool $includeContent Whether to include the content in the summary
     * @return array<string, mixed>
     */
    public function getSummary(bool $includeContent = true): array
    {
        $summary = [
            'format' => $this->format,
            'success' => $this->isSuccessful,
            'warnings' => $this->warnings,
            'errors' => array_map(function(ConversionError $error) {
                return [
                    'message' => $error->getMessage(),
                    'severity' => $error->getSeverity(),
                    'component' => $error->getComponent(),
                    'context' => $error->getContext(),
                    'code' => $error->getCode()
                ];
            }, $this->errors)
        ];

        if ($includeContent) {
            $summary['content'] = json_decode($this->content, true);
        }

        return $summary;
    }

    /**
     * Get error messages as a simple array of strings
     *
     * @return array<string>
     */
    public function getErrorMessages(): array
    {
        return array_map(function(ConversionError $error) {
            return (string)$error;
        }, $this->errors);
    }
}