<?php

namespace SBOMinator\Converter;

/**
 * Class to hold the result of an SBOM conversion
 */
class ConversionResult
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
     * Constructor for the ConversionResult class
     * 
     * @param string $content The converted SBOM content
     * @param string $format The format of the converted SBOM
     */
    public function __construct(string $content, string $format)
    {
        $this->content = $content;
        $this->format = $format;
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
     * Get the format of the converted SBOM
     * 
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }
}