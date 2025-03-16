<?php

namespace SBOMinator\Transformatron\Tests\Unit\Converter;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Converter\CycloneDxToSpdxConverter;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Exception\ValidationException;

/**
 * Test cases for CycloneDxToSpdxConverter class.
 */
class CycloneDxToSpdxConverterTest extends TestCase
{
    /**
     * @var CycloneDxToSpdxConverter
     */
    private CycloneDxToSpdxConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new CycloneDxToSpdxConverter();
    }

    /**
     * Test that the converter returns the expected format identifiers.
     */
    public function testConverterFormatIdentifiers(): void
    {
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->converter->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->converter->getTargetFormat());
    }

    /**
     * Test conversion with a valid CycloneDX document.
     */
    public function testConversionWithValidCycloneDxDocument(): void
    {
        // Create a minimal valid CycloneDX document
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            'version' => 1,
            'serialNumber' => 'urn:uuid:12345678-1234-1234-1234-123456789012',
            'metadata' => [
                'timestamp' => '2023-01-01T00:00:00Z',
                'tools' => [
                    [
                        'vendor' => 'Test',
                        'name' => 'Generator',
                        'version' => '1.0.0'
                    ]
                ]
            ]
        ]);

        // Perform conversion
        $result = $this->converter->convert($cyclonedxJson);

        // Check that result is a ConversionResult
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $result->getFormat());
        $this->assertTrue($result->isSuccessful());

        // Get content as array for easier inspection
        $content = $result->getContentAsArray();

        // Check basic SPDX structure
        $this->assertEquals(VersionEnum::SPDX_VERSION, $content['spdxVersion']);
        $this->assertEquals('CC0-1.0', $content['dataLicense']);
        $this->assertEquals('SPDXRef-DOCUMENT', $content['SPDXID']);
        $this->assertArrayHasKey('documentNamespace', $content);
        $this->assertEquals('SBOM for urn:uuid:12345678-1234-1234-1234-123456789012', $content['name']);

        // Check that creation info was properly transformed
        $this->assertArrayHasKey('creationInfo', $content);
        $this->assertEquals('2023-01-01T00:00:00Z', $content['creationInfo']['created']);
        $this->assertNotEmpty($content['creationInfo']['creators']);
    }

    /**
     * Test conversion with a CycloneDX document that has components and dependencies.
     */
    public function testConversionWithComponentsAndDependencies(): void
    {
        // Create a CycloneDX document with components and dependencies
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            'version' => 1,
            'serialNumber' => 'urn:uuid:12345678-1234-1234-1234-123456789012',
            'name' => 'test-document',
            'components' => [
                [
                    'type' => 'library',
                    'bom-ref' => 'component-1',
                    'name' => 'component1',
                    'version' => '1.0.0',
                    'licenses' => [
                        [
                            'license' => [
                                'id' => 'MIT'
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'library',
                    'bom-ref' => 'component-2',
                    'name' => 'component2',
                    'version' => '2.0.0',
                    'licenses' => [
                        [
                            'license' => [
                                'id' => 'Apache-2.0'
                            ]
                        ]
                    ]
                ]
            ],
            'dependencies' => [
                [
                    'ref' => 'component-1',
                    'dependsOn' => ['component-2']
                ]
            ]
        ]);

        // Perform conversion
        $result = $this->converter->convert($cyclonedxJson);
        $content = $result->getContentAsArray();

        // Check that packages were properly transformed
        $this->assertArrayHasKey('packages', $content);
        $this->assertCount(2, $content['packages']);

        // Check that component1 was properly transformed
        $package1 = $this->findPackageBySpdxId($content['packages'], 'SPDXRef-component-1');
        $this->assertNotNull($package1);
        $this->assertEquals('component1', $package1['name']);
        $this->assertEquals('1.0.0', $package1['versionInfo']);
        $this->assertEquals('MIT', $package1['licenseConcluded']);

        // Check that relationships were properly transformed
        $this->assertArrayHasKey('relationships', $content);

        // First let's print the actual relationships for debugging
        $foundRelationships = [];
        foreach ($content['relationships'] as $rel) {
            $foundRelationships[] = sprintf(
                "%s -> %s (%s)",
                $rel['spdxElementId'],
                $rel['relatedSpdxElement'],
                $rel['relationshipType']
            );
        }

        // Find the dependency relationship
        $dependencyRel = $this->findRelationship(
            $content['relationships'],
            'SPDXRef-component-1',
            'SPDXRef-component-2',
            'DEPENDS_ON'
        );
        $this->assertNotNull($dependencyRel, "Could not find dependency relationship. Found: " . implode(", ", $foundRelationships));

        // At least one DESCRIBES relationship should exist
        $hasDescribesRelationship = false;
        foreach ($content['relationships'] as $rel) {
            if ($rel['spdxElementId'] === 'SPDXRef-DOCUMENT' &&
                $rel['relationshipType'] === 'DESCRIBES') {
                $hasDescribesRelationship = true;
                break;
            }
        }
        $this->assertTrue($hasDescribesRelationship, "No DESCRIBES relationships found");
    }

    /**
     * Test validation failure with an invalid CycloneDX document.
     */
    public function testValidationFailureWithInvalidCycloneDxDocument(): void
    {
        // Create an invalid CycloneDX document (missing required fields)
        $invalidCyclonedxJson = json_encode([
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            // Missing bomFormat and version
        ]);

        // With our new approach, no exception is thrown, but we get a failed result with errors
        $result = $this->converter->convert($invalidCyclonedxJson);

        // Check that conversion failed
        $this->assertFalse($result->isSuccessful());

        // Check that we have validation errors
        $this->assertNotEmpty($result->getErrors());

        // Check for specific error about missing bomFormat
        $criticalErrors = $result->getErrorsBySeverity(ConversionError::SEVERITY_CRITICAL);
        $this->assertNotEmpty($criticalErrors);

        // At least one error should mention bomFormat
        $bomFormatError = false;
        foreach ($criticalErrors as $error) {
            if (strpos($error->getMessage(), 'bomFormat') !== false) {
                $bomFormatError = true;
                break;
            }
        }

        $this->assertTrue($bomFormatError, "No error about missing bomFormat found");
    }

    /**
     * Test with invalid bomFormat value.
     */
    public function testValidationFailureWithInvalidBomFormat(): void
    {
        // Create an invalid CycloneDX document (incorrect bomFormat)
        $invalidCyclonedxJson = json_encode([
            'bomFormat' => 'NotCycloneDX',
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            'version' => 1
        ]);

        // With our new approach, no exception is thrown, but we get a failed result with errors
        $result = $this->converter->convert($invalidCyclonedxJson);

        // Check that conversion failed
        $this->assertFalse($result->isSuccessful());

        // Check that we have validation errors
        $this->assertNotEmpty($result->getErrors());

        // Check for specific error about invalid bomFormat
        $criticalErrors = $result->getErrorsBySeverity(ConversionError::SEVERITY_CRITICAL);
        $this->assertNotEmpty($criticalErrors);

        // At least one error should mention bomFormat
        $bomFormatError = false;
        foreach ($criticalErrors as $error) {
            if (strpos($error->getMessage(), 'bomFormat') !== false) {
                $bomFormatError = true;
                break;
            }
        }

        $this->assertTrue($bomFormatError, "No error about invalid bomFormat found");
    }

    /**
     * Test with unknown fields to check warning generation.
     */
    public function testWarningGenerationForUnknownFields(): void
    {
        // Create a valid CycloneDX document with unknown fields
        $cyclonedxJson = json_encode([
            'bomFormat' => 'CycloneDX',
            'specVersion' => VersionEnum::CYCLONEDX_VERSION,
            'version' => 1,
            'unknownField1' => 'value1',
            'unknownField2' => 'value2'
        ]);

        // Perform conversion
        $result = $this->converter->convert($cyclonedxJson);

        // Check that warnings were generated for unknown fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);

        // Find warnings specifically about unknown fields
        $unknownFieldWarnings = array_filter($warnings, function($warning) {
            return strpos($warning, 'Unknown or unmapped CycloneDX field') !== false;
        });

        $this->assertCount(2, $unknownFieldWarnings, "Expected exactly 2 unknown field warnings");

        // Check for specific unknown fields in the warnings
        $field1Found = false;
        $field2Found = false;
        foreach ($unknownFieldWarnings as $warning) {
            if (strpos($warning, 'unknownField1') !== false) {
                $field1Found = true;
            }
            if (strpos($warning, 'unknownField2') !== false) {
                $field2Found = true;
            }
        }

        $this->assertTrue($field1Found, "No warning for unknownField1");
        $this->assertTrue($field2Found, "No warning for unknownField2");
    }

    /**
     * Helper method to find a package by SPDXID in an array of packages.
     *
     * @param array<array<string, mixed>> $packages Array of packages
     * @param string $spdxId The SPDXID to find
     * @return array<string, mixed>|null The found package or null
     */
    private function findPackageBySpdxId(array $packages, string $spdxId): ?array
    {
        foreach ($packages as $package) {
            if (isset($package['SPDXID']) && $package['SPDXID'] === $spdxId) {
                return $package;
            }
        }
        return null;
    }

    /**
     * Helper method to find a specific relationship in a relationships array.
     *
     * @param array<array<string, string>> $relationships The relationships array
     * @param string $spdxElementId The source element ID
     * @param string $relatedSpdxElement The target element ID
     * @param string $relationshipType The relationship type
     * @return array<string, string>|null The found relationship or null
     */
    private function findRelationship(
        array $relationships,
        string $spdxElementId,
        string $relatedSpdxElement,
        string $relationshipType
    ): ?array {
        foreach ($relationships as $relationship) {
            if ($relationship['spdxElementId'] === $spdxElementId &&
                $relationship['relatedSpdxElement'] === $relatedSpdxElement &&
                $relationship['relationshipType'] === $relationshipType) {
                return $relationship;
            }
        }
        return null;
    }
}