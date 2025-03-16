<?php

namespace SBOMinator\Transformatron\Tests\Unit\Transformer;

use PHPUnit\Framework\TestCase;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\TransformerInterface;

/**
 * Test cases for HashTransformer class.
 */
class HashTransformerTest extends TestCase
{
    /**
     * @var HashTransformer
     */
    private HashTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new HashTransformer();
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
        $checksums = [
            [
                'algorithm' => 'SHA1',
                'checksumValue' => 'a1b2c3d4e5f6'
            ],
            [
                'algorithm' => 'SHA256',
                'checksumValue' => '1a2b3c4d5e6f'
            ]
        ];

        $warnings = [];
        $errors = [];
        $hashes = $this->transformer->transform($checksums, $warnings, $errors);

        $this->assertCount(2, $hashes);
        $this->assertEquals('SHA-1', $hashes[0]['alg']);
        $this->assertEquals('a1b2c3d4e5f6', $hashes[0]['content']);
        $this->assertEquals('SHA-256', $hashes[1]['alg']);
        $this->assertEquals('1a2b3c4d5e6f', $hashes[1]['content']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with CycloneDX to SPDX conversion.
     */
    public function testTransformCycloneDxToSpdx(): void
    {
        $hashes = [
            [
                'alg' => 'SHA-1',
                'content' => 'a1b2c3d4e5f6'
            ],
            [
                'alg' => 'SHA-256',
                'content' => '1a2b3c4d5e6f'
            ]
        ];

        $warnings = [];
        $errors = [];
        $checksums = $this->transformer->transform($hashes, $warnings, $errors);

        $this->assertCount(2, $checksums);
        $this->assertEquals('SHA1', $checksums[0]['algorithm']);
        $this->assertEquals('a1b2c3d4e5f6', $checksums[0]['checksumValue']);
        $this->assertEquals('SHA256', $checksums[1]['algorithm']);
        $this->assertEquals('1a2b3c4d5e6f', $checksums[1]['checksumValue']);
        $this->assertEmpty($warnings);
        $this->assertEmpty($errors);
    }

    /**
     * Test the transform method with unknown format.
     */
    public function testTransformWithUnknownFormat(): void
    {
        $invalidData = [
            [
                'invalid_key' => 'invalid_value'
            ]
        ];

        $warnings = [];
        $errors = [];
        $result = $this->transformer->transform($invalidData, $warnings, $errors);

        $this->assertEmpty($result);
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals('Unknown hash data format', $errors[0]->getMessage());
    }

    /**
     * Test transforming SPDX checksums to CycloneDX hashes.
     */
    public function testTransformSpdxChecksumsToCycloneDxHashes(): void
    {
        $checksums = [
            [
                'algorithm' => 'SHA1',
                'checksumValue' => 'a1b2c3d4e5f6'
            ],
            [
                'algorithm' => 'SHA256',
                'checksumValue' => '1a2b3c4d5e6f'
            ],
            [
                'algorithm' => 'MD5',
                'checksumValue' => 'abcdef123456'
            ]
        ];

        $warnings = [];
        $hashes = $this->transformer->transformSpdxChecksumsToCycloneDxHashes($checksums, $warnings);

        $this->assertCount(3, $hashes);
        $this->assertEquals('SHA-1', $hashes[0]['alg']);
        $this->assertEquals('a1b2c3d4e5f6', $hashes[0]['content']);
        $this->assertEquals('SHA-256', $hashes[1]['alg']);
        $this->assertEquals('1a2b3c4d5e6f', $hashes[1]['content']);
        $this->assertEquals('MD5', $hashes[2]['alg']);
        $this->assertEquals('abcdef123456', $hashes[2]['content']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test transforming CycloneDX hashes to SPDX checksums.
     */
    public function testTransformCycloneDxHashesToSpdxChecksums(): void
    {
        $hashes = [
            [
                'alg' => 'SHA-1',
                'content' => 'a1b2c3d4e5f6'
            ],
            [
                'alg' => 'SHA-256',
                'content' => '1a2b3c4d5e6f'
            ],
            [
                'alg' => 'MD5',
                'content' => 'abcdef123456'
            ]
        ];

        $warnings = [];
        $checksums = $this->transformer->transformCycloneDxHashesToSpdxChecksums($hashes, $warnings);

        $this->assertCount(3, $checksums);
        $this->assertEquals('SHA1', $checksums[0]['algorithm']);
        $this->assertEquals('a1b2c3d4e5f6', $checksums[0]['checksumValue']);
        $this->assertEquals('SHA256', $checksums[1]['algorithm']);
        $this->assertEquals('1a2b3c4d5e6f', $checksums[1]['checksumValue']);
        $this->assertEquals('MD5', $checksums[2]['algorithm']);
        $this->assertEquals('abcdef123456', $checksums[2]['checksumValue']);
        $this->assertEmpty($warnings);
    }

    /**
     * Test mapping SPDX hash algorithm to CycloneDX.
     */
    public function testMapSpdxHashAlgorithmToCycloneDx(): void
    {
        $this->assertEquals('SHA-1', $this->transformer->mapSpdxHashAlgorithmToCycloneDx('SHA1'));
        $this->assertEquals('SHA-256', $this->transformer->mapSpdxHashAlgorithmToCycloneDx('SHA256'));
        $this->assertEquals('SHA-512', $this->transformer->mapSpdxHashAlgorithmToCycloneDx('SHA512'));
        $this->assertEquals('MD5', $this->transformer->mapSpdxHashAlgorithmToCycloneDx('MD5'));

        // Case insensitivity check
        $this->assertEquals('SHA-1', $this->transformer->mapSpdxHashAlgorithmToCycloneDx('sha1'));

        // Unsupported algorithm
        $this->assertNull($this->transformer->mapSpdxHashAlgorithmToCycloneDx('UNSUPPORTED'));
    }

    /**
     * Test mapping CycloneDX hash algorithm to SPDX.
     */
    public function testMapCycloneDxHashAlgorithmToSpdx(): void
    {
        $this->assertEquals('SHA1', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('SHA-1'));
        $this->assertEquals('SHA256', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('SHA-256'));
        $this->assertEquals('SHA512', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('SHA-512'));
        $this->assertEquals('MD5', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('MD5'));

        // Dash normalization
        $this->assertEquals('SHA256', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('SHA256'));

        // Case insensitivity check
        $this->assertEquals('SHA1', $this->transformer->mapCycloneDxHashAlgorithmToSpdx('sha-1'));

        // Unsupported algorithm
        $this->assertNull($this->transformer->mapCycloneDxHashAlgorithmToSpdx('UNSUPPORTED'));
    }

    /**
     * Test handling malformed checksums.
     */
    public function testHandlingMalformedChecksums(): void
    {
        // Missing algorithm
        $malformedChecksum = [
            'checksumValue' => 'a1b2c3d4e5f6'
        ];

        $warnings = [];
        $result = $this->transformer->convertSpdxChecksumToCycloneDxHash($malformedChecksum, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Malformed checksum entry', $warnings[0]);

        // Missing checksumValue
        $malformedChecksum = [
            'algorithm' => 'SHA1'
        ];

        $warnings = [];
        $result = $this->transformer->convertSpdxChecksumToCycloneDxHash($malformedChecksum, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);

        // Unsupported algorithm
        $unsupportedChecksum = [
            'algorithm' => 'UNSUPPORTED',
            'checksumValue' => 'a1b2c3d4e5f6'
        ];

        $warnings = [];
        $result = $this->transformer->convertSpdxChecksumToCycloneDxHash($unsupportedChecksum, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Unsupported hash algorithm', $warnings[0]);
    }

    /**
     * Test handling malformed hashes.
     */
    public function testHandlingMalformedHashes(): void
    {
        // Missing alg
        $malformedHash = [
            'content' => 'a1b2c3d4e5f6'
        ];

        $warnings = [];
        $result = $this->transformer->convertCycloneDxHashToSpdxChecksum($malformedHash, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Malformed hash entry', $warnings[0]);

        // Missing content
        $malformedHash = [
            'alg' => 'SHA-1'
        ];

        $warnings = [];
        $result = $this->transformer->convertCycloneDxHashToSpdxChecksum($malformedHash, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);

        // Unsupported algorithm
        $unsupportedHash = [
            'alg' => 'UNSUPPORTED',
            'content' => 'a1b2c3d4e5f6'
        ];

        $warnings = [];
        $result = $this->transformer->convertCycloneDxHashToSpdxChecksum($unsupportedHash, $warnings);

        $this->assertNull($result);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Unsupported hash algorithm', $warnings[0]);
    }

    /**
     * Test supported hash algorithms.
     */
    public function testSupportedAlgorithms(): void
    {
        $spdxAlgorithms = $this->transformer->getSupportedSpdxAlgorithms();
        $cyclonedxAlgorithms = $this->transformer->getSupportedCycloneDxAlgorithms();

        $this->assertNotEmpty($spdxAlgorithms);
        $this->assertNotEmpty($cyclonedxAlgorithms);

        // Check that algorithms match after normalization
        $this->assertCount(count($spdxAlgorithms), $cyclonedxAlgorithms,
            'SPDX and CycloneDX should support the same number of algorithms');

        // Check that SHA-256 is supported in both formats
        $this->assertContains('SHA256', $spdxAlgorithms);
        $this->assertContains('SHA-256', $cyclonedxAlgorithms);
    }
}