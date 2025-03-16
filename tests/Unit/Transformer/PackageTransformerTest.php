<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\PackageTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for PackageTransformer class.
 */
class PackageTransformerTest extends TestCase
{
    /**
     * @var PackageTransformer
     */
    private PackageTransformer $transformer;

    /**
     * @var HashTransformer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $hashTransformer;

    /**
     * @var LicenseTransformer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $licenseTransformer;

    /**
     * @var SpdxIdTransformer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $spdxIdTransformer;

    protected function setUp(): void
    {
        $this->hashTransformer = $this->createMock(HashTransformer::class);
        $this->licenseTransformer = $this->createMock(LicenseTransformer::class);
        $this->spdxIdTransformer = $this->createMock(SpdxIdTransformer::class);

        $this->transformer = new PackageTransformer(
            $this->hashTransformer,
            $this->licenseTransformer,
            $this->spdxIdTransformer
        );
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
     * Test the transform method with valid packages.
     */
    public function testTransformWithValidPackages(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        // Create test data
        $sourceData = [
            'packages' => [
                [
                    'name' => 'package1',
                    'SPDXID' => 'SPDXRef-Package-1',
                    'versionInfo' => '1.0.0'
                ],
                [
                    'name' => 'package2',
                    'SPDXID' => 'SPDXRef-Package-2',
                    'versionInfo' => '2.0.0'
                ]
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        // Verify results
        $this->assertArrayHasKey('components', $result);
        $this->assertCount(2, $result['components']);
        $this->assertEquals('Package-1', $result['components'][0]['bom-ref']);
        $this->assertEquals('package1', $result['components'][0]['name']);
        $this->assertEquals('1.0.0', $result['components'][0]['version']);
        $this->assertEquals('Package-2', $result['components'][1]['bom-ref']);
        $this->assertEquals('package2', $result['components'][1]['name']);
        $this->assertEquals('2.0.0', $result['components'][1]['version']);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with missing packages.
     */
    public function testTransformWithMissingPackages(): void
    {
        $sourceData = [
            'notPackages' => []
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid packages array in source data', $errors[0]->getMessage());
    }

    /**
     * Test the transform method with invalid packages.
     */
    public function testTransformWithInvalidPackages(): void
    {
        $sourceData = [
            'packages' => 'not an array'
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($sourceData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Missing or invalid packages array in source data', $errors[0]->getMessage());
    }

    /**
     * Test transforming SPDX packages to CycloneDX components.
     */
    public function testTransformPackagesToComponents(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturnCallback(function ($id) {
                return str_replace('SPDXRef-', '', $id);
            });

        $packages = [
            [
                'name' => 'package1',
                'SPDXID' => 'SPDXRef-Package-1',
                'versionInfo' => '1.0.0'
            ],
            [
                'name' => 'package2',
                'SPDXID' => 'SPDXRef-Package-2',
                'versionInfo' => '2.0.0'
            ]
        ];

        $warnings = [];
        $components = $this->transformer->transformPackagesToComponents($packages, $warnings);

        $this->assertCount(2, $components);
        $this->assertEquals('Package-1', $components[0]['bom-ref']);
        $this->assertEquals('package1', $components[0]['name']);
        $this->assertEquals('1.0.0', $components[0]['version']);
        $this->assertEquals('Package-2', $components[1]['bom-ref']);
        $this->assertEquals('package2', $components[1]['name']);
        $this->assertEquals('2.0.0', $components[1]['version']);
    }

    /**
     * Test transforming a single SPDX package to a CycloneDX component.
     */
    public function testTransformPackageToComponent(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturn('Package-1');

        $package = [
            'name' => 'package1',
            'SPDXID' => 'SPDXRef-Package-1',
            'versionInfo' => '1.0.0',
            'description' => 'Test package',
            'licenseConcluded' => 'MIT',
            'checksums' => [
                [
                    'algorithm' => 'SHA1',
                    'checksumValue' => 'a1b2c3d4e5f6'
                ]
            ]
        ];

        // Setup license transformer mock
        $this->licenseTransformer->method('transformSpdxLicenseToCycloneDx')
            ->willReturn([['license' => ['id' => 'MIT']]]);

        // Setup hash transformer mock
        $this->hashTransformer->method('transformSpdxChecksumsToCycloneDxHashes')
            ->willReturn([['alg' => 'SHA-1', 'content' => 'a1b2c3d4e5f6']]);

        $warnings = [];
        $component = $this->transformer->transformPackageToComponent($package, $warnings);

        $this->assertEquals('Package-1', $component['bom-ref']);
        $this->assertEquals('package1', $component['name']);
        $this->assertEquals('1.0.0', $component['version']);
        $this->assertEquals('Test package', $component['description']);
        $this->assertArrayHasKey('licenses', $component);
        $this->assertEquals('MIT', $component['licenses'][0]['license']['id']);
        $this->assertArrayHasKey('hashes', $component);
        $this->assertEquals('SHA-1', $component['hashes'][0]['alg']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling package with missing name.
     */
    public function testHandlingPackageWithMissingName(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('transformSpdxId')
            ->willReturn('Package-1');

        $package = [
            'SPDXID' => 'SPDXRef-Package-1',
            'versionInfo' => '1.0.0'
            // name is missing
        ];

        $warnings = [];
        $component = $this->transformer->transformPackageToComponent($package, $warnings);

        $this->assertEquals('Package-1', $component['bom-ref']);
        $this->assertStringStartsWith('unknown-', $component['name']);
        $this->assertEquals('1.0.0', $component['version']);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('missing required field: name', $warnings[0]);
    }

    /**
     * Test component type determination.
     */
    public function testComponentTypeDetermination(): void
    {
        // Create a test-accessible version of the method
        $determineComponentType = new \ReflectionMethod(
            PackageTransformer::class,
            'determineComponentType'
        );
        $determineComponentType->setAccessible(true);

        // Test app detection
        $appPackage = ['name' => 'test-app', 'comment' => 'An application'];
        $this->assertEquals(
            'application',
            $determineComponentType->invoke($this->transformer, $appPackage)
        );

        // Test framework detection
        $frameworkPackage = ['name' => 'test-framework'];
        $this->assertEquals(
            'framework',
            $determineComponentType->invoke($this->transformer, $frameworkPackage)
        );

        // Test OS detection
        $osPackage = ['name' => 'linux-kernel'];
        $this->assertEquals(
            'operating-system',
            $determineComponentType->invoke($this->transformer, $osPackage)
        );

        // Test default library type
        $libraryPackage = ['name' => 'test-lib'];
        $this->assertEquals(
            'library',
            $determineComponentType->invoke($this->transformer, $libraryPackage)
        );
    }

    /**
     * Test handling package verification code as hash.
     */
    public function testAddPackageVerificationCodeAsHash(): void
    {
        // Create a test-accessible version of the method
        $addPackageVerificationCodeAsHash = new \ReflectionMethod(
            PackageTransformer::class,
            'addPackageVerificationCodeAsHash'
        );
        $addPackageVerificationCodeAsHash->setAccessible(true);

        // Test adding verification code
        $component = [];
        $verificationCode = ['value' => 'a1b2c3d4e5f6'];
        $result = $addPackageVerificationCodeAsHash->invoke(
            $this->transformer,
            $component,
            $verificationCode
        );

        $this->assertArrayHasKey('hashes', $result);
        $this->assertEquals('SHA1', $result['hashes'][0]['alg']);
        $this->assertEquals('a1b2c3d4e5f6', $result['hashes'][0]['content']);

        // Test with existing hashes
        $component = ['hashes' => [['alg' => 'MD5', 'content' => '123456']]];
        $verificationCode = ['value' => 'a1b2c3d4e5f6'];
        $result = $addPackageVerificationCodeAsHash->invoke(
            $this->transformer,
            $component,
            $verificationCode
        );

        $this->assertArrayHasKey('hashes', $result);
        $this->assertCount(2, $result['hashes']);
        $this->assertEquals('SHA1', $result['hashes'][1]['alg']);
        $this->assertEquals('a1b2c3d4e5f6', $result['hashes'][1]['content']);

        // Test with missing value
        $component = [];
        $verificationCode = [];
        $result = $addPackageVerificationCodeAsHash->invoke(
            $this->transformer,
            $component,
            $verificationCode
        );

        $this->assertArrayNotHasKey('hashes', $result);
    }

    /**
     * Test transforming download location to purl.
     */
    public function testTransformDownloadLocationToPurl(): void
    {
        // Create a test-accessible version of the method
        $transformDownloadLocationToPurl = new \ReflectionMethod(
            PackageTransformer::class,
            'transformDownloadLocationToPurl'
        );
        $transformDownloadLocationToPurl->setAccessible(true);

        // Test with GitHub URL
        $githubUrl = 'git+https://github.com/example/repo@v1.0.0';
        $result = $transformDownloadLocationToPurl->invoke($this->transformer, $githubUrl);
        $this->assertEquals('pkg:github/example/repo@v1.0.0', $result);

        // Test with GitHub URL without version
        $githubUrlNoVersion = 'git+https://github.com/example/repo';
        $result = $transformDownloadLocationToPurl->invoke($this->transformer, $githubUrlNoVersion);
        $this->assertEquals('pkg:github/example/repo', $result);

        // Test with existing purl
        $purl = 'pkg:composer/example/package@1.0.0';
        $result = $transformDownloadLocationToPurl->invoke($this->transformer, $purl);
        $this->assertEquals($purl, $result);

        // Test with non-convertible URL
        $otherUrl = 'https://example.com/download';
        $result = $transformDownloadLocationToPurl->invoke($this->transformer, $otherUrl);
        $this->assertEquals($otherUrl, $result);
    }

    /**
     * Test adding warnings for unknown package fields.
     */
    public function testAddUnknownPackageFieldWarnings(): void
    {
        // Create a test-accessible version of the method
        $warnings = [];
        $reflection = new \ReflectionMethod(
            PackageTransformer::class,
            'addUnknownPackageFieldWarnings'
        );
        $reflection->setAccessible(true);

        $package = [
            'name' => 'test-package',
            'SPDXID' => 'SPDXRef-Package-1',
            'versionInfo' => '1.0.0',
            'unknownField1' => 'value1',
            'unknownField2' => 'value2'
        ];

        $reflection->invokeArgs($this->transformer, [$package, &$warnings]);

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('Unknown or unmapped package field', $warnings[0]);
        $this->assertStringContainsString('Unknown or unmapped package field', $warnings[1]);
    }

    /**
     * Test handle package field transformation.
     */
    public function testHandlePackageFieldTransformation(): void
    {
        // Create a test-accessible version of the method
        $warnings = [];
        $reflection = new \ReflectionMethod(
            PackageTransformer::class,
            'handlePackageFieldTransformation'
        );
        $reflection->setAccessible(true);

        $this->licenseTransformer->method('transformSpdxLicenseToCycloneDx')
            ->willReturn([['license' => ['id' => 'MIT']]]);

        // Test for licenseConcluded transformation
        $component = [];
        $license = 'MIT';

        $result = $reflection->invokeArgs(
            $this->transformer,
            [
                $component,
                'licenseConcluded',
                'licenses',
                $license,
                &$warnings
            ]
        );

        $this->assertArrayHasKey('licenses', $result);
        $this->assertEquals('MIT', $result['licenses'][0]['license']['id']);

        // Test for direct field mapping
        $component = [];
        $result = $reflection->invokeArgs(
            $this->transformer,
            [
                $component,
                'name',
                'name',
                'test-package',
                &$warnings
            ]
        );

        $this->assertEquals('test-package', $result['name']);
    }
}