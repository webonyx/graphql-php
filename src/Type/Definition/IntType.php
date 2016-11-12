<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\Value;
use GraphQL\Utils;

/**
 * Class IntType
 * @package GraphQL\Type\Definition
 */
class IntType extends ScalarType
{
    // As per the GraphQL Spec, Integers are only treated as valid when a valid
    // 32-bit signed integer, providing the broadest support across platforms.
    //
    // n.b. JavaScript's integers are safe between -(2^53 - 1) and 2^53 - 1 because
    // they are internally represented as IEEE 754 doubles.
    const MAX_INT = 2147483647;
    const MIN_INT = -2147483648;

    /**
     * @var string
     */
    public $name = Type::INT;

    /**
     * @var string
     */
    public $description =
'The `Int` scalar type represents non-fractional signed whole numeric
values. Int can represent values between -(2^31) and 2^31 - 1. ';

    /**
     * @param mixed $value
     * @return int|null
     */
    public function serialize($value)
    {
        return $this->coerceInt($value);
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    public function parseValue($value)
    {
        return $this->coerceInt($value);
    }

    /**
     * @param $value
     * @return int|null
     */
    private function coerceInt($value)
    {
        if ($value === '') {
            throw new InvariantViolation(
                'Int cannot represent non 32-bit signed integer value: (empty string)'
            );
        }
        if (false === $value || true === $value) {
            return (int) $value;
        }
        if (is_numeric($value) && $value <= self::MAX_INT && $value >= self::MIN_INT) {
            return (int) $value;
        }
        throw new InvariantViolation(
            'Int cannot represent non 32-bit signed integer value: ' . Utils::printSafe($value)
        );
    }

    /**
     * @param $ast
     * @return int|null
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof IntValue) {
            $val = (int) $ast->getValue();
            if ($ast->getValue() === (string) $val && self::MIN_INT <= $val && $val <= self::MAX_INT) {
                return $val;
            }
        }
        return null;
    }
}
