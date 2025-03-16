<?php

namespace SBOMinator\Transformatron\Enum;

/**
 * SPDX relationship type enumeration.
 *
 * Contains constants representing SPDX relationship types as defined in the SPDX specification.
 *
 * @see https://spdx.github.io/spdx-spec/v2.3/relationships-between-SPDX-elements/
 */
class RelationshipTypeEnum
{
    /**
     * Dependency relationship: indicates that a package depends on another package.
     *
     * @var string
     */
    public const RELATIONSHIP_DEPENDS_ON = 'DEPENDS_ON';

    /**
     * Describes relationship: indicates that a document describes a package.
     *
     * @var string
     */
    public const RELATIONSHIP_DESCRIBES = 'DESCRIBES';

    /**
     * Contains relationship: indicates that a package contains another package.
     *
     * @var string
     */
    public const RELATIONSHIP_CONTAINS = 'CONTAINS';

    /**
     * Generated from relationship: indicates that a package was generated from another package.
     *
     * @var string
     */
    public const RELATIONSHIP_GENERATED_FROM = 'GENERATED_FROM';

    /**
     * Dynamic link relationship: indicates that a package dynamically links to another package.
     *
     * @var string
     */
    public const RELATIONSHIP_DYNAMIC_LINK = 'DYNAMIC_LINK';

    /**
     * Static link relationship: indicates that a package statically links to another package.
     *
     * @var string
     */
    public const RELATIONSHIP_STATIC_LINK = 'STATIC_LINK';

    /**
     * Build dependency relationship: indicates that a package is a build dependency of another package.
     *
     * @var string
     */
    public const RELATIONSHIP_BUILD_DEPENDENCY_OF = 'BUILD_DEPENDENCY_OF';

    /**
     * Dev dependency relationship: indicates that a package is a development dependency of another package.
     *
     * @var string
     */
    public const RELATIONSHIP_DEV_DEPENDENCY_OF = 'DEV_DEPENDENCY_OF';

    /**
     * Runtime dependency relationship: indicates that a package is a runtime dependency of another package.
     *
     * @var string
     */
    public const RELATIONSHIP_RUNTIME_DEPENDENCY_OF = 'RUNTIME_DEPENDENCY_OF';

    /**
     * Optional dependency relationship: indicates that a package is an optional dependency of another package.
     *
     * @var string
     */
    public const RELATIONSHIP_OPTIONAL_DEPENDENCY_OF = 'OPTIONAL_DEPENDENCY_OF';
}