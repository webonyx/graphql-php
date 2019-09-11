<?php

declare(strict_types=1);

namespace GraphQL\Experimental\Executor;

use GraphQL\Language\AST\ValueNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;

/**
 * @internal
 */
interface Runtime
{
    /**
     * @param ScalarType|EnumType|InputObjectType|ListOfType|NonNull $type
     */
    public function evaluate(ValueNode $valueNode, InputType $type);

    public function addError($error);
}
