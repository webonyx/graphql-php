<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\UserError;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Utils;

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
        return $this->coerceFloat($value);
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    public function parseValue($value)
    {
        return $this->coerceFloat($value);
    }

    /**
     * @param $value
     * @return float|null
     */
    private function coerceFloat($value)
    {
        if ($value === '') {
            throw new UserError(
                'Float cannot represent non numeric value: (empty string)'
            );
        }
        if (is_numeric($value) || $value === true || $value === false) {
            return (float)$value;
        }
        throw new UserError(sprintf('Float cannot represent non numeric value: %s', Utils::printSafe($value)));
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
