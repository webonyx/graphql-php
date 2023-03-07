<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\Utils;
use GraphQL\Validator\QueryValidationContext;

class VariablesInAllowedPosition extends ValidationRule
{
    /**
     * A map from variable names to their definition nodes.
     *
     * @var array<string, VariableDefinitionNode>
     */
    protected array $varDefMap;

    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function (): void {
                    $this->varDefMap = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context): void {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $type = $usage['type'];
                        $defaultValue = $usage['defaultValue'];
                        $varName = $node->name->value;
                        $varDef = $this->varDefMap[$varName] ?? null;

                        if ($varDef === null || $type === null) {
                            continue;
                        }

                        // A var type is allowed if it is the same or more strict (e.g. is
                        // a subtype of) than the expected type. It can be more strict if
                        // the variable type is non-null when the expected type is nullable.
                        // If both are list types, the variable item type can be more strict
                        // than the expected item type (contravariant).
                        $schema = $context->getSchema();
                        $varType = AST::typeFromAST([$schema, 'getType'], $varDef->type);

                        if ($varType !== null && ! $this->allowedVariableUsage($schema, $varType, $varDef->defaultValue, $type, $defaultValue)) {
                            $context->reportError(new Error(
                                static::badVarPosMessage($varName, $varType->toString(), $type->toString()),
                                [$varDef, $node]
                            ));
                        }
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $varDefNode): void {
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
    public static function badVarPosMessage(string $varName, string $varType, string $expectedType): string
    {
        return "Variable \"\${$varName}\" of type \"{$varType}\" used in position expecting type \"{$expectedType}\".";
    }

    /**
     * Returns true if the variable is allowed in the location it was found,
     * which includes considering if default values exist for either the variable
     * or the location at which it is located.
     *
     * @param ValueNode|null $varDefaultValue
     * @param mixed          $locationDefaultValue
     *
     * @throws InvariantViolation
     */
    protected function allowedVariableUsage(Schema $schema, Type $varType, $varDefaultValue, Type $locationType, $locationDefaultValue): bool
    {
        if ($locationType instanceof NonNull && ! $varType instanceof NonNull) {
            $hasNonNullVariableDefaultValue = $varDefaultValue !== null && ! $varDefaultValue instanceof NullValueNode;
            $hasLocationDefaultValue = Utils::undefined() !== $locationDefaultValue;
            if (! $hasNonNullVariableDefaultValue && ! $hasLocationDefaultValue) {
                return false;
            }

            $nullableLocationType = $locationType->getWrappedType();

            return TypeComparators::isTypeSubTypeOf($schema, $varType, $nullableLocationType);
        }

        return TypeComparators::isTypeSubTypeOf($schema, $varType, $locationType);
    }
}
