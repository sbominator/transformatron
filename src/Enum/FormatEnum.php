<?php

namespace SBOMinator\Transformatron\Enum;

/**
 * SBOM format enumeration.
 *
 * Contains constants representing supported SBOM formats.
 */
class FormatEnum
{
    /**
     * SPDX format.
     *
     * @var string
     */
    public const FORMAT_SPDX = 'SPDX';

    /**
     * CycloneDX format.
     *
     * @var string
     */
    public const FORMAT_CYCLONEDX = 'CycloneDX';
}