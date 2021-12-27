<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Location;

/**
 * @phpstan-type LocationArray array{start: int, end: int}
 */
class TestUtils
{
    /**
     * @phpstan-return LocationArray
     */
    public static function locationToArray(Location $loc): array
    {
        return [
            'start' => $loc->start,
            'end'   => $loc->end,
        ];
    }

    /**
     * @phpstan-return LocationArray
     */
    public static function locArray(int $start, int $end): array
    {
        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
