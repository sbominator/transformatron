<?php

namespace SBOMinator\Converter;

/**
 * Main converter class for transforming between SPDX and CycloneDX formats
 */
class Converter
{
    // SPDX to CycloneDX field mappings
    private const SPDX_TO_CYCLONEDX_MAPPINGS = [
        'spdxVersion' => ['field' => 'specVersion', 'transform' => 'transformSpdxVersion'],
        'dataLicense' => ['field' => 'license', 'transform' => null],
        'name' => ['field' => 'name', 'transform' => null],
        'SPDXID' => ['field' => 'serialNumber', 'transform' => 'transformSpdxId'],
        'documentNamespace' => ['field' => 'documentNamespace', 'transform' => null],
        'creationInfo' => ['field' => 'metadata', 'transform' => 'transformCreationInfo']
    ];
    
    // CycloneDX to SPDX field mappings
    private const CYCLONEDX_TO_SPDX_MAPPINGS = [
        'bomFormat' => ['field' => null, 'transform' => null], // No direct mapping
        'specVersion' => ['field' => 'spdxVersion', 'transform' => 'transformSpecVersion'],
        'version' => ['field' => null, 'transform' => null], // No direct mapping
        'serialNumber' => ['field' => 'SPDXID', 'transform' => 'transformSerialNumber'],
        'name' => ['field' => 'name', 'transform' => null],
        'metadata' => ['field' => 'creationInfo', 'transform' => 'transformMetadata']
    ];
    
    /**
     * Constructor for the Converter class
     */
    public function __construct()
    {
        // Will be implemented later
    }

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
        $spdxData = $this->decodeJson($json);
        
        // Initialize CycloneDX structure and warnings array
        $cyclonedxData = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'components' => []
        ];
        $warnings = [];
        
        // Map fields from SPDX to CycloneDX
        foreach (self::SPDX_TO_CYCLONEDX_MAPPINGS as $spdxField => $mapping) {
            if (isset($spdxData[$spdxField])) {
                $value = $spdxData[$spdxField];
                
                // Apply transformation if needed
                if ($mapping['transform'] !== null && method_exists($this, $mapping['transform'])) {
                    $value = $this->{$mapping['transform']}($value);
                }
                
                // Set the value in CycloneDX data
                if ($mapping['field'] !== null) {
                    $cyclonedxData[$mapping['field']] = $value;
                }
            } else {
                $warnings[] = "Missing SPDX field: {$spdxField}";
            }
        }
        
        // Check for unknown fields in SPDX
        foreach (array_keys($spdxData) as $field) {
            if (!array_key_exists($field, self::SPDX_TO_CYCLONEDX_MAPPINGS)) {
                $warnings[] = "Unknown or unmapped SPDX field: {$field}";
            }
        }
        
        // Always include metadata section
        if (!isset($cyclonedxData['metadata'])) {
            $cyclonedxData['metadata'] = [
                'timestamp' => date('c'),
                'tools' => [
                    [
                        'vendor' => 'SBOMinator',
                        'name' => 'Converter',
                        'version' => '1.0.0'
                    ]
                ]
            ];
        }
        
        // Convert to JSON
        $cyclonedxContent = json_encode($cyclonedxData);
        
        return new ConversionResult($cyclonedxContent, 'CycloneDX', $warnings);
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
        $cyclonedxData = $this->decodeJson($json);
        
        // Initialize SPDX structure and warnings array
        $spdxData = [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'documentNamespace' => 'https://sbominator.example/spdx/placeholder-' . uniqid(),
            'packages' => [],
            'relationships' => []
        ];
        $warnings = [];
        
        // Map fields from CycloneDX to SPDX
        foreach (self::CYCLONEDX_TO_SPDX_MAPPINGS as $cyclonedxField => $mapping) {
            if (isset($cyclonedxData[$cyclonedxField])) {
                $value = $cyclonedxData[$cyclonedxField];
                
                // Apply transformation if needed
                if ($mapping['transform'] !== null && method_exists($this, $mapping['transform'])) {
                    $value = $this->{$mapping['transform']}($value);
                }
                
                // Set the value in SPDX data
                if ($mapping['field'] !== null) {
                    $spdxData[$mapping['field']] = $value;
                }
            } else {
                $warnings[] = "Missing CycloneDX field: {$cyclonedxField}";
            }
        }
        
        // Check for unknown fields in CycloneDX
        foreach (array_keys($cyclonedxData) as $field) {
            if (!array_key_exists($field, self::CYCLONEDX_TO_SPDX_MAPPINGS)) {
                $warnings[] = "Unknown or unmapped CycloneDX field: {$field}";
            }
        }
        
        // Always include creationInfo section
        if (!isset($spdxData['creationInfo'])) {
            $spdxData['creationInfo'] = [
                'created' => date('c'),
                'creators' => [
                    'Tool: SBOMinator-Converter-1.0'
                ]
            ];
        }
        
        // Convert to JSON
        $spdxContent = json_encode($spdxData);
        
        return new ConversionResult($spdxContent, 'SPDX', $warnings);
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
    
    /**
     * Transform SPDX version to CycloneDX spec version
     * 
     * @param string $spdxVersion The SPDX version
     * @return string The CycloneDX spec version
     */
    protected function transformSpdxVersion(string $spdxVersion): string
    {
        // Simple mapping example
        return match ($spdxVersion) {
            'SPDX-2.3' => '1.4',
            'SPDX-2.2' => '1.3',
            'SPDX-2.1' => '1.2',
            default => '1.4', // Default to latest
        };
    }
    
    /**
     * Transform SPDX ID to CycloneDX serial number
     * 
     * @param string $spdxId The SPDX ID
     * @return string The CycloneDX serial number
     */
    protected function transformSpdxId(string $spdxId): string
    {
        // Remove the "SPDXRef-" prefix if present
        return str_replace('SPDXRef-', '', $spdxId);
    }
    
    /**
     * Transform SPDX creation info to CycloneDX metadata
     * 
     * @param array $creationInfo The SPDX creation info
     * @return array The CycloneDX metadata
     */
    protected function transformCreationInfo(array $creationInfo): array
    {
        $metadata = [
            'timestamp' => $creationInfo['created'] ?? date('c'),
            'tools' => []
        ];
        
        // Extract tool information from creators
        if (isset($creationInfo['creators']) && is_array($creationInfo['creators'])) {
            foreach ($creationInfo['creators'] as $creator) {
                if (strpos($creator, 'Tool:') === 0) {
                    $toolInfo = trim(substr($creator, 5));
                    $parts = explode('-', $toolInfo);
                    
                    if (count($parts) >= 2) {
                        $metadata['tools'][] = [
                            'vendor' => $parts[0],
                            'name' => $parts[0],
                            'version' => $parts[1] ?? '1.0'
                        ];
                    }
                }
            }
        }
        
        // Add default tool if none found
        if (empty($metadata['tools'])) {
            $metadata['tools'][] = [
                'vendor' => 'SBOMinator',
                'name' => 'Converter',
                'version' => '1.0.0'
            ];
        }
        
        return $metadata;
    }
    
    /**
     * Transform CycloneDX spec version to SPDX version
     * 
     * @param string $specVersion The CycloneDX spec version
     * @return string The SPDX version
     */
    protected function transformSpecVersion(string $specVersion): string
    {
        // Simple mapping example
        return match ($specVersion) {
            '1.4' => 'SPDX-2.3',
            '1.3' => 'SPDX-2.2',
            '1.2' => 'SPDX-2.1',
            default => 'SPDX-2.3', // Default to latest
        };
    }
    
    /**
     * Transform CycloneDX serial number to SPDX ID
     * 
     * @param string $serialNumber The CycloneDX serial number
     * @return string The SPDX ID
     */
    protected function transformSerialNumber(string $serialNumber): string
    {
        // Add "SPDXRef-" prefix if not present
        return strpos($serialNumber, 'SPDXRef-') === 0
            ? $serialNumber
            : 'SPDXRef-' . $serialNumber;
    }
    
    /**
     * Transform CycloneDX metadata to SPDX creation info
     * 
     * @param array $metadata The CycloneDX metadata
     * @return array The SPDX creation info
     */
    protected function transformMetadata(array $metadata): array
    {
        $creationInfo = [
            'created' => $metadata['timestamp'] ?? date('c'),
            'creators' => []
        ];
        
        // Extract tool information
        if (isset($metadata['tools']) && is_array($metadata['tools'])) {
            foreach ($metadata['tools'] as $tool) {
                if (isset($tool['name'])) {
                    $vendor = $tool['vendor'] ?? '';
                    $name = $tool['name'];
                    $version = $tool['version'] ?? '1.0';
                    
                    $creationInfo['creators'][] = "Tool: {$vendor}{$name}-{$version}";
                }
            }
        }
        
        // Add default creator if none found
        if (empty($creationInfo['creators'])) {
            $creationInfo['creators'][] = 'Tool: SBOMinator-Converter-1.0';
        }
        
        return $creationInfo;
    }
}