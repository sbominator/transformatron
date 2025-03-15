<?php

namespace SBOMinator\Transformatron;

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
     * @var array Warnings generated during conversion
     */
    private array $warnings = [];
    
    /**
     * Constructor for the ConversionResult class
     * 
     * @param string $content The converted SBOM content
     * @param string $format The format of the converted SBOM
     * @param array $warnings Warnings generated during conversion
     */
    public function __construct(string $content, string $format, array $warnings = [])
    {
        $this->content = $content;
        $this->format = $format;
        $this->warnings = $warnings;
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
     * @return array
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
     * @return array
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
     * Check if there are any warnings
     * 
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
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
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'format' => $this->format,
            'content' => json_decode($this->content, true),
            'warnings' => $this->warnings
        ];
    }
}