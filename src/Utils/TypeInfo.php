<?php

declare(strict_types=1);

namespace GraphQL\Utils;

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
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use function array_map;
use function array_merge;
use function array_pop;
use function count;
use function is_array;
use function sprintf;

class TypeInfo
{
    /** @var Schema */
    private $schema;

    /** @var array<(OutputType&Type)|null> */
    private $typeStack;

    /** @var array<(CompositeType&Type)|null> */
    private $parentTypeStack;

    /** @var array<(InputType&Type)|null> */
    private $inputTypeStack;

    /** @var array<FieldDefinition> */
    private $fieldDefStack;

    /** @var array<mixed> */
    private $defaultValueStack;

    /** @var Directive|null */
    private $directive;

    /** @var FieldArgument|null */
    private $argument;

    /** @var mixed */
    private $enumValue;

    /**
     * @param Type|null $initialType
     */
    public function __construct(Schema $schema, $initialType = null)
    {
        $this->schema            = $schema;
        $this->typeStack         = [];
        $this->parentTypeStack   = [];
        $this->inputTypeStack    = [];
        $this->fieldDefStack     = [];
        $this->defaultValueStack = [];

        if ($initialType === null) {
            return;
        }

        if (Type::isInputType($initialType)) {
            $this->inputTypeStack[] = $initialType;
        }
        if (Type::isCompositeType($initialType)) {
            $this->parentTypeStack[] = $initialType;
        }
        if (! Type::isOutputType($initialType)) {
            return;
        }

        $this->typeStack[] = $initialType;
    }

    /**
     * @deprecated moved to GraphQL\Utils\TypeComparators
     *
     * @codeCoverageIgnore
     */
    public static function isEqualType(Type $typeA, Type $typeB) : bool
    {
        return TypeComparators::isEqualType($typeA, $typeB);
    }

    /**
     * @deprecated moved to GraphQL\Utils\TypeComparators
     *
     * @codeCoverageIgnore
     */
    public static function isTypeSubTypeOf(Schema $schema, Type $maybeSubType, Type $superType)
    {
        return TypeComparators::isTypeSubTypeOf($schema, $maybeSubType, $superType);
    }

    /**
     * @deprecated moved to GraphQL\Utils\TypeComparators
     *
     * @codeCoverageIgnore
     */
    public static function doTypesOverlap(Schema $schema, CompositeType $typeA, CompositeType $typeB)
    {
        return TypeComparators::doTypesOverlap($schema, $typeA, $typeB);
    }

    /**
     * Given root type scans through all fields to find nested types. Returns array where keys are for type name
     * and value contains corresponding type instance.
     *
     * Example output:
     * [
     *     'String' => $instanceOfStringType,
     *     'MyType' => $instanceOfMyType,
     *     ...
     * ]
     *
     * @param Type|null   $type
     * @param Type[]|null $typeMap
     *
     * @return Type[]|null
     */
    public static function extractTypes($type, ?array $typeMap = null)
    {
        if (! $typeMap) {
            $typeMap = [];
        }
        if (! $type) {
            return $typeMap;
        }

        if ($type instanceof WrappingType) {
            return self::extractTypes($type->getWrappedType(true), $typeMap);
        }

        if (! $type instanceof Type) {
            // Preserve these invalid types in map (at numeric index) to make them
            // detectable during $schema->validate()
            $i            = 0;
            $alreadyInMap = false;
            while (isset($typeMap[$i])) {
                $alreadyInMap = $alreadyInMap || $typeMap[$i] === $type;
                $i++;
            }
            if (! $alreadyInMap) {
                $typeMap[$i] = $type;
            }

            return $typeMap;
        }

        if (isset($typeMap[$type->name])) {
            Utils::invariant(
                $typeMap[$type->name] === $type,
                sprintf('Schema must contain unique named types but contains multiple types named "%s" ', $type) .
                '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
            );

            return $typeMap;
        }
        $typeMap[$type->name] = $type;

        $nestedTypes = [];

        if ($type instanceof UnionType) {
            $nestedTypes = $type->getTypes();
        }
        if ($type instanceof ImplementingType) {
            $nestedTypes = array_merge($nestedTypes, $type->getInterfaces());
        }

        if ($type instanceof HasFieldsType) {
            foreach ($type->getFields() as $field) {
                if (count($field->args) > 0) {
                    $fieldArgTypes = array_map(
                        static function (FieldArgument $arg) : Type {
                            return $arg->getType();
                        },
                        $field->args
                    );

                    $nestedTypes = array_merge($nestedTypes, $fieldArgTypes);
                }
                $nestedTypes[] = $field->getType();
            }
        }
        if ($type instanceof InputObjectType) {
            foreach ($type->getFields() as $field) {
                $nestedTypes[] = $field->getType();
            }
        }
        foreach ($nestedTypes as $nestedType) {
            $typeMap = self::extractTypes($nestedType, $typeMap);
        }

        return $typeMap;
    }

    /**
     * @param Type[] $typeMap
     *
     * @return Type[]
     */
    public static function extractTypesFromDirectives(Directive $directive, array $typeMap = [])
    {
        if (is_array($directive->args)) {
            foreach ($directive->args as $arg) {
                $typeMap = self::extractTypes($arg->getType(), $typeMap);
            }
        }

        return $typeMap;
    }

    /**
     * @return (Type&InputType)|null
     */
    public function getParentInputType() : ?InputType
    {
        return $this->inputTypeStack[count($this->inputTypeStack) - 2] ?? null;
    }

    public function getArgument() : ?FieldArgument
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

    public function enter(Node $node)
    {
        $schema = $this->schema;

        // Note: many of the types below are explicitly typed as "mixed" to drop
        // any assumptions of a valid schema to ensure runtime types are properly
        // checked before continuing since TypeInfo is used as part of validation
        // which occurs before guarantees of schema and document validity.
        switch (true) {
            case $node instanceof SelectionSetNode:
                $namedType               = Type::getNamedType($this->getType());
                $this->parentTypeStack[] = Type::isCompositeType($namedType) ? $namedType : null;
                break;

            case $node instanceof FieldNode:
                $parentType = $this->getParentType();
                $fieldDef   = null;
                if ($parentType) {
                    $fieldDef = self::getFieldDefinition($schema, $parentType, $node);
                }
                $fieldType = null;
                if ($fieldDef) {
                    $fieldType = $fieldDef->getType();
                }
                $this->fieldDefStack[] = $fieldDef;
                $this->typeStack[]     = Type::isOutputType($fieldType) ? $fieldType : null;
                break;

            case $node instanceof DirectiveNode:
                $this->directive = $schema->getDirective($node->name->value);
                break;

            case $node instanceof OperationDefinitionNode:
                $type = null;
                if ($node->operation === 'query') {
                    $type = $schema->getQueryType();
                } elseif ($node->operation === 'mutation') {
                    $type = $schema->getMutationType();
                } elseif ($node->operation === 'subscription') {
                    $type = $schema->getSubscriptionType();
                }
                $this->typeStack[] = Type::isOutputType($type) ? $type : null;
                break;

            case $node instanceof InlineFragmentNode:
            case $node instanceof FragmentDefinitionNode:
                $typeConditionNode = $node->typeCondition;
                $outputType        = $typeConditionNode
                    ? self::typeFromAST(
                        $schema,
                        $typeConditionNode
                    )
                    : Type::getNamedType($this->getType());
                $this->typeStack[] = Type::isOutputType($outputType) ? $outputType : null;
                break;

            case $node instanceof VariableDefinitionNode:
                $inputType              = self::typeFromAST($schema, $node->type);
                $this->inputTypeStack[] = Type::isInputType($inputType) ? $inputType : null; // push
                break;

            case $node instanceof ArgumentNode:
                $fieldOrDirective = $this->getDirective() ?? $this->getFieldDef();
                $argDef           = $argType = null;
                if ($fieldOrDirective) {
                    /** @var FieldArgument $argDef */
                    $argDef = Utils::find(
                        $fieldOrDirective->args,
                        static function ($arg) use ($node) : bool {
                            return $arg->name === $node->name->value;
                        }
                    );
                    if ($argDef !== null) {
                        $argType = $argDef->getType();
                    }
                }
                $this->argument            = $argDef;
                $this->defaultValueStack[] = $argDef && $argDef->defaultValueExists() ? $argDef->defaultValue : Utils::undefined();
                $this->inputTypeStack[]    = Type::isInputType($argType) ? $argType : null;
                break;

            case $node instanceof ListValueNode:
                $type     = $this->getInputType();
                $listType = $type === null ? null : Type::getNullableType($type);
                $itemType = $listType instanceof ListOfType
                    ? $listType->getWrappedType()
                    : $listType;
                // List positions never have a default value.
                $this->defaultValueStack[] = Utils::undefined();
                $this->inputTypeStack[]    = Type::isInputType($itemType) ? $itemType : null;
                break;

            case $node instanceof ObjectFieldNode:
                $objectType     = Type::getNamedType($this->getInputType());
                $fieldType      = null;
                $inputField     = null;
                $inputFieldType = null;
                if ($objectType instanceof InputObjectType) {
                    $tmp            = $objectType->getFields();
                    $inputField     = $tmp[$node->name->value] ?? null;
                    $inputFieldType = $inputField ? $inputField->getType() : null;
                }
                $this->defaultValueStack[] = $inputField && $inputField->defaultValueExists() ? $inputField->defaultValue : Utils::undefined();
                $this->inputTypeStack[]    = Type::isInputType($inputFieldType) ? $inputFieldType : null;
                break;

            case $node instanceof EnumValueNode:
                $enumType  = Type::getNamedType($this->getInputType());
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
    public function getType() : ?OutputType
    {
        return $this->typeStack[count($this->typeStack) - 1] ?? null;
    }

    /**
     * @return (CompositeType & Type) | null
     */
    public function getParentType() : ?CompositeType
    {
        return $this->parentTypeStack[count($this->parentTypeStack) - 1] ?? null;
    }

    /**
     * Not exactly the same as the executor's definition of getFieldDef, in this
     * statically evaluated environment we do not always have an Object type,
     * and need to handle Interface and Union types.
     */
    private static function getFieldDefinition(Schema $schema, Type $parentType, FieldNode $fieldNode) : ?FieldDefinition
    {
        $name       = $fieldNode->name->value;
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

        if ($parentType instanceof ObjectType ||
            $parentType instanceof InterfaceType
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
    public static function typeFromAST(Schema $schema, $inputTypeNode) : ?Type
    {
        return AST::typeFromAST($schema, $inputTypeNode);
    }

    public function getDirective() : ?Directive
    {
        return $this->directive;
    }

    public function getFieldDef() : ?FieldDefinition
    {
        return $this->fieldDefStack[count($this->fieldDefStack) - 1] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function getDefaultValue()
    {
        return $this->defaultValueStack[count($this->defaultValueStack) - 1] ?? null;
    }

    /**
     * @return (Type & InputType) | null
     */
    public function getInputType() : ?InputType
    {
        return $this->inputTypeStack[count($this->inputTypeStack) - 1] ?? null;
    }

    public function leave(Node $node)
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
