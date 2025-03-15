<?php

namespace SBOMinator\Converter;

/**
 * Exception thrown when SBOM validation fails
 */
class ValidationException extends \Exception
{
    /**
     * @var array Array of validation errors
     */
    private array $validationErrors;
    
    /**
     * Constructor for the ValidationException class
     * 
     * @param string $message The exception message
     * @param array $validationErrors Array of validation errors
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message, array $validationErrors = [])
    {
        parent::__construct($message);
        
        $this->validationErrors = $validationErrors;
    }
    
    /**
     * Get the validation errors
     * 
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}