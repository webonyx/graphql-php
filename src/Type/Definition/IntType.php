<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Utils\Utils;

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
        if ($value === '') {
            throw new InvariantViolation('Int cannot represent non 32-bit signed integer value: (empty string)');
        }
        if (false === $value || true === $value) {
            return (int) $value;
        }
        if (!is_numeric($value) || $value > self::MAX_INT || $value < self::MIN_INT) {
            throw new InvariantViolation(sprintf(
                'Int cannot represent non 32-bit signed integer value: %s',
                Utils::printSafe($value)
            ));
        }
        $num = (float) $value;

        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        // Examples: 1.0 == 1; 1.1 != 1, etc
        if ($num != (int) $value) {
            // Additionally account for scientific notation (i.e. 1e3), because (float)'1e3' is 1000, but (int)'1e3' is 1
            $trimmed = floor($num);
            if ($trimmed !== $num) {
                throw new InvariantViolation(sprintf(
                    'Int cannot represent non-integer value: %s',
                    Utils::printSafe($value)
                ));
            }
        }
        return (int) $value;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    public function parseValue($value)
    {
        // Below is a fix against PHP bug where (in some combinations of OSs and versions)
        // boundary values are treated as "double" vs "integer" and failing is_int() check
        $isInt = is_int($value) || $value === self::MIN_INT || $value === self::MAX_INT;
        return $isInt && $value <= self::MAX_INT && $value >= self::MIN_INT ? $value : null;
    }

    /**
     * @param $ast
     * @return int|null
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof IntValueNode) {
            $val = (int) $ast->value;
            if ($ast->value === (string) $val && self::MIN_INT <= $val && $val <= self::MAX_INT) {
                return $val;
            }
        }
        return null;
    }
}
