<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

use GraphQL\Utils\Utils;
use JsonSerializable;

use function count;
use function get_object_vars;
use function is_array;
use function is_scalar;
use function json_encode;

/**
 * type Node = NameNode
 * | DocumentNode
 * | OperationDefinitionNode
 * | VariableDefinitionNode
 * | VariableNode
 * | SelectionSetNode
 * | FieldNode
 * | ArgumentNode
 * | FragmentSpreadNode
 * | InlineFragmentNode
 * | FragmentDefinitionNode
 * | IntValueNode
 * | FloatValueNode
 * | StringValueNode
 * | BooleanValueNode
 * | EnumValueNode
 * | ListValueNode
 * | ObjectValueNode
 * | ObjectFieldNode
 * | DirectiveNode
 * | ListTypeNode
 * | NonNullTypeNode
 */
abstract class Node implements JsonSerializable
{
    public ?Location $loc = null;

    public string $kind;

    /**
     * @param array<string, mixed> $vars
     */
    public function __construct(array $vars)
    {
        if (count($vars) === 0) {
            return;
        }

        Utils::assign($this, $vars);
    }

    /**
     * Returns a clone of this instance and all its children, except Location $loc.
     *
     * @return static
     */
    public function cloneDeep(): self
    {
        return static::cloneValue($this);
    }

    /**
     * @phpstan-param TCloneable $value
     *
     * @phpstan-return TCloneable
     *
     * @template TNode of Node
     * @template TCloneable of TNode|NodeList<TNode>|Location|string
     */
    protected static function cloneValue($value)
    {
        if ($value instanceof self) {
            $cloned = clone $value;
            foreach (get_object_vars($cloned) as $prop => $propValue) {
                $cloned->{$prop} = static::cloneValue($propValue);
            }

            return $cloned;
        }

        if ($value instanceof NodeList) {
            return $value->cloneDeep();
        }

        return $value;
    }

    public function __toString(): string
    {
        return json_encode($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::recursiveToArray($this);
    }

    /**
     * @return array<string, mixed>
     */
    private static function recursiveToArray(Node $node): array
    {
        $result = [
            'kind' => $node->kind,
        ];

        if ($node->loc !== null) {
            $result['loc'] = [
                'start' => $node->loc->start,
                'end'   => $node->loc->end,
            ];
        }

        foreach (get_object_vars($node) as $prop => $propValue) {
            if (isset($result[$prop])) {
                continue;
            }

            if ($propValue === null) {
                continue;
            }

            if (is_array($propValue) || $propValue instanceof NodeList) {
                $converted = [];
                foreach ($propValue as $item) {
                    $converted[] = $item instanceof Node
                        ? self::recursiveToArray($item)
                        : (array) $item;
                }
            } elseif ($propValue instanceof Node) {
                $converted = self::recursiveToArray($propValue);
            } elseif (is_scalar($propValue)) {
                $converted = $propValue;
            } else {
                $converted = null;
            }

            $result[$prop] = $converted;
        }

        return $result;
    }
}
