<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for LicenseTransformer class.
 */
class LicenseTransformerTest extends TestCase
{
    /**
     * @var LicenseTransformer
     */
    private LicenseTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new LicenseTransformer();
    }

    /**
     * Test that the transformer implements the TransformerInterface.
     */
    public function testImplementsTransformerInterface(): void
    {
        $this->assertInstanceOf(TransformerInterface::class, $this->transformer);
    }

    /**
     * Test the source and target formats of the transformer.
     */
    public function testGetSourceAndTargetFormats(): void
    {
        $this->assertEquals(FormatEnum::FORMAT_SPDX, $this->transformer->getSourceFormat());
        $this->assertEquals(FormatEnum::FORMAT_CYCLONEDX, $this->transformer->getTargetFormat());
    }

    /**
     * Test the transform method with SPDX to CycloneDX conversion.
     */
    public function testTransformSpdxToCycloneDx(): void
    {
        $sourceData = ['license' => 'MIT'];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('license', $result[0]);
        $this->assertArrayHasKey('id', $result[0]['license']);
        $this->assertEquals('MIT', $result[0]['license']['id']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with SPDX complex license expression.
     */
    public function testTransformSpdxComplexExpression(): void
    {
        $sourceData = ['license' => '(MIT OR Apache-2.0) AND GPL-2.0-only'];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('license', $result[0]);
        $this->assertArrayHasKey('expression', $result[0]['license']);
        $this->assertEquals('(MIT OR Apache-2.0) AND GPL-2.0-only', $result[0]['license']['expression']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with CycloneDX to SPDX conversion.
     */
    public function testTransformCycloneDxToSpdx(): void
    {
        $sourceData = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('license', $result);
        $this->assertEquals('MIT', $result['license']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with multiple CycloneDX licenses.
     */
    public function testTransformMultipleCycloneDxLicenses(): void
    {
        $sourceData = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ],
            [
                'license' => [
                    'id' => 'Apache-2.0'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('license', $result);
        $this->assertEquals('(MIT OR Apache-2.0)', $result['license']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with CycloneDX license expression.
     */
    public function testTransformCycloneDxWithExpression(): void
    {
        $sourceData = [
            [
                'license' => [
                    'expression' => '(MIT OR Apache-2.0) AND GPL-2.0-only'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertArrayHasKey('license', $result);
        $this->assertEquals('(MIT OR Apache-2.0) AND GPL-2.0-only', $result['license']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with empty data.
     */
    public function testTransformWithEmptyData(): void
    {
        $sourceData = [];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Unknown license data format', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid SPDX data.
     */
    public function testTransformWithInvalidSpdxData(): void
    {
        $sourceData = ['not_a_license' => 'value'];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Unknown license data format', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid CycloneDX data.
     */
    public function testTransformWithInvalidCycloneDxData(): void
    {
        $sourceData = [
            [
                'not_a_license' => 'value'
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Unknown license data format', $errors[0]->getMessage());
    }

    /**
     * Test transforming a simple SPDX license ID to CycloneDX format.
     */
    public function testTransformSimpleSpdxLicenseToCycloneDx(): void
    {
        $spdxLicense = 'MIT';
        $warnings = [];

        $cyclonedxLicense = $this->transformer->transformSpdxLicenseToCycloneDx($spdxLicense, $warnings);

        $this->assertCount(1, $cyclonedxLicense);
        $this->assertArrayHasKey('license', $cyclonedxLicense[0]);
        $this->assertArrayHasKey('id', $cyclonedxLicense[0]['license']);
        $this->assertEquals('MIT', $cyclonedxLicense[0]['license']['id']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming a complex SPDX license expression to CycloneDX format.
     */
    public function testTransformComplexSpdxLicenseToCycloneDx(): void
    {
        $spdxLicense = '(MIT OR Apache-2.0) AND GPL-2.0-only';
        $warnings = [];

        $cyclonedxLicense = $this->transformer->transformSpdxLicenseToCycloneDx($spdxLicense, $warnings);

        $this->assertCount(1, $cyclonedxLicense);
        $this->assertArrayHasKey('license', $cyclonedxLicense[0]);
        $this->assertArrayHasKey('expression', $cyclonedxLicense[0]['license']);
        $this->assertEquals($spdxLicense, $cyclonedxLicense[0]['license']['expression']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming NOASSERTION and NONE license values.
     */
    public function testTransformNoassertionLicense(): void
    {
        $warnings = [];

        // Test with NOASSERTION
        $cyclonedxLicense = $this->transformer->transformSpdxLicenseToCycloneDx('NOASSERTION', $warnings);
        $this->assertEmpty($cyclonedxLicense);

        // Test with NONE
        $cyclonedxLicense = $this->transformer->transformSpdxLicenseToCycloneDx('NONE', $warnings);
        $this->assertEmpty($cyclonedxLicense);

        // Test with empty string
        $cyclonedxLicense = $this->transformer->transformSpdxLicenseToCycloneDx('', $warnings);
        $this->assertEmpty($cyclonedxLicense);
    }

    /**
     * Test transforming CycloneDX license with ID to SPDX format.
     */
    public function testTransformCycloneDxLicenseWithIdToSpdx(): void
    {
        $cyclonedxLicenses = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($cyclonedxLicenses, $warnings);

        $this->assertEquals('MIT', $spdxLicense);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming CycloneDX license with name to SPDX format.
     */
    public function testTransformCycloneDxLicenseWithNameToSpdx(): void
    {
        $cyclonedxLicenses = [
            [
                'license' => [
                    'name' => 'MIT License'
                ]
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($cyclonedxLicenses, $warnings);

        $this->assertEquals('MIT License', $spdxLicense);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming CycloneDX license with expression to SPDX format.
     */
    public function testTransformCycloneDxLicenseWithExpressionToSpdx(): void
    {
        $expression = '(MIT OR Apache-2.0) AND GPL-2.0-only';
        $cyclonedxLicenses = [
            [
                'license' => [
                    'expression' => $expression
                ]
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($cyclonedxLicenses, $warnings);

        $this->assertEquals($expression, $spdxLicense);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming multiple CycloneDX licenses to SPDX format.
     */
    public function testTransformMultipleCycloneDxLicensesToSpdx(): void
    {
        $cyclonedxLicenses = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ],
            [
                'license' => [
                    'id' => 'Apache-2.0'
                ]
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($cyclonedxLicenses, $warnings);

        $this->assertEquals('(MIT OR Apache-2.0)', $spdxLicense);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming empty CycloneDX licenses array to SPDX format.
     */
    public function testTransformEmptyCycloneDxLicensesToSpdx(): void
    {
        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx([], $warnings);

        $this->assertEquals('NOASSERTION', $spdxLicense);
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling malformed CycloneDX license entries.
     */
    public function testHandlingMalformedCycloneDxLicenses(): void
    {
        // Missing license object
        $malformedLicenses = [
            [
                'something' => 'value'
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($malformedLicenses, $warnings);

        $this->assertEquals('NOASSERTION', $spdxLicense);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('missing license object', $warnings[0]);

        // Missing id, name, and expression
        $malformedLicenses = [
            [
                'license' => [
                    'something' => 'value'
                ]
            ]
        ];

        $warnings = [];
        $spdxLicense = $this->transformer->transformCycloneDxLicenseToSpdx($malformedLicenses, $warnings);

        $this->assertEquals('NOASSERTION', $spdxLicense);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('missing license id/name', $warnings[0]);
    }

    /**
     * Test adding license to a CycloneDX component.
     */
    public function testAddLicenseToComponent(): void
    {
        $component = [
            'name' => 'test-component',
            'version' => '1.0.0'
        ];

        $updatedComponent = $this->transformer->addLicenseToComponent($component, 'MIT');

        $this->assertArrayHasKey('licenses', $updatedComponent);
        $this->assertCount(1, $updatedComponent['licenses']);
        $this->assertEquals('MIT', $updatedComponent['licenses'][0]['license']['id']);

        // Test with empty license
        $component = [
            'name' => 'test-component',
            'version' => '1.0.0'
        ];

        $updatedComponent = $this->transformer->addLicenseToComponent($component, '');

        $this->assertArrayNotHasKey('licenses', $updatedComponent);

        // Test when license already set
        $componentWithLicense = [
            'name' => 'test-component',
            'version' => '1.0.0',
            'licenses' => [
                [
                    'license' => [
                        'id' => 'Apache-2.0'
                    ]
                ]
            ]
        ];

        $updatedComponent = $this->transformer->addLicenseToComponent($componentWithLicense, 'MIT');

        $this->assertArrayHasKey('licenses', $updatedComponent);
        $this->assertEquals('Apache-2.0', $updatedComponent['licenses'][0]['license']['id']);
    }

    /**
     * Test adding licenses to an SPDX package.
     */
    public function testAddLicensesToPackage(): void
    {
        $package = [
            'name' => 'test-package',
            'versionInfo' => '1.0.0'
        ];

        $licenses = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ]
        ];

        $warnings = [];
        $updatedPackage = $this->transformer->addLicensesToPackage($package, $licenses, $warnings);

        $this->assertArrayHasKey('licenseConcluded', $updatedPackage);
        $this->assertEquals('MIT', $updatedPackage['licenseConcluded']);
        $this->assertArrayHasKey('licenseDeclared', $updatedPackage);
        $this->assertEquals('MIT', $updatedPackage['licenseDeclared']);
        $this->assertEmpty($warnings);

        // Test with empty licenses
        $package = [
            'name' => 'test-package',
            'versionInfo' => '1.0.0'
        ];

        $warnings = [];
        $updatedPackage = $this->transformer->addLicensesToPackage($package, [], $warnings);

        $this->assertSame($package, $updatedPackage);
        $this->assertEmpty($warnings);

        // Test when licenseDeclared already set
        $packageWithLicense = [
            'name' => 'test-package',
            'versionInfo' => '1.0.0',
            'licenseDeclared' => 'Apache-2.0'
        ];

        $licenses = [
            [
                'license' => [
                    'id' => 'MIT'
                ]
            ]
        ];

        $warnings = [];
        $updatedPackage = $this->transformer->addLicensesToPackage($packageWithLicense, $licenses, $warnings);

        $this->assertArrayHasKey('licenseConcluded', $updatedPackage);
        $this->assertEquals('MIT', $updatedPackage['licenseConcluded']);
        $this->assertArrayHasKey('licenseDeclared', $updatedPackage);
        $this->assertEquals('Apache-2.0', $updatedPackage['licenseDeclared']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test getCommonSpdxLicenseIds method.
     */
    public function testGetCommonSpdxLicenseIds(): void
    {
        $licenses = $this->transformer->getCommonSpdxLicenseIds();

        $this->assertNotEmpty($licenses);
        $this->assertIsArray($licenses);
        $this->assertContains('MIT', $licenses);
        $this->assertContains('Apache-2.0', $licenses);
        $this->assertContains('GPL-3.0-only', $licenses);
    }
}