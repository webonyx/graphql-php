<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Utils\Utils;

/**
 * Class FloatType
 * @package GraphQL\Type\Definition
 */
class FloatType extends ScalarType
{
    /**
     * @var string
     */
    public $name = Type::FLOAT;

    /**
     * @var string
     */
    public $description =
'The `Float` scalar type represents signed double-precision fractional
values as specified by
[IEEE 754](http://en.wikipedia.org/wiki/IEEE_floating_point). ';

    /**
     * @param mixed $value
     * @return float|null
     */
    public function serialize($value)
    {
        if (is_numeric($value) || $value === true || $value === false) {
            return (float) $value;
        }

        if ($value === '') {
            $err = 'Float cannot represent non numeric value: (empty string)';
        } else {
            $err = sprintf('Float cannot represent non numeric value: %s', Utils::printSafe($value));
        }
        throw new InvariantViolation($err);
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    public function parseValue($value)
    {
        return (is_numeric($value) && !is_string($value)) ? (float) $value : null;
    }

    /**
     * @param $ast
     * @return float|null
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof FloatValueNode || $ast instanceof IntValueNode) {
            return (float) $ast->value;
        }
        return null;
    }
}
