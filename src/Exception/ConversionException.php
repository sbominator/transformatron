<?php

namespace SBOMinator\Transformatron\Exception;

/**
 * Exception thrown when SBOM conversion fails.
 */
class ConversionException extends \Exception implements ExceptionInterface
{
    /**
     * The source format that was being converted.
     */
    private string $sourceFormat;
    
    /**
     * The target format that was being converted to.
     */
    private string $targetFormat;
    
    /**
     * Constructor.
     */
    public function __construct(string $message, string $sourceFormat, string $targetFormat) {
        parent::__construct(sprintf('Failed to convert %s to %s: %s', $sourceFormat, $targetFormat, $message));
        
        $this->sourceFormat = $sourceFormat;
        $this->targetFormat = $targetFormat;
    }
    
    /**
     * Get the source format.
     */
    public function getSourceFormat(): ?string
    {
        return $this->sourceFormat;
    }
    
    /**
     * Get the target format.
     */
    public function getTargetFormat(): ?string
    {
        return $this->targetFormat;
    }
}