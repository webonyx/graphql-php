<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;

use function get_object_vars;
use function is_array;
use function is_scalar;

class TestUtils
{
    /**
     * @return mixed[]
     */
    public static function nodeToArray(Node $node): array
    {
        $result = [
            'kind' => $node->kind,
            'loc'  => self::locationToArray($node->loc),
        ];

        foreach (get_object_vars($node) as $prop => $propValue) {
            if (isset($result[$prop])) {
                continue;
            }

            if (is_array($propValue) || $propValue instanceof NodeList) {
                $tmp = [];
                foreach ($propValue as $tmp1) {
                    $tmp[] = $tmp1 instanceof Node
                        ? self::nodeToArray($tmp1)
                        : (array) $tmp1;
                }
            } elseif ($propValue instanceof Node) {
                $tmp = self::nodeToArray($propValue);
            } elseif (is_scalar($propValue) || $propValue === null) {
                $tmp = $propValue;
            } else {
                $tmp = null;
            }

            $result[$prop] = $tmp;
        }

        return $result;
    }

    /**
     * @return int[]
     */
    public static function locationToArray(Location $loc): array
    {
        return [
            'start' => $loc->start,
            'end'   => $loc->end,
        ];
    }

    /**
     * @return int[]
     */
    public static function locArray(int $start, int $end): array
    {
        return ['start' => $start, 'end' => $end];
    }
}
