<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use function sprintf;

class VariablesInAllowedPosition extends ValidationRule
{
    /**
     * A map from variable names to their definition nodes.
     *
     * @var VariableDefinitionNode[]
     */
    public $varDefMap;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () : void {
                    $this->varDefMap = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) : void {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node         = $usage['node'];
                        $type         = $usage['type'];
                        $defaultValue = $usage['defaultValue'];
                        $varName      = $node->name->value;
                        $varDef       = $this->varDefMap[$varName] ?? null;

                        if ($varDef === null || $type === null) {
                            continue;
                        }

                        // A var type is allowed if it is the same or more strict (e.g. is
                        // a subtype of) than the expected type. It can be more strict if
                        // the variable type is non-null when the expected type is nullable.
                        // If both are list types, the variable item type can be more strict
                        // than the expected item type (contravariant).
                        $schema  = $context->getSchema();
                        $varType = TypeInfo::typeFromAST($schema, $varDef->type);

                        if (! $varType || $this->allowedVariableUsage($schema, $varType, $varDef->defaultValue, $type, $defaultValue)) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::badVarPosMessage($varName, $varType, $type),
                            [$varDef, $node]
                        ));
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION  => function (VariableDefinitionNode $varDefNode) : void {
                $this->varDefMap[$varDefNode->variable->name->value] = $varDefNode;
            },
        ];
    }

    /**
     * A var type is allowed if it is the same or more strict than the expected
     * type. It can be more strict if the variable type is non-null when the
     * expected type is nullable. If both are list types, the variable item type can
     * be more strict than the expected item type.
     */
    public static function badVarPosMessage($varName, $varType, $expectedType)
    {
        return sprintf(
            'Variable "$%s" of type "%s" used in position expecting type "%s".',
            $varName,
            $varType,
            $expectedType
        );
    }

    /**
     * Returns true if the variable is allowed in the location it was found,
     * which includes considering if default values exist for either the variable
     * or the location at which it is located.
     *
     * @param ValueNode|null $varDefaultValue
     * @param mixed          $locationDefaultValue
     */
    private function allowedVariableUsage(Schema $schema, Type $varType, $varDefaultValue, Type $locationType, $locationDefaultValue) : bool
    {
        if ($locationType instanceof NonNull && ! $varType instanceof NonNull) {
            $hasNonNullVariableDefaultValue = $varDefaultValue && ! $varDefaultValue instanceof NullValueNode;
            $hasLocationDefaultValue        = ! Utils::isInvalid($locationDefaultValue);
            if (! $hasNonNullVariableDefaultValue && ! $hasLocationDefaultValue) {
                return false;
            }
            $nullableLocationType = $locationType->getWrappedType();

            return TypeComparators::isTypeSubTypeOf($schema, $varType, $nullableLocationType);
        }

        return TypeComparators::isTypeSubTypeOf($schema, $varType, $locationType);
    }
}
