<?php

namespace SBOMinator\Converter;

/**
 * Main converter class for transforming between SPDX and CycloneDX formats
 */
class Converter
{
    /**
     * Convert SPDX format to CycloneDX format
     *
     * @param string $json The SPDX JSON to convert
     * @return ConversionResult The conversion result with CycloneDX content
     * @throws ValidationException If the JSON is invalid
     */
    public function convertSpdxToCyclonedx(string $json): ConversionResult
    {
        // Decode the JSON input
        $data = $this->decodeJson($json);
        
        // This is a placeholder implementation
        // In a real implementation, this would transform SPDX to CycloneDX
        $cyclonedxContent = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'serialNumber' => 'placeholder-serial-number',
            'metadata' => [
                'timestamp' => date('c'),
                'tools' => [
                    [
                        'vendor' => 'SBOMinator',
                        'name' => 'Converter',
                        'version' => '1.0.0'
                    ]
                ]
            ],
            'components' => [],
            'original' => 'Converted from SPDX format'
        ]);
        
        return new ConversionResult($cyclonedxContent, 'CycloneDX');
    }
    
    /**
     * Convert CycloneDX format to SPDX format
     *
     * @param string $json The CycloneDX JSON to convert
     * @return ConversionResult The conversion result with SPDX content
     * @throws ValidationException If the JSON is invalid
     */
    public function convertCyclonedxToSpdx(string $json): ConversionResult
    {
        // Decode the JSON input
        $data = $this->decodeJson($json);
        
        // This is a placeholder implementation
        // In a real implementation, this would transform CycloneDX to SPDX
        $spdxContent = json_encode([
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'placeholder-spdx-document',
            'documentNamespace' => 'https://sbominator.example/spdx/placeholder-' . uniqid(),
            'creationInfo' => [
                'created' => date('c'),
                'creators' => [
                    'Tool: SBOMinator-Converter-1.0'
                ]
            ],
            'packages' => [],
            'relationships' => [],
            'original' => 'Converted from CycloneDX format'
        ]);
        
        return new ConversionResult($spdxContent, 'SPDX');
    }

    /**
     * Decode a JSON string into an associative array
     *
     * @param string $json The JSON string to decode
     * @return array The decoded associative array
     * @throws ValidationException If the JSON is invalid or doesn't decode to an array
     */
    protected function decodeJson(string $json): array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                'Invalid JSON: ' . json_last_error_msg(),
                ['json_error' => json_last_error_msg()]
            );
        }
        
        if (!is_array($data)) {
            throw new ValidationException(
                'JSON must decode to an array',
                ['type_error' => 'Decoded value is not an array']
            );
        }
        
        return $data;
    }
}