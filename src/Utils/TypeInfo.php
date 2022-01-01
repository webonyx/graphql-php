<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use function array_pop;
use function count;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;

/**
 * @phpstan-import-type InputTypeAlias from InputType
 */
class TypeInfo
{
    private Schema $schema;

    /** @var array<(OutputType&Type)|null> */
    private array $typeStack = [];

    /** @var array<(CompositeType&Type)|null> */
    private array $parentTypeStack = [];

    /** @var array<(InputType&Type)|null> */
    private array $inputTypeStack = [];

    /** @var array<FieldDefinition> */
    private array $fieldDefStack = [];

    /** @var array<mixed> */
    private array $defaultValueStack = [];

    private ?Directive $directive = null;

    private ?Argument $argument = null;

    /** @var mixed */
    private $enumValue;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Given root type scans through all fields to find nested types.
     *
     * Returns array where keys are for type name
     * and value contains corresponding type instance.
     *
     * Example output:
     * [
     *     'String' => $instanceOfStringType,
     *     'MyType' => $instanceOfMyType,
     *     ...
     * ]
     *
     * @param array<Type> $typeMap
     */
    public static function extractTypes(Type $type, array &$typeMap): void
    {
        /** @var (Type&WrappingType)|(Type&NamedType) $type */
        if ($type instanceof WrappingType) {
            self::extractTypes($type->getInnermostType(), $typeMap);

            return;
        }

        $name = $type->name;

        if (isset($typeMap[$name])) {
            Utils::invariant(
                $typeMap[$name] === $type,
                'Schema must contain unique named types but contains multiple types named "' . $type . '" '
                . '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
            );

            return;
        }

        $typeMap[$name] = $type;

        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $member) {
                self::extractTypes($member, $typeMap);
            }

            return;
        }

        if ($type instanceof InputObjectType) {
            foreach ($type->getFields() as $field) {
                self::extractTypes($field->getType(), $typeMap);
            }

            return;
        }

        if ($type instanceof ImplementingType) {
            foreach ($type->getInterfaces() as $interface) {
                self::extractTypes($interface, $typeMap);
            }
        }

        if (! ($type instanceof HasFieldsType)) {
            return;
        }

        foreach ($type->getFields() as $field) {
            foreach ($field->args as $arg) {
                self::extractTypes($arg->getType(), $typeMap);
            }

            self::extractTypes($field->getType(), $typeMap);
        }
    }

    /**
     * @param array<Type> $typeMap
     */
    public static function extractTypesFromDirectives(Directive $directive, array &$typeMap): void
    {
        foreach ($directive->args as $arg) {
            self::extractTypes($arg->getType(), $typeMap);
        }
    }

    /**
     * @return (Type&InputType)|null
     */
    public function getParentInputType(): ?InputType
    {
        return $this->inputTypeStack[count($this->inputTypeStack) - 2] ?? null;
    }

    public function getArgument(): ?Argument
    {
        return $this->argument;
    }

    /**
     * @return mixed
     */
    public function getEnumValue()
    {
        return $this->enumValue;
    }

    public function enter(Node $node): void
    {
        $schema = $this->schema;

        // Note: many of the types below are explicitly typed as "mixed" to drop
        // any assumptions of a valid schema to ensure runtime types are properly
        // checked before continuing since TypeInfo is used as part of validation
        // which occurs before guarantees of schema and document validity.
        switch (true) {
            case $node instanceof SelectionSetNode:
                $namedType = Type::getNamedType($this->getType());
                $this->parentTypeStack[] = Type::isCompositeType($namedType) ? $namedType : null;
                break;

            case $node instanceof FieldNode:
                $parentType = $this->getParentType();
                $fieldDef = null;
                if (null !== $parentType) {
                    $fieldDef = self::getFieldDefinition($schema, $parentType, $node);
                }

                $fieldType = null;
                if (null !== $fieldDef) {
                    $fieldType = $fieldDef->getType();
                }

                $this->fieldDefStack[] = $fieldDef;
                $this->typeStack[] = Type::isOutputType($fieldType) ? $fieldType : null;
                break;

            case $node instanceof DirectiveNode:
                $this->directive = $schema->getDirective($node->name->value);
                break;

            case $node instanceof OperationDefinitionNode:
                $type = null;
                if ('query' === $node->operation) {
                    $type = $schema->getQueryType();
                } elseif ('mutation' === $node->operation) {
                    $type = $schema->getMutationType();
                } elseif ('subscription' === $node->operation) {
                    $type = $schema->getSubscriptionType();
                }

                $this->typeStack[] = Type::isOutputType($type) ? $type : null;
                break;

            case $node instanceof InlineFragmentNode:
            case $node instanceof FragmentDefinitionNode:
                $typeConditionNode = $node->typeCondition;
                $outputType = null === $typeConditionNode
                    ? Type::getNamedType($this->getType())
                    : self::typeFromAST(
                        $schema,
                        $typeConditionNode
                    );
                $this->typeStack[] = Type::isOutputType($outputType) ? $outputType : null;
                break;

            case $node instanceof VariableDefinitionNode:
                $inputType = self::typeFromAST($schema, $node->type);
                $this->inputTypeStack[] = Type::isInputType($inputType) ? $inputType : null; // push
                break;

            case $node instanceof ArgumentNode:
                $fieldOrDirective = $this->getDirective() ?? $this->getFieldDef();
                $argDef = null;
                $argType = null;
                if (null !== $fieldOrDirective) {
                    foreach ($fieldOrDirective->args as $arg) {
                        if ($arg->name !== $node->name->value) {
                            continue;
                        }

                        $argDef = $arg;
                        $argType = $arg->getType();
                    }
                }

                $this->argument = $argDef;
                $this->defaultValueStack[] = null !== $argDef && $argDef->defaultValueExists()
                    ? $argDef->defaultValue
                    : Utils::undefined();
                $this->inputTypeStack[] = Type::isInputType($argType) ? $argType : null;
                break;

            case $node instanceof ListValueNode:
                $type = $this->getInputType();
                $listType = $type instanceof NonNull
                    ? $type->getWrappedType()
                    : $type;
                $itemType = $listType instanceof ListOfType
                    ? $listType->getWrappedType()
                    : $listType;
                // List positions never have a default value.
                $this->defaultValueStack[] = Utils::undefined();
                $this->inputTypeStack[] = Type::isInputType($itemType) ? $itemType : null;
                break;

            case $node instanceof ObjectFieldNode:
                $objectType = Type::getNamedType($this->getInputType());
                $inputField = null;
                $inputFieldType = null;
                if ($objectType instanceof InputObjectType) {
                    $tmp = $objectType->getFields();
                    $inputField = $tmp[$node->name->value] ?? null;
                    $inputFieldType = null === $inputField
                        ? null
                        : $inputField->getType();
                }

                $this->defaultValueStack[] = null !== $inputField && $inputField->defaultValueExists()
                    ? $inputField->defaultValue
                    : Utils::undefined();
                $this->inputTypeStack[] = Type::isInputType($inputFieldType)
                    ? $inputFieldType
                    : null;
                break;

            case $node instanceof EnumValueNode:
                $enumType = Type::getNamedType($this->getInputType());
                $enumValue = null;
                if ($enumType instanceof EnumType) {
                    $this->enumValue = $enumType->getValue($node->value);
                }

                $this->enumValue = $enumValue;
                break;
        }
    }

    /**
     * @return (Type & OutputType) | null
     */
    public function getType(): ?OutputType
    {
        return $this->typeStack[count($this->typeStack) - 1] ?? null;
    }

    /**
     * @return (CompositeType & Type) | null
     */
    public function getParentType(): ?CompositeType
    {
        return $this->parentTypeStack[count($this->parentTypeStack) - 1] ?? null;
    }

    /**
     * Not exactly the same as the executor's definition of getFieldDef, in this
     * statically evaluated environment we do not always have an Object type,
     * and need to handle Interface and Union types.
     */
    private static function getFieldDefinition(Schema $schema, Type $parentType, FieldNode $fieldNode): ?FieldDefinition
    {
        $name = $fieldNode->name->value;
        $schemaMeta = Introspection::schemaMetaFieldDef();
        if ($name === $schemaMeta->name && $schema->getQueryType() === $parentType) {
            return $schemaMeta;
        }

        $typeMeta = Introspection::typeMetaFieldDef();
        if ($name === $typeMeta->name && $schema->getQueryType() === $parentType) {
            return $typeMeta;
        }

        $typeNameMeta = Introspection::typeNameMetaFieldDef();
        if ($name === $typeNameMeta->name && $parentType instanceof CompositeType) {
            return $typeNameMeta;
        }

        if (
            $parentType instanceof ObjectType
            || $parentType instanceof InterfaceType
        ) {
            return $parentType->findField($name);
        }

        return null;
    }

    /**
     * @param NamedTypeNode|ListTypeNode|NonNullTypeNode $inputTypeNode
     *
     * @throws InvariantViolation
     */
    public static function typeFromAST(Schema $schema, Node $inputTypeNode): ?Type
    {
        return AST::typeFromAST($schema, $inputTypeNode);
    }

    public function getDirective(): ?Directive
    {
        return $this->directive;
    }

    public function getFieldDef(): ?FieldDefinition
    {
        return $this->fieldDefStack[count($this->fieldDefStack) - 1] ?? null;
    }

    /**
     * @return mixed any value is possible
     */
    public function getDefaultValue()
    {
        return $this->defaultValueStack[count($this->defaultValueStack) - 1] ?? null;
    }

    /**
     * @return (InputType&Type)|null
     */
    public function getInputType(): ?InputType
    {
        return $this->inputTypeStack[count($this->inputTypeStack) - 1] ?? null;
    }

    public function leave(Node $node): void
    {
        switch (true) {
            case $node instanceof SelectionSetNode:
                array_pop($this->parentTypeStack);
                break;

            case $node instanceof FieldNode:
                array_pop($this->fieldDefStack);
                array_pop($this->typeStack);
                break;

            case $node instanceof DirectiveNode:
                $this->directive = null;
                break;

            case $node instanceof OperationDefinitionNode:
            case $node instanceof InlineFragmentNode:
            case $node instanceof FragmentDefinitionNode:
                array_pop($this->typeStack);
                break;
            case $node instanceof VariableDefinitionNode:
                array_pop($this->inputTypeStack);
                break;
            case $node instanceof ArgumentNode:
                $this->argument = null;
                array_pop($this->defaultValueStack);
                array_pop($this->inputTypeStack);
                break;
            case $node instanceof ListValueNode:
            case $node instanceof ObjectFieldNode:
                array_pop($this->defaultValueStack);
                array_pop($this->inputTypeStack);
                break;
            case $node instanceof EnumValueNode:
                $this->enumValue = null;
                break;
        }
    }
}
