<?php

namespace SBOMinator\Converter;

use SBOMinator\Converter\Exception\ConversionException;
use SBOMinator\Converter\Exception\ValidationException;

/**
 * Main converter class for transforming between SPDX and CycloneDX formats
 */
class Converter
{
    /**
     * Format constants
     */
    public const FORMAT_SPDX = 'SPDX';
    public const FORMAT_CYCLONEDX = 'CycloneDX';
    
    /**
     * Version constants
     */
    public const SPDX_VERSION = 'SPDX-2.3';
    public const CYCLONEDX_VERSION = '1.4';
    
    /**
     * Relationship type constants
     */
    public const RELATIONSHIP_DEPENDS_ON = 'DEPENDS_ON';
    
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
    
    // Required fields for valid SPDX
    private const REQUIRED_SPDX_FIELDS = [
        'spdxVersion',
        'dataLicense',
        'SPDXID',
        'name',
        'documentNamespace'
    ];
    
    // Required fields for valid CycloneDX
    private const REQUIRED_CYCLONEDX_FIELDS = [
        'bomFormat',
        'specVersion',
        'version'
    ];
    
    /**
     * Convert SPDX format to CycloneDX format
     *
     * @param string $json The SPDX JSON to convert
     * @return ConversionResult The conversion result with CycloneDX content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convertSpdxToCyclonedx(string $json): ConversionResult
    {
        try {
            $spdxData = $this->decodeJson($json);
            $this->validateSpdxFields($spdxData);
            
            $cyclonedxData = $this->getInitialCycloneDxData();
            $warnings = [];
            
            $cyclonedxData = $this->mapSpdxToCyclonedx($spdxData, $cyclonedxData, $warnings);
            $warnings = array_merge($warnings, $this->checkUnknownSpdxFields($spdxData));
            
            if (!isset($cyclonedxData['metadata'])) {
                $cyclonedxData['metadata'] = $this->createDefaultMetadata();
            }
            
            $cyclonedxContent = json_encode($cyclonedxData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new ConversionResult($cyclonedxContent, self::FORMAT_CYCLONEDX, $warnings);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new ConversionException($exception->getMessage(), self::FORMAT_SPDX, self::FORMAT_CYCLONEDX);
        }
    }
    
    /**
     * Get initial CycloneDX data structure
     *
     * @return array Initial CycloneDX data
     */
    protected function getInitialCycloneDxData(): array
    {
        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => self::CYCLONEDX_VERSION,
            'version' => 1,
            'components' => []
        ];
    }
    
    /**
     * Convert CycloneDX format to SPDX format
     *
     * @param string $json The CycloneDX JSON to convert
     * @return ConversionResult The conversion result with SPDX content
     * @throws ValidationException If the JSON is invalid or required fields are missing
     * @throws ConversionException If the conversion fails
     */
    public function convertCyclonedxToSpdx(string $json): ConversionResult
    {
        try {
            $cyclonedxData = $this->decodeJson($json);
            $this->validateCycloneDxFields($cyclonedxData);
            
            $spdxData = $this->getInitialSpdxData();
            $warnings = [];
            
            $spdxData = $this->mapCyclonedxToSpdx($cyclonedxData, $spdxData, $warnings);
            $warnings = array_merge($warnings, $this->checkUnknownCycloneDxFields($cyclonedxData));
            
            if (!isset($spdxData['creationInfo'])) {
                $spdxData['creationInfo'] = $this->createDefaultCreationInfo();
            }
            
            $spdxContent = json_encode($spdxData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new ConversionResult($spdxContent, self::FORMAT_SPDX, $warnings);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new ConversionException($exception->getMessage(), self::FORMAT_CYCLONEDX, self::FORMAT_SPDX);
        }
    }
    
    /**
     * Get initial SPDX data structure
     *
     * @return array Initial SPDX data
     */
    protected function getInitialSpdxData(): array
    {
        return [
            'spdxVersion' => self::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'documentNamespace' => 'https://sbominator.example/spdx/placeholder-' . uniqid(),
            'packages' => [],
            'relationships' => []
        ];
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
     * Validate that required SPDX fields are present
     * 
     * @param array $data The SPDX data to validate
     * @throws ValidationException If required fields are missing
     */
    protected function validateSpdxFields(array $data): void
    {
        $missingFields = array_filter(self::REQUIRED_SPDX_FIELDS, function($field) use ($data) {
            return !isset($data[$field]);
        });
        
        if (empty($missingFields)) {
            return;
        }
        
        throw new ValidationException(
            'Missing required SPDX fields: ' . implode(', ', $missingFields),
            ['missing_fields' => $missingFields]
        );
    }
    
    /**
     * Validate that required CycloneDX fields are present
     * 
     * @param array $data The CycloneDX data to validate
     * @throws ValidationException If required fields are missing
     */
    protected function validateCycloneDxFields(array $data): void
    {
        $missingFields = array_filter(self::REQUIRED_CYCLONEDX_FIELDS, function($field) use ($data) {
            return !isset($data[$field]);
        });
        
        if (!empty($missingFields)) {
            throw new ValidationException(
                'Missing required CycloneDX fields: ' . implode(', ', $missingFields),
                ['missing_fields' => $missingFields]
            );
        }
        
        $this->validateBomFormat($data);
    }
    
    /**
     * Validate CycloneDX bomFormat field
     * 
     * @param array $data The CycloneDX data
     * @throws ValidationException If bomFormat is invalid
     */
    protected function validateBomFormat(array $data): void
    {
        if (!isset($data['bomFormat']) || $data['bomFormat'] !== 'CycloneDX') {
            throw new ValidationException(
                'Invalid CycloneDX bomFormat: ' . ($data['bomFormat'] ?? 'missing'),
                ['invalid_field' => 'bomFormat']
            );
        }
    }
    
    /**
     * Maps SPDX fields to CycloneDX fields
     * 
     * @param array $spdxData SPDX data
     * @param array $cyclonedxData Initial CycloneDX data structure
     * @param array &$warnings Array to collect warnings
     * @return array Updated CycloneDX data
     */
    protected function mapSpdxToCyclonedx(array $spdxData, array $cyclonedxData, array &$warnings): array
    {
        foreach (self::SPDX_TO_CYCLONEDX_MAPPINGS as $spdxField => $mapping) {
            if (!isset($spdxData[$spdxField])) {
                $this->warnIfRequiredField($spdxField, self::REQUIRED_SPDX_FIELDS, $warnings);
                continue;
            }
            
            if ($mapping['field'] === null) {
                continue;
            }
            
            $value = $this->transformFieldValue(
                $spdxData[$spdxField], 
                $mapping['transform'], 
                $spdxField, 
                $warnings
            );
            
            $cyclonedxData[$mapping['field']] = $value;
        }
        
        return $cyclonedxData;
    }
    
    /**
     * Maps CycloneDX fields to SPDX fields
     * 
     * @param array $cyclonedxData CycloneDX data
     * @param array $spdxData Initial SPDX data structure
     * @param array &$warnings Array to collect warnings
     * @return array Updated SPDX data
     */
    protected function mapCyclonedxToSpdx(array $cyclonedxData, array $spdxData, array &$warnings): array
    {
        foreach (self::CYCLONEDX_TO_SPDX_MAPPINGS as $cyclonedxField => $mapping) {
            if (!isset($cyclonedxData[$cyclonedxField])) {
                $this->warnIfRequiredField($cyclonedxField, self::REQUIRED_CYCLONEDX_FIELDS, $warnings);
                continue;
            }
            
            if ($mapping['field'] === null) {
                continue;
            }
            
            $value = $this->transformFieldValue(
                $cyclonedxData[$cyclonedxField], 
                $mapping['transform'], 
                $cyclonedxField, 
                $warnings
            );
            
            $spdxData[$mapping['field']] = $value;
        }
        
        return $spdxData;
    }
    
    /**
     * Add warning if field is required
     * 
     * @param string $field Field name
     * @param array $requiredFields List of required fields
     * @param array &$warnings Warnings array to update
     */
    protected function warnIfRequiredField(string $field, array $requiredFields, array &$warnings): void
    {
        if (in_array($field, $requiredFields)) {
            $warnings[] = "Missing required field: {$field}";
        }
    }
    
    /**
     * Transform field value using specified transform method
     * 
     * @param mixed $value Value to transform
     * @param string|null $transform Transform method name
     * @param string $fieldName Field name for special handling
     * @param array &$warnings Warnings array for methods that need it
     * @return mixed Transformed value
     */
    protected function transformFieldValue($value, ?string $transform, string $fieldName, array &$warnings)
    {
        if ($transform === null || !method_exists($this, $transform)) {
            return $value;
        }
        
        // Special handling for fields that need warnings array
        $needsWarnings = in_array($fieldName, ['packages', 'relationships', 'components', 'dependencies']);
        
        return $needsWarnings 
            ? $this->{$transform}($value, $warnings)
            : $this->{$transform}($value);
    }
    
    /**
     * Check for unknown fields in SPDX data
     * 
     * @param array $spdxData SPDX data
     * @return array Warnings for unknown fields
     */
    protected function checkUnknownSpdxFields(array $spdxData): array
    {
        $knownFields = array_keys(self::SPDX_TO_CYCLONEDX_MAPPINGS);
        return $this->collectUnknownFieldWarnings($spdxData, $knownFields, 'SPDX');
    }
    
    /**
     * Check for unknown fields in CycloneDX data
     * 
     * @param array $cyclonedxData CycloneDX data
     * @return array Warnings for unknown fields
     */
    protected function checkUnknownCycloneDxFields(array $cyclonedxData): array
    {
        $knownFields = array_keys(self::CYCLONEDX_TO_SPDX_MAPPINGS);
        return $this->collectUnknownFieldWarnings($cyclonedxData, $knownFields, 'CycloneDX');
    }
    
    /**
     * Collect warnings for unknown fields
     * 
     * @param array $data Data to check
     * @param array $knownFields Known field names
     * @param string $formatName Format name for warning message
     * @return array Warnings
     */
    protected function collectUnknownFieldWarnings(array $data, array $knownFields, string $formatName): array
    {
        $unknownFields = array_diff(array_keys($data), $knownFields);
        return array_map(function($field) use ($formatName) {
            return "Unknown or unmapped {$formatName} field: {$field}";
        }, $unknownFields);
    }
    
    /**
     * Create default metadata for CycloneDX
     * 
     * @return array Default metadata
     */
    protected function createDefaultMetadata(): array
    {
        return [
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
    
    /**
     * Create default creation info for SPDX
     * 
     * @return array Default creation info
     */
    protected function createDefaultCreationInfo(): array
    {
        return [
            'created' => date('c'),
            'creators' => [
                'Tool: SBOMinator-Converter-1.0'
            ]
        ];
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
        return array_map(function($package) use (&$warnings) {
            return $this->transformPackageToComponent($package, $warnings);
        }, $packages);
    }
    
    /**
     * Transform a single SPDX package to a CycloneDX component
     * 
     * @param array $package SPDX package
     * @param array &$warnings Array to collect warnings
     * @return array CycloneDX component
     */
    protected function transformPackageToComponent(array $package, array &$warnings): array
    {
        $component = [
            'type' => 'library', // Default type
            'bom-ref' => isset($package['SPDXID']) ? $this->transformSpdxId($package['SPDXID']) : uniqid('component-')
        ];
        
        $component = $this->mapPackageFieldsToComponent($package, $component, $warnings);
        $this->addUnknownPackageFieldWarnings($package, $warnings);
        
        if (!isset($component['name'])) {
            $warnings[] = "Package missing required field: name";
            $component['name'] = 'unknown-' . uniqid();
        }
        
        return $component;
    }
    
    /**
     * Map package fields to component
     * 
     * @param array $package Source package
     * @param array $component Target component
     * @param array &$warnings Warnings array
     * @return array Updated component
     */
    protected function mapPackageFieldsToComponent(array $package, array $component, array &$warnings): array
    {
        foreach (self::PACKAGE_TO_COMPONENT_MAPPINGS as $packageField => $componentField) {
            if (!isset($package[$packageField])) {
                continue;
            }
            
            $component = $this->handlePackageFieldTransformation(
                $component, 
                $packageField, 
                $componentField, 
                $package[$packageField], 
                $warnings
            );
        }
        
        return $component;
    }
    
    /**
     * Handle package field transformation 
     * 
     * @param array $component Component to update
     * @param string $packageField Package field name
     * @param string $componentField Component field name
     * @param mixed $value Field value
     * @param array &$warnings Warnings array
     * @return array Updated component
     */
    protected function handlePackageFieldTransformation(
        array $component, 
        string $packageField, 
        string $componentField, 
        $value, 
        array &$warnings
    ): array {
        switch ($packageField) {
            case 'checksums':
                $component['hashes'] = $this->transformSpdxChecksums($value, $warnings);
                break;
            
            case 'packageVerificationCode':
                $component = $this->addPackageVerificationCodeAsHash($component, $value);
                break;
            
            case 'licenseConcluded':
            case 'licenseDeclared':
                $component = $this->addLicenseIfMissing($component, $value);
                break;
                
            default:
                // Direct field mapping
                $component[$componentField] = $value;
        }
        
        return $component;
    }
    
    /**
     * Add warnings for unknown package fields
     * 
     * @param array $package Package data
     * @param array &$warnings Warnings array
     */
    protected function addUnknownPackageFieldWarnings(array $package, array &$warnings): void
    {
        $knownFields = array_merge(array_keys(self::PACKAGE_TO_COMPONENT_MAPPINGS), ['SPDXID']);
        $unknownFields = array_diff(array_keys($package), $knownFields);
        
        $warnings = array_merge(
            $warnings,
            array_map(function($field) {
                return "Unknown or unmapped package field: {$field}";
            }, $unknownFields)
        );
    }
    
    /**
     * Add package verification code as a hash
     * 
     * @param array $component Component to modify
     * @param array $verificationCode Verification code data
     * @return array Updated component
     */
    protected function addPackageVerificationCodeAsHash(array $component, array $verificationCode): array
    {
        if (!isset($verificationCode['value'])) {
            return $component;
        }
        
        if (!isset($component['hashes'])) {
            $component['hashes'] = [];
        }
        
        $component['hashes'][] = [
            'alg' => 'SHA1',
            'content' => $verificationCode['value']
        ];
        
        return $component;
    }
    
    /**
     * Add license to component if not already set
     * 
     * @param array $component Component to modify
     * @param string $license License identifier
     * @return array Updated component
     */
    protected function addLicenseIfMissing(array $component, string $license): array
    {
        if (isset($component['licenses']) || empty($license)) {
            return $component;
        }
        
        $component['licenses'] = [
            [
                'license' => [
                    'id' => $license
                ]
            ]
        ];
        
        return $component;
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
        return array_map(function($component) use (&$warnings) {
            return $this->transformComponentToPackage($component, $warnings);
        }, $components);
    }
    
    /**
     * Transform a single CycloneDX component to an SPDX package
     * 
     * @param array $component CycloneDX component
     * @param array &$warnings Array to collect warnings
     * @return array SPDX package
     */
    protected function transformComponentToPackage(array $component, array &$warnings): array
    {
        $package = [
            'SPDXID' => isset($component['bom-ref']) 
                ? 'SPDXRef-' . $component['bom-ref'] 
                : 'SPDXRef-' . uniqid('pkg-')
        ];
        
        $package = $this->mapComponentFieldsToPackage($component, $package, $warnings);
        $this->addUnknownComponentFieldWarnings($component, $warnings);
        
        if (!isset($package['name'])) {
            $warnings[] = "Component missing required field: name";
            $package['name'] = 'unknown-' . uniqid();
        }
        
        return $package;
    }
    
    /**
     * Map component fields to package
     * 
     * @param array $component Source component
     * @param array $package Target package
     * @param array &$warnings Warnings array
     * @return array Updated package
     */
    protected function mapComponentFieldsToPackage(array $component, array $package, array &$warnings): array
    {
        foreach (self::COMPONENT_TO_PACKAGE_MAPPINGS as $componentField => $packageField) {
            if (!isset($component[$componentField])) {
                continue;
            }
            
            $package = $this->handleComponentFieldTransformation(
                $package,
                $componentField,
                $packageField,
                $component[$componentField],
                $warnings
            );
        }
        
        return $package;
    }
    
    /**
     * Handle component field transformation
     * 
     * @param array $package Package to update
     * @param string $componentField Component field name
     * @param string $packageField Package field name
     * @param mixed $value Field value
     * @param array &$warnings Warnings array
     * @return array Updated package
     */
    protected function handleComponentFieldTransformation(
        array $package,
        string $componentField,
        string $packageField,
        $value,
        array &$warnings
    ): array {
        switch ($componentField) {
            case 'hashes':
                $package['checksums'] = $this->transformCycloneDxHashes($value, $warnings);
                break;
                
            case 'licenses':
                $package = $this->extractLicenseId($package, $value, $warnings);
                break;
                
            default:
                // Direct field mapping
                $package[$packageField] = $value;
        }
        
        return $package;
    }
    
    /**
     * Add warnings for unknown component fields
     * 
     * @param array $component Component data
     * @param array &$warnings Warnings array
     */
    protected function addUnknownComponentFieldWarnings(array $component, array &$warnings): void
    {
        $knownFields = array_merge(array_keys(self::COMPONENT_TO_PACKAGE_MAPPINGS), ['bom-ref', 'type']);
        $unknownFields = array_diff(array_keys($component), $knownFields);
        
        $warnings = array_merge(
            $warnings,
            array_map(function($field) {
                return "Unknown or unmapped component field: {$field}";
            }, $unknownFields)
        );
    }
    
    /**
     * Extract license ID from component licenses
     * 
     * @param array $package Package to modify
     * @param array $licenses Licenses array
     * @param array &$warnings Warnings array
     * @return array Updated package
     */
    protected function extractLicenseId(array $package, array $licenses, array &$warnings): array
    {
        if (empty($licenses)) {
            return $package;
        }
        
        $license = $licenses[0];
        
        if (isset($license['license']['id'])) {
            $package['licenseConcluded'] = $license['license']['id'];
        } elseif (isset($license['license']['name'])) {
            $package['licenseConcluded'] = $license['license']['name'];
        } else {
            $warnings[] = "Component license format not recognized";
        }
        
        return $package;
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
        return array_filter(array_map(function($checksum) use (&$warnings) {
            return $this->convertSpdxChecksumToHash($checksum, $warnings);
        }, $checksums));
    }
    
    /**
     * Convert a single SPDX checksum to a CycloneDX hash
     * 
     * @param array $checksum SPDX checksum
     * @param array &$warnings Warnings array
     * @return array|null CycloneDX hash or null if invalid
     */
    protected function convertSpdxChecksumToHash(array $checksum, array &$warnings): ?array
    {
        if (!isset($checksum['algorithm']) || !isset($checksum['checksumValue'])) {
            $warnings[] = "Malformed checksum entry in SPDX package";
            return null;
        }
        
        $algorithm = $this->mapSpdxHashAlgorithm($checksum['algorithm']);
        
        if ($algorithm === null) {
            $warnings[] = "Unsupported hash algorithm: {$checksum['algorithm']}";
            return null;
        }
        
        return [
            'alg' => $algorithm,
            'content' => $checksum['checksumValue']
        ];
    }
    
    /**
     * Map SPDX hash algorithm to CycloneDX algorithm
     * 
     * @param string $algorithm SPDX algorithm
     * @return string|null CycloneDX algorithm or null if unsupported
     */
    protected function mapSpdxHashAlgorithm(string $algorithm): ?string
    {
        return match(strtoupper($algorithm)) {
            'SHA1' => 'SHA-1',
            'SHA256' => 'SHA-256',
            'SHA512' => 'SHA-512',
            'MD5' => 'MD5',
            default => null
        };
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
        return array_filter(array_map(function($hash) use (&$warnings) {
            return $this->convertHashToSpdxChecksum($hash, $warnings);
        }, $hashes));
    }
    
    /**
     * Convert a single CycloneDX hash to SPDX checksum
     * 
     * @param array $hash CycloneDX hash
     * @param array &$warnings Warnings array
     * @return array|null SPDX checksum or null if invalid
     */
    protected function convertHashToSpdxChecksum(array $hash, array &$warnings): ?array
    {
        if (!isset($hash['alg']) || !isset($hash['content'])) {
            $warnings[] = "Malformed hash entry in CycloneDX component";
            return null;
        }
        
        $algorithm = $this->mapCycloneDxHashAlgorithm($hash['alg']);
        
        if ($algorithm === null) {
            $warnings[] = "Unsupported hash algorithm: {$hash['alg']}";
            return null;
        }
        
        return [
            'algorithm' => $algorithm,
            'checksumValue' => $hash['content']
        ];
    }
    
    /**
     * Map CycloneDX hash algorithm to SPDX algorithm
     * 
     * @param string $algorithm CycloneDX algorithm
     * @return string|null SPDX algorithm or null if unsupported
     */
    protected function mapCycloneDxHashAlgorithm(string $algorithm): ?string
    {
        return match(strtoupper($algorithm)) {
            'SHA-1', 'SHA1' => 'SHA1',
            'SHA-256', 'SHA256' => 'SHA256',
            'SHA-512', 'SHA512' => 'SHA512',
            'MD5' => 'MD5',
            default => null
        };
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
        $dependencyMap = $this->buildDependencyMap($relationships, $warnings);
        return $this->formatDependencyMap($dependencyMap);
    }
    
    /**
     * Format dependency map into CycloneDX dependencies array
     * 
     * @param array $dependencyMap Dependency map
     * @return array Dependencies array
     */
    protected function formatDependencyMap(array $dependencyMap): array
    {
        return array_map(function($ref, $deps) {
            return [
                'ref' => $ref,
                'dependsOn' => $deps
            ];
        }, array_keys($dependencyMap), array_values($dependencyMap));
    }
    
    /**
     * Build dependency map from relationships
     * 
     * @param array $relationships Relationships array
     * @param array &$warnings Warnings array
     * @return array Dependency map
     */
    protected function buildDependencyMap(array $relationships, array &$warnings): array
    {
        $dependencyMap = [];
        
        foreach ($relationships as $relationship) {
            $this->processRelationshipForDependencyMap($relationship, $dependencyMap, $warnings);
        }
        
        return $dependencyMap;
    }
    
    /**
     * Process a single relationship for dependency map
     * 
     * @param array $relationship Relationship data
     * @param array &$dependencyMap Dependency map to update
     * @param array &$warnings Warnings array
     */
    protected function processRelationshipForDependencyMap(
        array $relationship, 
        array &$dependencyMap, 
        array &$warnings
    ): void {
        if (!$this->isValidRelationship($relationship)) {
            $warnings[] = "Malformed relationship entry in SPDX: missing required fields";
            return;
        }
        
        if ($relationship['relationshipType'] === self::RELATIONSHIP_DEPENDS_ON) {
            $this->addDependencyToMap($relationship, $dependencyMap);
        } elseif (str_contains(strtoupper($relationship['relationshipType']), 'DEPEND')) {
            $warnings[] = "Unsupported dependency relationship type: {$relationship['relationshipType']}";
        }
    }
    
    /**
     * Add dependency to dependency map
     * 
     * @param array $relationship Relationship data
     * @param array &$dependencyMap Dependency map to update
     */
    protected function addDependencyToMap(array $relationship, array &$dependencyMap): void
    {
        $dependent = $this->transformSpdxId($relationship['spdxElementId']);
        $dependency = $this->transformSpdxId($relationship['relatedSpdxElement']);
        
        if (!isset($dependencyMap[$dependent])) {
            $dependencyMap[$dependent] = [];
        }
        
        $dependencyMap[$dependent][] = $dependency;
    }
    
    /**
     * Check if relationship has all required fields
     * 
     * @param array $relationship Relationship to check
     * @return bool True if valid
     */
    protected function isValidRelationship(array $relationship): bool
    {
        return isset($relationship['spdxElementId']) && 
               isset($relationship['relatedSpdxElement']) && 
               isset($relationship['relationshipType']);
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
        return array_merge([], ...array_map(function($dependency) use (&$warnings) {
            return $this->convertDependencyToRelationships($dependency, $warnings);
        }, $dependencies));
    }
    
    /**
     * Convert a single dependency to relationships
     * 
     * @param array $dependency Dependency data
     * @param array &$warnings Warnings array
     * @return array Relationships array
     */
    protected function convertDependencyToRelationships(array $dependency, array &$warnings): array
    {
        if (!$this->isValidDependency($dependency)) {
            $warnings[] = "Malformed dependency entry in CycloneDX: missing required fields";
            return [];
        }
        
        $dependent = $this->transformSerialNumber($dependency['ref']);
        
        return array_map(function($dependencyRef) use ($dependent) {
            return [
                'spdxElementId' => $dependent,
                'relatedSpdxElement' => $this->transformSerialNumber($dependencyRef),
                'relationshipType' => self::RELATIONSHIP_DEPENDS_ON
            ];
        }, $dependency['dependsOn']);
    }
    
    /**
     * Check if dependency has all required fields
     * 
     * @param array $dependency Dependency to check
     * @return bool True if valid
     */
    protected function isValidDependency(array $dependency): bool
    {
        return isset($dependency['ref']) && 
               isset($dependency['dependsOn']) && 
               is_array($dependency['dependsOn']);
    }
    
    /**
     * Transform SPDX version to CycloneDX spec version
     * 
     * @param string $spdxVersion The SPDX version
     * @return string The CycloneDX spec version
     */
    protected function transformSpdxVersion(string $spdxVersion): string
    {
        return match ($spdxVersion) {
            'SPDX-2.3' => '1.4',
            'SPDX-2.2' => '1.3',
            'SPDX-2.1' => '1.2',
            default => self::CYCLONEDX_VERSION, // Default to latest
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
        if (!isset($creationInfo['creators']) || !is_array($creationInfo['creators'])) {
            return $this->addDefaultTool($metadata);
        }
        
        $metadata['tools'] = $this->extractToolsFromCreators($creationInfo['creators']);
        
        // Add default tool if none found
        if (empty($metadata['tools'])) {
            $metadata = $this->addDefaultTool($metadata);
        }
        
        return $metadata;
    }
    
    /**
     * Extract tool information from creators array
     * 
     * @param array $creators Creators array
     * @return array Tools array
     */
    protected function extractToolsFromCreators(array $creators): array
    {
        return array_filter(array_map(function($creator) {
            return $this->extractToolFromCreator($creator);
        }, $creators));
    }
    
    /**
     * Extract tool from creator string
     * 
     * @param string $creator Creator string
     * @return array|null Tool data or null
     */
    protected function extractToolFromCreator(string $creator): ?array
    {
        if (strpos($creator, 'Tool:') !== 0) {
            return null;
        }
        
        $toolInfo = trim(substr($creator, 5));
        $parts = explode('-', $toolInfo);
        
        if (count($parts) < 2) {
            return null;
        }
        
        return [
            'vendor' => $parts[0],
            'name' => $parts[0],
            'version' => $parts[1] ?? '1.0'
        ];
    }
    
    /**
     * Add default tool to metadata
     * 
     * @param array $metadata Metadata to update
     * @return array Updated metadata
     */
    protected function addDefaultTool(array $metadata): array
    {
        $metadata['tools'][] = [
            'vendor' => 'SBOMinator',
            'name' => 'Converter',
            'version' => '1.0.0'
        ];
        
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
        return match ($specVersion) {
            '1.4' => 'SPDX-2.3',
            '1.3' => 'SPDX-2.2',
            '1.2' => 'SPDX-2.1',
            default => self::SPDX_VERSION, // Default to latest
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
            $creationInfo['creators'] = $this->convertToolsToCreators($metadata['tools']);
        }
        
        // Add default creator if none found
        if (empty($creationInfo['creators'])) {
            $creationInfo['creators'][] = 'Tool: SBOMinator-Converter-1.0';
        }
        
        return $creationInfo;
    }
    
    /**
     * Convert tools array to creators array
     * 
     * @param array $tools Tools array
     * @return array Creators array
     */
    protected function convertToolsToCreators(array $tools): array
    {
        return array_filter(array_map(function($tool) {
            return $this->convertToolToCreator($tool);
        }, $tools));
    }
    
    /**
     * Convert a tool to creator string
     * 
     * @param array $tool Tool data
     * @return string|null Creator string or null
     */
    protected function convertToolToCreator(array $tool): ?string
    {
        if (!isset($tool['name'])) {
            return null;
        }
        
        $vendor = $tool['vendor'] ?? '';
        $name = $tool['name'];
        $version = $tool['version'] ?? '1.0';
        
        return "Tool: {$vendor}{$name}-{$version}";
    }
}