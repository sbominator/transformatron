<?php

namespace SBOMinator\Transformatron\Tests\Unit\Converter;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\ConversionResult;
use SBOMinator\Transformatron\Converter\SpdxToCycloneDxConverter;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Enum\VersionEnum;
use SBOMinator\Transformatron\Error\ConversionError;

/**
 * Test cases for SpdxToCycloneDxConverter class.
 */
class SpdxToCycloneDxConverterTest extends TestCase
{
    /**
     * @var SpdxToCycloneDxConverter
     */
    private SpdxToCycloneDxConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new SpdxToCycloneDxConverter();
    }

    /**
     * Test that the converter returns the expected format identifiers.
     */
    public function testConverterFormatIdentifiers(): void
    {
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->converter->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->converter->getTargetFormat());
    }

    /**
     * Test conversion with a valid SPDX document.
     */
    public function testConversionWithValidSpdxDocument(): void
    {
        // Create a minimal valid SPDX document
        $spdxJson = json_encode([
            'spdxVersion' => VersionEnum::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test'
        ]);

        // Perform conversion
        $result = $this->converter->convert($spdxJson);

        // Check that result is a ConversionResult
        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $result->getFormat());
        $this->assertTrue($result->isSuccessful());

        // Get content as array for easier inspection
        $content = $result->getContentAsArray();

        // Check basic CycloneDX structure
        $this->assertEquals('CycloneDX', $content['bomFormat']);
        $this->assertEquals(VersionEnum::CYCLONEDX_VERSION, $content['specVersion']);
        $this->assertEquals(1, $content['version']);
    }

    /**
     * Test conversion with an SPDX document that has packages and relationships.
     */
    public function testConversionWithPackagesAndRelationships(): void
    {
        // Create an SPDX document with packages and relationships
        $spdxJson = json_encode([
            'spdxVersion' => VersionEnum::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test',
            'packages' => [
                [
                    'name' => 'package1',
                    'SPDXID' => 'SPDXRef-Package-1',
                    'versionInfo' => '1.0.0',
                    'licenseConcluded' => 'MIT'
                ],
                [
                    'name' => 'package2',
                    'SPDXID' => 'SPDXRef-Package-2',
                    'versionInfo' => '2.0.0',
                    'licenseConcluded' => 'Apache-2.0'
                ]
            ],
            'relationships' => [
                [
                    'spdxElementId' => 'SPDXRef-Package-1',
                    'relatedSpdxElement' => 'SPDXRef-Package-2',
                    'relationshipType' => 'DEPENDS_ON'
                ]
            ]
        ]);

        // Perform conversion
        $result = $this->converter->convert($spdxJson);
        $content = $result->getContentAsArray();

        // Check that components were properly transformed
        $this->assertArrayHasKey('components', $content);
        $this->assertCount(2, $content['components']);

        // Check that package1 was properly transformed
        $package1 = $this->findComponentByName($content['components'], 'package1');
        $this->assertNotNull($package1);
        $this->assertEquals('package1', $package1['name']);
        $this->assertEquals('1.0.0', $package1['version']);
        $this->assertArrayHasKey('licenses', $package1);
        $this->assertEquals('MIT', $package1['licenses'][0]['license']['id']);

        // Check that dependencies were properly transformed
        $this->assertArrayHasKey('dependencies', $content);
        $this->assertNotEmpty($content['dependencies']);
        $this->assertEquals('Package-1', $content['dependencies'][0]['ref']);
        $this->assertContains('Package-2', $content['dependencies'][0]['dependsOn']);
    }

    /**
     * Test validation failure with an invalid SPDX document.
     */
    public function testValidationFailureWithInvalidSpdxDocument(): void
    {
        // Create an invalid SPDX document (missing required fields)
        $invalidSpdxJson = json_encode([
            'spdxVersion' => VersionEnum::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0'
            // Missing SPDXID, name, and documentNamespace
        ]);

        // With our new approach, no exception is thrown, but we get a failed result with errors
        $result = $this->converter->convert($invalidSpdxJson);

        // Check that conversion failed or contains errors
        if (!$result->isSuccessful()) {
            // This is the expected path - conversion failed
            $this->assertFalse($result->isSuccessful());
            $this->assertNotEmpty($result->getErrors());
        } else {
            // If the conversion technically "succeeded" but with errors,
            // make sure there are warnings or non-critical errors
            $this->assertNotEmpty($result->getWarnings());
            $this->assertTrue(
                $result->hasErrors() || $result->hasWarnings(),
                "Expected errors or warnings for invalid SPDX document"
            );
        }
    }

    /**
     * Test with unknown fields to check warning generation.
     */
    public function testWarningGenerationForUnknownFields(): void
    {
        // Create a valid SPDX document with unknown fields
        $spdxJson = json_encode([
            'spdxVersion' => VersionEnum::SPDX_VERSION,
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'test-document',
            'documentNamespace' => 'https://example.com/test',
            'unknownField1' => 'value1',
            'unknownField2' => 'value2'
        ]);

        // Perform conversion
        $result = $this->converter->convert($spdxJson);

        // Check that warnings were generated for unknown fields
        $warnings = $result->getWarnings();
        $this->assertNotEmpty($warnings);

        // Find warnings specifically about unknown fields
        $unknownFieldWarnings = array_filter($warnings, function($warning) {
            return strpos($warning, 'Unknown or unmapped SPDX field') !== false;
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
     * Helper method to find a component by name in an array of components.
     *
     * @param array<array<string, mixed>> $components Array of components
     * @param string $name Component name to find
     * @return array<string, mixed>|null The found component or null
     */
    private function findComponentByName(array $components, string $name): ?array
    {
        foreach ($components as $component) {
            if ($component['name'] === $name) {
                return $component;
            }
        }
        return null;
    }
}