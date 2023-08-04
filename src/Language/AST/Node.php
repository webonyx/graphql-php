<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

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
 * | NonNullTypeNode.
 *
 * @see \GraphQL\Tests\Language\AST\NodeTest
 */
abstract class Node implements \JsonSerializable
{
    public ?Location $loc = null;

    public string $kind;

    /** @param array<string, mixed> $vars */
    public function __construct(array $vars)
    {
        Utils::assign($this, $vars);
    }

    /**
     * Returns a clone of this instance and all its children, except Location $loc.
     *
     * @throws \JsonException
     * @throws InvariantViolation
     *
     * @return static
     */
    public function cloneDeep(): self
    {
        return static::cloneValue($this);
    }

    /**
     * @template TNode of Node
     * @template TCloneable of TNode|NodeList<TNode>|Location|string
     *
     * @phpstan-param TCloneable $value
     *
     * @phpstan-return TCloneable
     *
     * @throws \JsonException
     * @throws InvariantViolation
     */
    protected static function cloneValue($value)
    {
        if ($value instanceof self) {
            $cloned = clone $value;
            foreach (\get_object_vars($cloned) as $prop => $propValue) {
                $cloned->{$prop} = static::cloneValue($propValue);
            }

            return $cloned;
        }

        if ($value instanceof NodeList) {
            return $value->cloneDeep();
        }

        return $value;
    }

    /** @throws \JsonException */
    public function __toString(): string
    {
        return \json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * Improves upon the default serialization by:
     * - excluding null values
     * - excluding large reference values such as @see Location::$source.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return self::recursiveToArray($this);
    }

    /** @return array<string, mixed> */
    private static function recursiveToArray(Node $node): array
    {
        $result = [];

        foreach (\get_object_vars($node) as $prop => $propValue) {
            if ($propValue === null) {
                continue;
            }

            if ($propValue instanceof NodeList) {
                $converted = [];
                foreach ($propValue as $item) {
                    $converted[] = self::recursiveToArray($item);
                }
            } elseif ($propValue instanceof Node) {
                $converted = self::recursiveToArray($propValue);
            } elseif ($propValue instanceof Location) {
                $converted = $propValue->toArray();
            } else {
                $converted = $propValue;
            }

            $result[$prop] = $converted;
        }

        return $result;
    }
}
