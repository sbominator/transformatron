<?php

namespace SBOMinator\Transformatron\Converter;

use SBOMinator\Transformatron\Config\CycloneDxFieldConfig;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\ComponentTransformer;
use SBOMinator\Transformatron\Transformer\CycloneDxMetadataTransformer;
use SBOMinator\Transformatron\Transformer\CycloneDxReferenceTransformer;
use SBOMinator\Transformatron\Transformer\DependencyTransformer;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Util\ValidationUtil;
use SBOMinator\Transformatron\Validation\CycloneDxValidator;

/**
 * Converter for transforming CycloneDX format to SPDX format.
 *
 * Uses specialized transformers to convert each section of the SBOM.
 */
class CycloneDxToSpdxConverter extends AbstractConverter
{
    /**
     * @var CycloneDxValidator
     */
    private CycloneDxValidator $validator;

    /**
     * @var CycloneDxMetadataTransformer
     */
    private CycloneDxMetadataTransformer $metadataTransformer;

    /**
     * @var ComponentTransformer
     */
    private ComponentTransformer $componentTransformer;

    /**
     * @var DependencyTransformer
     */
    private DependencyTransformer $dependencyTransformer;

    /**
     * @var CycloneDxReferenceTransformer
     */
    private CycloneDxReferenceTransformer $referenceTransformer;

    /**
     * @var SpdxIdTransformer
     */
    private SpdxIdTransformer $spdxIdTransformer;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->validator = new CycloneDxValidator();
        $this->spdxIdTransformer = new SpdxIdTransformer();
        $hashTransformer = new HashTransformer();
        $licenseTransformer = new LicenseTransformer();

        $this->metadataTransformer = new CycloneDxMetadataTransformer();
        $this->referenceTransformer = new CycloneDxReferenceTransformer();
        $this->componentTransformer = new ComponentTransformer(
            $hashTransformer,
            $licenseTransformer,
            $this->spdxIdTransformer
        );
        $this->dependencyTransformer = new DependencyTransformer($this->spdxIdTransformer);
    }

    /**
     * Get the source format for this converter.
     *
     * @return string The source format (e.g., 'CycloneDX')
     */
    public function getSourceFormat(): string
    {
        return FormatEnum::FORMAT_CYCLONEDX;
    }

    /**
     * Get the target format for this converter.
     *
     * @return string The target format (e.g., 'SPDX')
     */
    public function getTargetFormat(): string
    {
        return FormatEnum::FORMAT_SPDX;
    }

    /**
     * Validate the source data.
     *
     * @param array<string, mixed> $sourceData The source data to validate
     * @return array<ConversionError> Array of validation errors
     */
    protected function validateSourceData(array $sourceData): array
    {
        $errors = [];
        $validationErrors = $this->validator->validate($sourceData);

        foreach ($validationErrors as $errorMessage) {
            $errors[] = ConversionError::createError(
                $errorMessage,
                'CycloneDxValidator',
                ['field' => $this->extractFieldFromError($errorMessage)],
                'validation_error'
            );
        }

        // Check for critical issue - missing bomFormat
        if (!isset($sourceData['bomFormat']) || $sourceData['bomFormat'] !== 'CycloneDX') {
            $errors[] = ConversionError::createCritical(
                'Invalid or missing bomFormat - must be "CycloneDX"',
                'CycloneDxValidator',
                ['actual_value' => $sourceData['bomFormat'] ?? 'missing'],
                'invalid_bom_format'
            );
        }

        return $errors;
    }

    /**
     * Extract field name from a validation error message.
     *
     * @param string $errorMessage The error message to parse
     * @return string|null The extracted field name or null
     */
    private function extractFieldFromError(string $errorMessage): ?string
    {
        if (preg_match('/field:\s*([a-zA-Z0-9]+)/', $errorMessage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get initial target data structure.
     *
     * @return array<string, mixed> Initial target data structure
     */
    protected function getInitialTargetData(): array
    {
        return [
            'spdxVersion' => VersionEnum::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT', // Always use SPDXRef-DOCUMENT as the document ID
            'documentNamespace' => $this->generateDocumentNamespace(),
            'packages' => [],
            'relationships' => []
        ];
    }

    /**
     * Map source data to target data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @param array<string, mixed> $targetData Initial target data structure
     * @param array<string> &$warnings Array to collect warnings during conversion
     * @param array<ConversionError> &$errors Array to collect errors during conversion
     * @return array<string, mixed> Updated target data
     */
    protected function mapSourceToTarget(array $sourceData, array $targetData, array &$warnings, array &$errors): array
    {
        $mappings = CycloneDxFieldConfig::getCycloneDxToSpdxMappings();

        foreach ($mappings as $cyclonedxField => $mapping) {
            if (!isset($sourceData[$cyclonedxField])) {
                ValidationUtil::warnIfRequiredField($cyclonedxField, CycloneDxFieldConfig::getRequiredCycloneDxFields(), $warnings);
                continue;
            }

            if ($mapping['field'] === null) {
                continue;
            }

            // Use transform method with the appropriate transformer based on field
            switch ($cyclonedxField) {
                case 'metadata':
                    $transformResult = $this->metadataTransformer->transform(['metadata' => $sourceData[$cyclonedxField]], $warnings, $errors);
                    if (isset($transformResult['creationInfo'])) {
                        $targetData['creationInfo'] = $transformResult['creationInfo'];
                    }
                    break;

                case 'components':
                    $transformResult = $this->componentTransformer->transform(['components' => $sourceData[$cyclonedxField]], $warnings, $errors);
                    if (isset($transformResult['packages'])) {
                        $targetData['packages'] = $transformResult['packages'];
                    }
                    break;

                case 'dependencies':
                    $transformResult = $this->dependencyTransformer->transform(['dependencies' => $sourceData[$cyclonedxField]], $warnings, $errors);
                    if (isset($transformResult['relationships'])) {
                        $targetData['relationships'] = $transformResult['relationships'];
                    }
                    break;

                case 'specVersion':
                    $transformResult = $this->referenceTransformer->transform(['specVersion' => $sourceData[$cyclonedxField]], $warnings, $errors);
                    if (isset($transformResult['spdxVersion'])) {
                        $targetData['spdxVersion'] = $transformResult['spdxVersion'];
                    }
                    break;

                case 'serialNumber':
                    // Note: We ignore the serial number and always use SPDXRef-DOCUMENT
                    // for the document ID to maintain consistency with the test expectations.
                    $targetData['SPDXID'] = 'SPDXRef-DOCUMENT';
                    break;

                default:
                    // For direct mappings with no transformation needed
                    if ($mapping['transform'] === null) {
                        $targetData[$mapping['field']] = $sourceData[$cyclonedxField];
                    } else {
                        // Use the old method for any remaining transformations
                        $targetData[$mapping['field']] = $this->transformFieldValue(
                            $sourceData[$cyclonedxField],
                            $mapping['transform'],
                            $cyclonedxField,
                            $warnings,
                            $errors
                        );
                    }
                    break;
            }
        }

        // If name is not set, use the serial number or a default
        if (!isset($targetData['name']) && isset($sourceData['serialNumber'])) {
            $targetData['name'] = "SBOM for {$sourceData['serialNumber']}";
        } elseif (!isset($targetData['name'])) {
            $targetData['name'] = "Converted SBOM Document";
            $warnings[] = "Missing name field, using default name";
        }

        // Add additional relationships: DOCUMENT DESCRIBES main components
        $this->addDocumentRelationships($targetData, $sourceData, $warnings, $errors);

        return $targetData;
    }

    /**
     * Check for unknown fields in the source data.
     *
     * @param array<string, mixed> $sourceData Source data
     * @return array<string> Warnings for unknown fields
     */
    protected function checkUnknownSourceFields(array $sourceData): array
    {
        $knownFields = array_keys(CycloneDxFieldConfig::getCycloneDxToSpdxMappings());
        return ValidationUtil::collectUnknownFieldWarnings($sourceData, $knownFields, 'CycloneDX');
    }

    /**
     * Ensure required default data is added to the target data if missing.
     *
     * @param array<string, mixed> $targetData Target data to update
     * @return array<string, mixed> Updated target data with defaults
     */
    protected function ensureRequiredDefaultData(array $targetData): array
    {
        if (!isset($targetData['creationInfo'])) {
            $targetData['creationInfo'] = $this->metadataTransformer->createDefaultCreationInfo();
        }

        return $targetData;
    }

    /**
     * Add document relationships for top-level components.
     *
     * @param array<string, mixed> &$targetData Target SPDX data
     * @param array<string, mixed> $sourceData Source CycloneDX data
     * @param array<string> &$warnings Warnings array
     * @param array<ConversionError> &$errors Errors array
     */
    private function addDocumentRelationships(
        array &$targetData,
        array $sourceData,
        array &$warnings,
        array &$errors
    ): void {
        if (!isset($sourceData['components']) || empty($sourceData['components'])) {
            return;
        }

        try {
            // Get existing relationships
            $relationships = $targetData['relationships'] ?? [];

            // Get document SPDXID
            $documentId = $targetData['SPDXID'];

            // Add DESCRIBES relationships manually for each component
            foreach ($sourceData['components'] as $component) {
                if (!isset($component['bom-ref'])) {
                    $warnings[] = "Component missing bom-ref, cannot create DESCRIBES relationship";
                    continue;
                }

                $spdxId = $this->spdxIdTransformer->formatAsSpdxId($component['bom-ref']);

                // Add DESCRIBES relationship
                $relationships[] = [
                    'spdxElementId' => $documentId,
                    'relatedSpdxElement' => $spdxId,
                    'relationshipType' => 'DESCRIBES'
                ];
            }

            $targetData['relationships'] = $relationships;
        } catch (\Exception $e) {
            $errors[] = ConversionError::createWarning(
                "Error adding document relationships: " . $e->getMessage(),
                "DocumentRelationships",
                ['component_count' => count($sourceData['components'] ?? [])],
                'document_relationships_error'
            );
        }
    }

    /**
     * Generate a document namespace for the SPDX document.
     *
     * @return string The generated document namespace
     */
    private function generateDocumentNamespace(): string
    {
        return "https://sbominator.example/spdx/document-" . uniqid();
    }
}