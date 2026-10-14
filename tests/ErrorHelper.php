<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Language\SourceLocation;

/**
 * @phpstan-type ErrorArray array{
 *     message: string,
 *     locations?: array<int, array{line: int, column: int}>
 * }
 */
final class ErrorHelper
{
    /**
     * @param array<SourceLocation> $locations
     *
     * @phpstan-return ErrorArray
     */
    public static function create(string $error, array $locations = []): array
    {
        $formatted = ['message' => $error];

        if ($locations !== []) {
            $formatted['locations'] = array_map(
                static fn (SourceLocation $loc): array => $loc->toArray(),
                $locations
            );
        }

        return $formatted;
    }
}
