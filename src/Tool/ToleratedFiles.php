<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tool;

/**
 * Filenames that may appear at any directory root without counting as
 * project content — tolerated exceptions to cleanliness rules.
 *
 * Shared by cleanliness validators across Scafera packages so the
 * tolerated set is maintained in one place instead of being duplicated
 * per validator.
 *
 * The `$scope` parameter reserves space for future config-driven
 * additions keyed by scope name — today every scope returns the defaults.
 */
final class ToleratedFiles
{
    /** Default tolerated names that apply to every scope. */
    public const DEFAULTS = [
        '.gitkeep',
        '.gitignore',
    ];

    /**
     * Returns the tolerated filenames for a given scope.
     *
     * @return list<string>
     */
    public static function names(string $scope = 'default'): array
    {
        // TODO: when config support lands, merge DEFAULTS with
        // scope-specific additions declared in config/config.yaml.
        return self::DEFAULTS;
    }
}
