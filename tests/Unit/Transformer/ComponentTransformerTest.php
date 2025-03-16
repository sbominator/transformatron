<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Transformer\ComponentTransformer;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;

/**
 * Test cases for ComponentTransformer class.
 */
class ComponentTransformerTest extends TestCase
{
    /**
     * @var ComponentTransformer
     */
    private ComponentTransformer $transformer;

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

        $this->transformer = new ComponentTransformer(
            $this->hashTransformer,
            $this->licenseTransformer,
            $this->spdxIdTransformer
        );
    }

    /**
     * Test transforming CycloneDX components to SPDX packages.
     */
    public function testTransformComponentsToPackages(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturnCallback(function ($id) {
                return 'SPDXRef-' . $id;
            });

        $components = [
            [
                'name' => 'component1',
                'bom-ref' => 'component-1',
                'version' => '1.0.0'
            ],
            [
                'name' => 'component2',
                'bom-ref' => 'component-2',
                'version' => '2.0.0'
            ]
        ];

        $warnings = [];
        $packages = $this->transformer->transformComponentsToPackages($components, $warnings);

        $this->assertCount(2, $packages);
        $this->assertEquals('SPDXRef-component-1', $packages[0]['SPDXID']);
        $this->assertEquals('component1', $packages[0]['name']);
        $this->assertEquals('1.0.0', $packages[0]['versionInfo']);
        $this->assertEquals('SPDXRef-component-2', $packages[1]['SPDXID']);
        $this->assertEquals('component2', $packages[1]['name']);
        $this->assertEquals('2.0.0', $packages[1]['versionInfo']);
    }

    /**
     * Test transforming a single CycloneDX component to an SPDX package.
     */
    public function testTransformComponentToPackage(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturn('SPDXRef-component-1');

        $component = [
            'name' => 'component1',
            'bom-ref' => 'component-1',
            'version' => '1.0.0',
            'description' => 'Test component',
            'licenses' => [
                [
                    'license' => [
                        'id' => 'MIT'
                    ]
                ]
            ],
            'hashes' => [
                [
                    'alg' => 'SHA-1',
                    'content' => 'a1b2c3d4e5f6'
                ]
            ]
        ];

        // Setup license transformer mock
        $this->licenseTransformer->method('addLicensesToPackage')
            ->willReturnCallback(function ($package, $licenses, &$warnings) {
                $package['licenseConcluded'] = 'MIT';
                return $package;
            });

        // Setup hash transformer mock
        $this->hashTransformer->method('transformCycloneDxHashesToSpdxChecksums')
            ->willReturn([['algorithm' => 'SHA1', 'checksumValue' => 'a1b2c3d4e5f6']]);

        $warnings = [];
        $package = $this->transformer->transformComponentToPackage($component, $warnings);

        $this->assertEquals('SPDXRef-component-1', $package['SPDXID']);
        $this->assertEquals('component1', $package['name']);
        $this->assertEquals('1.0.0', $package['versionInfo']);
        $this->assertEquals('Test component', $package['description']);
        $this->assertArrayHasKey('licenseConcluded', $package);
        $this->assertEquals('MIT', $package['licenseConcluded']);
        $this->assertArrayHasKey('checksums', $package);
        $this->assertEquals('SHA1', $package['checksums'][0]['algorithm']);
        $this->assertFalse($package['filesAnalyzed']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test handling component with missing name.
     */
    public function testHandlingComponentWithMissingName(): void
    {
        // Setup mock expectations
        $this->spdxIdTransformer->method('formatAsSpdxId')
            ->willReturn('SPDXRef-component-1');

        $component = [
            'bom-ref' => 'component-1',
            'version' => '1.0.0'
            // name is missing
        ];

        $warnings = [];
        $package = $this->transformer->transformComponentToPackage($component, $warnings);

        $this->assertEquals('SPDXRef-component-1', $package['SPDXID']);
        $this->assertStringStartsWith('unknown-', $package['name']);
        $this->assertEquals('1.0.0', $package['versionInfo']);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('missing required field: name', $warnings[0]);
    }

    /**
     * Test getBomRefFromComponent method.
     */
    public function testGetBomRefFromComponent(): void
    {
        $method = new \ReflectionMethod(ComponentTransformer::class, 'getBomRefFromComponent');
        $method->setAccessible(true);

        // Test with bom-ref
        $component = ['bom-ref' => 'component-1'];
        $result = $method->invoke($this->transformer, $component);
        $this->assertEquals('component-1', $result);

        // Test with purl
        $component = ['purl' => 'pkg:npm/example@1.0.0'];
        $result = $method->invoke($this->transformer, $component);
        $this->assertEquals('pkg:npm/example@1.0.0', $result);

        // Test with name and version
        $component = ['name' => 'example', 'version' => '1.0.0'];
        $result = $method->invoke($this->transformer, $component);
        $this->assertStringStartsWith('example-1.0.0-', $result);

        // Test with name only
        $component = ['name' => 'example'];
        $result = $method->invoke($this->transformer, $component);
        $this->assertStringStartsWith('example-', $result);

        // Test with no identifiers
        $component = [];
        $result = $method->invoke($this->transformer, $component);
        $this->assertStringStartsWith('unknown-', $result);
    }

    /**
     * Test extractNameFromPurl method.
     */
    public function testExtractNameFromPurl(): void
    {
        $method = new \ReflectionMethod(ComponentTransformer::class, 'extractNameFromPurl');
        $method->setAccessible(true);

        // Test with npm purl
        $purl = 'pkg:npm/example@1.0.0';
        $result = $method->invoke($this->transformer, $purl);
        $this->assertEquals('example', $result);

        // Test with GitHub purl
        $purl = 'pkg:github/owner/repo@v1.0.0';
        $result = $method->invoke($this->transformer, $purl);
        $this->assertEquals('repo', $result);

        // Test with Maven purl
        $purl = 'pkg:maven/org.example/library@1.0.0';
        $result = $method->invoke($this->transformer, $purl);
        $this->assertEquals('library', $result);

        // Test with unexpected format
        $purl = 'not-a-purl';
        $result = $method->invoke($this->transformer, $purl);
        $this->assertStringStartsWith('package-', $result);
    }

    /**
     * Test adding warnings for unknown component fields.
     */
    public function testAddUnknownComponentFieldWarnings(): void
    {
        $component = [
            'name' => 'test-component',
            'bom-ref' => 'component-1',
            'version' => '1.0.0',
            'unknownField1' => 'value1',
            'unknownField2' => 'value2'
        ];

        $warnings = [];

        // Use a different approach with ReflectionMethod
        $class = new \ReflectionClass(ComponentTransformer::class);
        $method = $class->getMethod('addUnknownComponentFieldWarnings');
        $method->setAccessible(true);

        // Call the method with the reference parameter
        $method->invokeArgs($this->transformer, [$component, &$warnings]);

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('Unknown or unmapped component field', $warnings[0]);
        $this->assertStringContainsString('Unknown or unmapped component field', $warnings[1]);
    }

    /**
     * Test handle component field transformation.
     */
    public function testHandleComponentFieldTransformation(): void
    {
        // Use a different approach to test this method with reference parameters
        $transformMethod = new \ReflectionMethod(
            ComponentTransformer::class,
            'handleComponentFieldTransformation'
        );
        $transformMethod->setAccessible(true);

        // Set up test data for hashes transformation
        $package = [];
        $hashes = [['alg' => 'SHA-1', 'content' => 'a1b2c3d4e5f6']];
        $warnings = [];

        $this->hashTransformer->method('transformCycloneDxHashesToSpdxChecksums')
            ->willReturn([['algorithm' => 'SHA1', 'checksumValue' => 'a1b2c3d4e5f6']]);

        // Call the method with the reference parameter
        $result = $transformMethod->invokeArgs(
            $this->transformer,
            [$package, 'hashes', 'checksums', $hashes, &$warnings]
        );

        $this->assertArrayHasKey('checksums', $result);
        $this->assertEquals('SHA1', $result['checksums'][0]['algorithm']);

        // Test licenses transformation
        $package = [];
        $licenses = [['license' => ['id' => 'MIT']]];
        $warnings = [];

        $this->licenseTransformer->method('addLicensesToPackage')
            ->willReturnCallback(function ($pkg, $lic, &$warn) {
                $pkg['licenseConcluded'] = 'MIT';
                return $pkg;
            });

        $result = $transformMethod->invokeArgs(
            $this->transformer,
            [$package, 'licenses', 'licenseConcluded', $licenses, &$warnings]
        );

        $this->assertArrayHasKey('licenseConcluded', $result);
        $this->assertEquals('MIT', $result['licenseConcluded']);

        // Test purl transformation
        $package = [];
        $purl = 'pkg:npm/example@1.0.0';
        $warnings = [];

        $result = $transformMethod->invokeArgs(
            $this->transformer,
            [$package, 'purl', 'downloadLocation', $purl, &$warnings]
        );

        $this->assertArrayHasKey('downloadLocation', $result);
        $this->assertEquals($purl, $result['downloadLocation']);

        // Test direct field mapping
        $package = [];
        $warnings = [];

        $result = $transformMethod->invokeArgs(
            $this->transformer,
            [$package, 'name', 'name', 'test-component', &$warnings]
        );

        $this->assertEquals('test-component', $result['name']);
    }
}