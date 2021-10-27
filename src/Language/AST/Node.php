<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

use GraphQL\Utils\Utils;

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
abstract class Node
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
     * Performs a deep clone of this Node and all its children, except Location $loc.
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
            $cloned = clone $value;
            foreach ($value as $key => $listValue) {
                $cloned[$key] = static::cloneValue($listValue);
            }

            return $cloned;
        }

        return $value;
    }

    public function __toString(): string
    {
        $tmp = $this->toArray(true);

        return (string) json_encode($tmp);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $recursive = false): array
    {
        if ($recursive) {
            return $this->recursiveToArray($this);
        }

        $tmp = (array) $this;

        if ($this->loc !== null) {
            $tmp['loc'] = [
                'start' => $this->loc->start,
                'end'   => $this->loc->end,
            ];
        }

        return $tmp;
    }

    /**
     * @return array<string, mixed>
     */
    private function recursiveToArray(Node $node): array
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
                $tmp = [];
                foreach ($propValue as $tmp1) {
                    $tmp[] = $tmp1 instanceof Node
                        ? $this->recursiveToArray($tmp1)
                        : (array) $tmp1;
                }
            } elseif ($propValue instanceof Node) {
                $tmp = $this->recursiveToArray($propValue);
            } elseif (is_scalar($propValue) || $propValue === null) {
                $tmp = $propValue;
            } else {
                $tmp = null;
            }

            $result[$prop] = $tmp;
        }

        return $result;
    }
}
