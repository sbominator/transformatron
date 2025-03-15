<?php

namespace SBOMinator\Transformatron\Exception;

/**
 * Exception thrown when SBOM validation fails
 */
class ValidationException extends \Exception implements ExceptionInterface
{
    /**
     * Array of validation errors.
     */
    private array $validationErrors;
    
    /**
     * Constructor.
     */
    public function __construct(string $message, array $validationErrors = [])
    {
        parent::__construct($message);
        
        $this->validationErrors = $validationErrors;
    }
    
    /**
     * Get the validation errors.
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}