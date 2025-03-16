<?php

namespace SBOMinator\Transformatron\Enum;

/**
 * SBOM version enumeration.
 *
 * Contains constants representing supported SBOM format versions.
 */
class VersionEnum
{
    /**
     * SPDX version.
     *
     * @var string
     */
    public const SPDX_VERSION = 'SPDX-2.3';

    /**
     * CycloneDX version.
     *
     * @var string
     */
    public const CYCLONEDX_VERSION = '1.4';
}