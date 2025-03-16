<?php

namespace SBOMinator\Transformatron\Factory;

use SBOMinator\Transformatron\Converter\AbstractConverter;
use SBOMinator\Transformatron\Converter\CycloneDxToSpdxConverter;
use SBOMinator\Transformatron\Converter\ConverterInterface;
use SBOMinator\Transformatron\Converter\SpdxToCycloneDxConverter;
use SBOMinator\Transformatron\Enum\FormatEnum;
use SBOMinator\Transformatron\Exception\ValidationException;
use SBOMinator\Transformatron\Error\ConversionError;
use SBOMinator\Transformatron\Transformer\HashTransformer;
use SBOMinator\Transformatron\Transformer\LicenseTransformer;
use SBOMinator\Transformatron\Transformer\SpdxIdTransformer;
use SBOMinator\Transformatron\Transformer\CycloneDxMetadataTransformer;
use SBOMinator\Transformatron\Transformer\CycloneDxReferenceTransformer;
use SBOMinator\Transformatron\Transformer\ComponentTransformer;
use SBOMinator\Transformatron\Transformer\DependencyTransformer;
use SBOMinator\Transformatron\Transformer\PackageTransformer;
use SBOMinator\Transformatron\Transformer\RelationshipTransformer;
use SBOMinator\Transformatron\Transformer\SpdxMetadataTransformer;

/**
 * Factory for creating SBOM converters.
 *
 * This class provides methods for creating the appropriate converter based
 * on source and target formats, as well as detecting the format of JSON input.
 */
class ConverterFactory
{
    /** @var array<string, array<string, class-string<ConverterInterface>>> */
    private const CONVERTER_MAP = [
        FormatEnum::FORMAT_SPDX => [
            FormatEnum::FORMAT_CYCLONEDX => SpdxToCycloneDxConverter::class,
        ],
        FormatEnum::FORMAT_CYCLONEDX => [
            FormatEnum::FORMAT_SPDX => CycloneDxToSpdxConverter::class,
        ],
    ];

    /** @var array<string, string> Format detection keys */
    private const FORMAT_DETECTION_KEYS = [
        'bomFormat' => FormatEnum::FORMAT_CYCLONEDX,
        'spdxVersion' => FormatEnum::FORMAT_SPDX,
    ];

    /** @var array<string, object> Service instances cache */
    private array $services = [];

    /**
     * Create a converter for the given source and target formats.
     *
     * @param string $sourceFormat Source format (e.g., 'SPDX', 'CycloneDX')
     * @param string $targetFormat Target format (e.g., 'SPDX', 'CycloneDX')
     * @return ConverterInterface The appropriate converter
     * @throws \InvalidArgumentException If no converter is available for the specified formats
     */
    public function createConverter(string $sourceFormat, string $targetFormat): ConverterInterface
    {
        // Validate formats
        if (!isset(self::CONVERTER_MAP[$sourceFormat][$targetFormat])) {
            throw new \InvalidArgumentException(
                "No converter available from {$sourceFormat} to {$targetFormat}"
            );
        }

        $converterClass = self::CONVERTER_MAP[$sourceFormat][$targetFormat];

        // Create and return the converter with all dependencies
        return $this->resolveConverter($converterClass);
    }

    /**
     * Create a converter based on the auto-detected source format and specified target format.
     *
     * @param string $json JSON content to analyze
     * @param string $targetFormat Target format (e.g., 'SPDX', 'CycloneDX')
     * @return ConverterInterface The appropriate converter
     * @throws ValidationException If the JSON format cannot be detected
     * @throws \InvalidArgumentException If no converter is available for the formats
     */
    public function createConverterFromJson(string $json, string $targetFormat): ConverterInterface
    {
        $sourceFormat = $this->detectJsonFormat($json);

        if ($sourceFormat === null) {
            throw new ValidationException(
                'Could not detect the format of the provided JSON',
                ['detected_format' => 'unknown']
            );
        }

        // Don't convert if source and target formats are the same
        if ($sourceFormat === $targetFormat) {
            throw new \InvalidArgumentException(
                "Source and target formats are the same: {$sourceFormat}"
            );
        }

        return $this->createConverter($sourceFormat, $targetFormat);
    }

    /**
     * Detect the format of a JSON string.
     *
     * @param string $json JSON content to analyze
     * @return string|null Detected format or null if unknown
     */
    public function detectJsonFormat(string $json): ?string
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                return null;
            }

            // Try to detect format based on known keys
            foreach (self::FORMAT_DETECTION_KEYS as $key => $format) {
                if (isset($data[$key])) {
                    // Additional validation for CycloneDX
                    if ($key === 'bomFormat' && $data[$key] !== 'CycloneDX') {
                        continue;
                    }
                    // Additional validation for SPDX
                    if ($key === 'spdxVersion' && strpos($data[$key], 'SPDX-') !== 0) {
                        continue;
                    }
                    return $format;
                }
            }

            return null;
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Resolve a converter instance with all its dependencies.
     *
     * @param class-string<ConverterInterface> $converterClass Converter class name
     * @return ConverterInterface The initialized converter
     */
    private function resolveConverter(string $converterClass): ConverterInterface
    {
        // Check if we already have an instance
        if (isset($this->services[$converterClass])) {
            return $this->services[$converterClass];
        }

        // Create shared transformers
        $hashTransformer = $this->getOrCreateService(HashTransformer::class);
        $licenseTransformer = $this->getOrCreateService(LicenseTransformer::class);
        $spdxIdTransformer = $this->getOrCreateService(SpdxIdTransformer::class);

        // Create appropriate converter type with dependencies
        if ($converterClass === CycloneDxToSpdxConverter::class) {
            $metadataTransformer = $this->getOrCreateService(CycloneDxMetadataTransformer::class);
            $referenceTransformer = $this->getOrCreateService(CycloneDxReferenceTransformer::class);

            $componentTransformer = new ComponentTransformer(
                $hashTransformer,
                $licenseTransformer,
                $spdxIdTransformer
            );

            $dependencyTransformer = new DependencyTransformer($spdxIdTransformer);

            $converter = new CycloneDxToSpdxConverter();
            $this->setTransformers($converter, $metadataTransformer, $componentTransformer, $dependencyTransformer, $referenceTransformer);

        } elseif ($converterClass === SpdxToCycloneDxConverter::class) {
            $metadataTransformer = $this->getOrCreateService(SpdxMetadataTransformer::class);

            $packageTransformer = new PackageTransformer(
                $hashTransformer,
                $licenseTransformer,
                $spdxIdTransformer
            );

            $relationshipTransformer = new RelationshipTransformer($spdxIdTransformer);

            $converter = new SpdxToCycloneDxConverter();
            $this->setTransformers($converter, $metadataTransformer, $packageTransformer, $relationshipTransformer, $spdxIdTransformer);

        } else {
            throw new \InvalidArgumentException("Unsupported converter class: {$converterClass}");
        }

        // Cache the instance
        $this->services[$converterClass] = $converter;

        return $converter;
    }

    /**
     * Get an existing service or create a new one.
     *
     * @template T
     * @param class-string<T> $class Service class name
     * @return T The service instance
     */
    private function getOrCreateService(string $class)
    {
        if (!isset($this->services[$class])) {
            $this->services[$class] = new $class();
        }

        return $this->services[$class];
    }

    /**
     * Set transformer dependencies on converters using reflection.
     *
     * This method uses reflection to inject dependencies into converters
     * that may have private or protected properties for transformers.
     *
     * @param AbstractConverter $converter The converter to modify
     * @param mixed ...$transformers The transformers to inject
     */
    private function setTransformers(AbstractConverter $converter, ...$transformers): void
    {
        $reflection = new \ReflectionClass($converter);

        foreach ($transformers as $transformer) {
            $transformerClass = get_class($transformer);
            $shortName = (new \ReflectionClass($transformerClass))->getShortName();

            // Convert from CamelCase to camelCase for property names
            $propertyName = lcfirst($shortName);

            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($converter, $transformer);
            }
        }
    }

    /**
     * Reset all cached service instances.
     *
     * Useful primarily for testing.
     */
    public function reset(): void
    {
        $this->services = [];
    }

    /**
     * Create a converter for a specific conversion path.
     *
     * @param string $conversionPath The specific conversion path ('spdx-to-cyclonedx' or 'cyclonedx-to-spdx')
     * @return ConverterInterface The appropriate converter
     * @throws \InvalidArgumentException If an invalid conversion path is provided
     */
    public function createConverterForPath(string $conversionPath): ConverterInterface
    {
        $map = [
            'spdx-to-cyclonedx' => [FormatEnum::FORMAT_SPDX, FormatEnum::FORMAT_CYCLONEDX],
            'cyclonedx-to-spdx' => [FormatEnum::FORMAT_CYCLONEDX, FormatEnum::FORMAT_SPDX]
        ];

        if (!isset($map[$conversionPath])) {
            throw new \InvalidArgumentException("Invalid conversion path: {$conversionPath}");
        }

        [$sourceFormat, $targetFormat] = $map[$conversionPath];
        return $this->createConverter($sourceFormat, $targetFormat);
    }
}