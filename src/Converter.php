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
        'creationInfo' => ['field' => 'metadata', 'transform' => 'transformCreationInfo'],
        'packages' => ['field' => 'components', 'transform' => 'transformPackagesToComponents'],
        'relationships' => ['field' => 'dependencies', 'transform' => 'transformRelationshipsToDependencies']
    ];
    
    // CycloneDX to SPDX field mappings
    private const CYCLONEDX_TO_SPDX_MAPPINGS = [
        'bomFormat' => ['field' => null, 'transform' => null], // No direct mapping
        'specVersion' => ['field' => 'spdxVersion', 'transform' => 'transformSpecVersion'],
        'version' => ['field' => null, 'transform' => null], // No direct mapping
        'serialNumber' => ['field' => 'SPDXID', 'transform' => 'transformSerialNumber'],
        'name' => ['field' => 'name', 'transform' => null],
        'metadata' => ['field' => 'creationInfo', 'transform' => 'transformMetadata'],
        'components' => ['field' => 'packages', 'transform' => 'transformComponentsToPackages'],
        'dependencies' => ['field' => 'relationships', 'transform' => 'transformDependenciesToRelationships']
    ];
    
    // SPDX package field to CycloneDX component field mappings
    private const PACKAGE_TO_COMPONENT_MAPPINGS = [
        'name' => 'name',
        'versionInfo' => 'version',
        'downloadLocation' => 'purl', // Will need transformation
        'supplier' => 'supplier', // Will need transformation
        'licenseConcluded' => 'licenses', // Will need transformation
        'licenseDeclared' => 'licenses', // Secondary source
        'description' => 'description',
        'packageFileName' => 'purl', // Secondary source for purl
        'packageVerificationCode' => 'hashes', // Will need transformation
        'checksums' => 'hashes' // Will need transformation
    ];
    
    // CycloneDX component field to SPDX package field mappings
    private const COMPONENT_TO_PACKAGE_MAPPINGS = [
        'name' => 'name',
        'version' => 'versionInfo',
        'purl' => 'downloadLocation', // Will need transformation
        'supplier' => 'supplier', // Will need transformation
        'licenses' => 'licenseConcluded', // Will need transformation
        'description' => 'description',
        'hashes' => 'checksums' // Will need transformation
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
                    if ($spdxField === 'packages' || $spdxField === 'relationships') {
                        // For packages and relationships, we need to pass the warnings array by reference
                        $value = $this->{$mapping['transform']}($value, $warnings);
                    } else {
                        $value = $this->{$mapping['transform']}($value);
                    }
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
                    if ($cyclonedxField === 'components' || $cyclonedxField === 'dependencies') {
                        // For components and dependencies, we need to pass the warnings array by reference
                        $value = $this->{$mapping['transform']}($value, $warnings);
                    } else {
                        $value = $this->{$mapping['transform']}($value);
                    }
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
     * Transform SPDX packages array to CycloneDX components array
     * 
     * @param array $packages SPDX packages array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array CycloneDX components array
     */
    protected function transformPackagesToComponents(array $packages, array &$warnings): array
    {
        $components = [];
        
        foreach ($packages as $package) {
            $component = [
                'type' => 'library', // Default type
                'bom-ref' => isset($package['SPDXID']) ? $this->transformSpdxId($package['SPDXID']) : uniqid('component-')
            ];
            
            // Map known fields from package to component
            foreach (self::PACKAGE_TO_COMPONENT_MAPPINGS as $packageField => $componentField) {
                if (isset($package[$packageField])) {
                    // Handle special transformations
                    switch ($packageField) {
                        case 'checksums':
                            $component['hashes'] = $this->transformSpdxChecksums($package[$packageField], $warnings);
                            break;
                        
                        case 'packageVerificationCode':
                            if (!isset($component['hashes'])) {
                                $component['hashes'] = [];
                            }
                            
                            // Add verification code as a hash
                            if (isset($package[$packageField]['value'])) {
                                $component['hashes'][] = [
                                    'alg' => 'SHA1',
                                    'content' => $package[$packageField]['value']
                                ];
                            }
                            break;
                        
                        case 'licenseConcluded':
                        case 'licenseDeclared':
                            // Only process if licenses haven't been set yet
                            if (!isset($component['licenses']) && !empty($package[$packageField])) {
                                $component['licenses'] = [
                                    [
                                        'license' => [
                                            'id' => $package[$packageField]
                                        ]
                                    ]
                                ];
                            }
                            break;
                            
                        default:
                            // Direct field mapping
                            $component[$componentField] = $package[$packageField];
                    }
                }
            }
            
            // Check for unknown fields in the package
            foreach (array_keys($package) as $field) {
                if (!array_key_exists($field, self::PACKAGE_TO_COMPONENT_MAPPINGS) && $field !== 'SPDXID') {
                    $warnings[] = "Unknown or unmapped package field: {$field}";
                }
            }
            
            // Check required fields
            if (!isset($component['name'])) {
                $warnings[] = "Package missing required field: name";
                $component['name'] = 'unknown-' . uniqid();
            }
            
            $components[] = $component;
        }
        
        return $components;
    }
    
    /**
     * Transform CycloneDX components array to SPDX packages array
     * 
     * @param array $components CycloneDX components array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array SPDX packages array
     */
    protected function transformComponentsToPackages(array $components, array &$warnings): array
    {
        $packages = [];
        
        foreach ($components as $component) {
            $package = [
                'SPDXID' => isset($component['bom-ref']) 
                    ? 'SPDXRef-' . $component['bom-ref'] 
                    : 'SPDXRef-' . uniqid('pkg-')
            ];
            
            // Map known fields from component to package
            foreach (self::COMPONENT_TO_PACKAGE_MAPPINGS as $componentField => $packageField) {
                if (isset($component[$componentField])) {
                    // Handle special transformations
                    switch ($componentField) {
                        case 'hashes':
                            $package['checksums'] = $this->transformCycloneDxHashes($component[$componentField], $warnings);
                            break;
                            
                        case 'licenses':
                            // Extract the first license ID if available
                            if (is_array($component[$componentField]) && !empty($component[$componentField])) {
                                $license = $component[$componentField][0];
                                if (isset($license['license']['id'])) {
                                    $package['licenseConcluded'] = $license['license']['id'];
                                } elseif (isset($license['license']['name'])) {
                                    $package['licenseConcluded'] = $license['license']['name'];
                                } else {
                                    $warnings[] = "Component license format not recognized";
                                }
                            }
                            break;
                            
                        default:
                            // Direct field mapping
                            $package[$packageField] = $component[$componentField];
                    }
                }
            }
            
            // Check for unknown fields in the component
            foreach (array_keys($component) as $field) {
                if (!array_key_exists($field, self::COMPONENT_TO_PACKAGE_MAPPINGS) && 
                    $field !== 'bom-ref' && $field !== 'type') {
                    $warnings[] = "Unknown or unmapped component field: {$field}";
                }
            }
            
            // Check required fields
            if (!isset($package['name'])) {
                $warnings[] = "Component missing required field: name";
                $package['name'] = 'unknown-' . uniqid();
            }
            
            $packages[] = $package;
        }
        
        return $packages;
    }
    
    /**
     * Transform SPDX checksums to CycloneDX hashes
     * 
     * @param array $checksums SPDX checksums array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array CycloneDX hashes array
     */
    protected function transformSpdxChecksums(array $checksums, array &$warnings): array
    {
        $hashes = [];
        
        foreach ($checksums as $checksum) {
            if (isset($checksum['algorithm']) && isset($checksum['checksumValue'])) {
                // Map SPDX algorithm to CycloneDX algorithm
                $algorithm = match(strtoupper($checksum['algorithm'])) {
                    'SHA1' => 'SHA-1',
                    'SHA256' => 'SHA-256',
                    'SHA512' => 'SHA-512',
                    'MD5' => 'MD5',
                    default => null
                };
                
                if ($algorithm) {
                    $hashes[] = [
                        'alg' => $algorithm,
                        'content' => $checksum['checksumValue']
                    ];
                } else {
                    $warnings[] = "Unsupported hash algorithm: {$checksum['algorithm']}";
                }
            } else {
                $warnings[] = "Malformed checksum entry in SPDX package";
            }
        }
        
        return $hashes;
    }
    
    /**
     * Transform CycloneDX hashes to SPDX checksums
     * 
     * @param array $hashes CycloneDX hashes array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array SPDX checksums array
     */
    protected function transformCycloneDxHashes(array $hashes, array &$warnings): array
    {
        $checksums = [];
        
        foreach ($hashes as $hash) {
            if (isset($hash['alg']) && isset($hash['content'])) {
                // Map CycloneDX algorithm to SPDX algorithm
                $algorithm = match(strtoupper($hash['alg'])) {
                    'SHA-1', 'SHA1' => 'SHA1',
                    'SHA-256', 'SHA256' => 'SHA256',
                    'SHA-512', 'SHA512' => 'SHA512',
                    'MD5' => 'MD5',
                    default => null
                };
                
                if ($algorithm) {
                    $checksums[] = [
                        'algorithm' => $algorithm,
                        'checksumValue' => $hash['content']
                    ];
                } else {
                    $warnings[] = "Unsupported hash algorithm: {$hash['alg']}";
                }
            } else {
                $warnings[] = "Malformed hash entry in CycloneDX component";
            }
        }
        
        return $checksums;
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
    
    /**
     * Transform SPDX relationships to CycloneDX dependencies
     * 
     * @param array $relationships SPDX relationships array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array CycloneDX dependencies array
     */
    protected function transformRelationshipsToDependencies(array $relationships, array &$warnings): array
    {
        $dependencies = [];
        $dependencyMap = [];
        
        // First pass: collect all dependency relationships
        foreach ($relationships as $relationship) {
            // Check if required fields exist
            if (!isset($relationship['spdxElementId']) || !isset($relationship['relatedSpdxElement']) || !isset($relationship['relationshipType'])) {
                $warnings[] = "Malformed relationship entry in SPDX: missing required fields";
                continue;
            }
            
            // Map only dependency relationships
            // In SPDX, A DEPENDS_ON B means A depends on B
            // In CycloneDX, we need to list B as a dependency of A
            if ($relationship['relationshipType'] === 'DEPENDS_ON') {
                $dependent = $this->transformSpdxId($relationship['spdxElementId']);
                $dependency = $this->transformSpdxId($relationship['relatedSpdxElement']);
                
                if (!isset($dependencyMap[$dependent])) {
                    $dependencyMap[$dependent] = [];
                }
                
                $dependencyMap[$dependent][] = $dependency;
            } elseif (str_contains(strtoupper($relationship['relationshipType']), 'DEPEND')) {
                // Handle other dependency-related relationship types, with warning
                $warnings[] = "Unsupported dependency relationship type: {$relationship['relationshipType']}";
            }
        }
        
        // Second pass: format into CycloneDX dependencies structure
        foreach ($dependencyMap as $ref => $deps) {
            $dependencies[] = [
                'ref' => $ref,
                'dependsOn' => $deps
            ];
        }
        
        return $dependencies;
    }
    
    /**
     * Transform CycloneDX dependencies to SPDX relationships
     * 
     * @param array $dependencies CycloneDX dependencies array
     * @param array &$warnings Array to collect warnings during conversion
     * @return array SPDX relationships array
     */
    protected function transformDependenciesToRelationships(array $dependencies, array &$warnings): array
    {
        $relationships = [];
        
        foreach ($dependencies as $dependency) {
            // Check if required fields exist
            if (!isset($dependency['ref']) || !isset($dependency['dependsOn']) || !is_array($dependency['dependsOn'])) {
                $warnings[] = "Malformed dependency entry in CycloneDX: missing required fields";
                continue;
            }
            
            $dependent = $this->transformSerialNumber($dependency['ref']);
            
            // Process each dependency
            foreach ($dependency['dependsOn'] as $dependencyRef) {
                $relationships[] = [
                    'spdxElementId' => $dependent,
                    'relatedSpdxElement' => $this->transformSerialNumber($dependencyRef),
                    'relationshipType' => 'DEPENDS_ON'
                ];
            }
        }
        
        return $relationships;
    }
}