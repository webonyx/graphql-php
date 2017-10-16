<?php
namespace GraphQL\Tests\Language;


use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;

class TestUtils
{
    /**
     * @param Node $node
     * @return array
     */
    public static function nodeToArray(Node $node)
    {
        $result = [
            'kind' => $node->kind,
            'loc' => self::locationToArray($node->loc)
        ];

        foreach (get_object_vars($node) as $prop => $propValue) {
            if (isset($result[$prop]))
                continue;

            if (is_array($propValue) || $propValue instanceof NodeList) {
                $tmp = [];
                foreach ($propValue as $tmp1) {
                    $tmp[] = $tmp1 instanceof Node ? self::nodeToArray($tmp1) : (array) $tmp1;
                }
            } else if ($propValue instanceof Node) {
                $tmp = self::nodeToArray($propValue);
            } else if (is_scalar($propValue) || null === $propValue) {
                $tmp = $propValue;
            } else {
                $tmp = null;
            }

            $result[$prop] = $tmp;
        }
        return $result;
    }

    /**
     * @param Location $loc
     * @return array
     */
    public static function locationToArray(Location $loc)
    {
        return [
            'start' => $loc->start,
            'end' => $loc->end
        ];
    }

    /**
     * @param $start
     * @param $end
     * @return array
     */
    public static function locArray($start, $end)
    {
        return ['start' => $start, 'end' => $end];
    }
}
