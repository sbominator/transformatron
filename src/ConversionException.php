<?php

namespace SBOMinator\Converter;

/**
 * Exception thrown when SBOM conversion fails
 */
class ConversionException extends \Exception
{
    /**
     * @var string|null The source format that was being converted
     */
    private ?string $sourceFormat;
    
    /**
     * @var string|null The target format that was being converted to
     */
    private ?string $targetFormat;
    
    /**
     * Constructor for the ConversionException class
     * 
     * @param string $message The exception message
     * @param string|null $sourceFormat The source format
     * @param string|null $targetFormat The target format
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message, ?string $sourceFormat = null, ?string $targetFormat = null) {
        parent::__construct($message);
        
        $this->sourceFormat = $sourceFormat;
        $this->targetFormat = $targetFormat;
    }
    
    /**
     * Get the source format
     * 
     * @return string|null
     */
    public function getSourceFormat(): ?string
    {
        return $this->sourceFormat;
    }
    
    /**
     * Get the target format
     * 
     * @return string|null
     */
    public function getTargetFormat(): ?string
    {
        return $this->targetFormat;
    }
}