<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

/**
 * Scalar Type Definition
 *
 * The leaf values of any request and input values to arguments are
 * Scalars (or Enums) and are defined with a name and a series of coercion
 * functions used to ensure validity.
 *
 * Example:
 *
 *     var OddType = new GraphQLScalarType({
 *       name: 'Odd',
 *       serialize(value) {
 *         return value % 2 === 1 ? value : null;
 *       }
 *     });
 *
 */
abstract class ScalarType extends Type implements OutputType, InputType
{
    protected function __construct()
    {
        Utils::invariant($this->name, 'Type must be named.');
    }

    abstract public function serialize($value);

    abstract public function parseValue($value);

    abstract public function parseLiteral(/* GraphQL\Language\AST\Value */$valueAST);
}
