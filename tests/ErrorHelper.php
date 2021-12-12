<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Language\SourceLocation;

use function array_map;
use function count;

/**
 * @phpstan-type ErrorArray array{
 *     message: string,
 *     locations?: array<int, array{line: int, column: int}>
 * }
 */
class ErrorHelper
{
    /**
     * @param array<SourceLocation> $locations
     *
     * @phpstan-return ErrorArray
     */
    public static function create(string $error, array $locations = []): array
    {
        $formatted = ['message' => $error];

        if (count($locations) > 0) {
            $formatted['locations'] = array_map(
                static fn (SourceLocation $loc): array => $loc->toArray(),
                $locations
            );
        }

        return $formatted;
    }
}
