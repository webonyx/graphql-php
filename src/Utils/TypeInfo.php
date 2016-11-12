<?php
namespace GraphQL\Utils;

use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\NonNullType;
use GraphQL\Schema;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Utils;

/**
 * Class TypeInfo
 * @package GraphQL\Utils
 */
class TypeInfo
{
    /**
     * Provided two types, return true if the types are equal (invariant).
     */
    public static function isEqualType(Type $typeA, Type $typeB)
    {
        // Equivalent types are equal.
        if ($typeA === $typeB) {
            return true;
        }

        // If either type is non-null, the other must also be non-null.
        if ($typeA instanceof NonNull && $typeB instanceof NonNull) {
            return self::isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }

        // If either type is a list, the other must also be a list.
        if ($typeA instanceof ListOfType && $typeB instanceof ListOfType) {
            return self::isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }

        // Otherwise the types are not equal.
        return false;
    }

    /**
     * Provided a type and a super type, return true if the first type is either
     * equal or a subset of the second super type (covariant).
     */
    static function isTypeSubTypeOf(Schema $schema, Type $maybeSubType, Type $superType)
    {
        // Equivalent type is a valid subtype
        if ($maybeSubType === $superType) {
            return true;
        }

        // If superType is non-null, maybeSubType must also be nullable.
        if ($superType instanceof NonNull) {
            if ($maybeSubType instanceof NonNull) {
                return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType->getWrappedType());
            }
            return false;
        } else if ($maybeSubType instanceof NonNull) {
            // If superType is nullable, maybeSubType may be non-null.
            return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType);
        }

        // If superType type is a list, maybeSubType type must also be a list.
        if ($superType instanceof ListOfType) {
            if ($maybeSubType instanceof ListOfType) {
                return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType->getWrappedType());
            }
            return false;
        } else if ($maybeSubType instanceof ListOfType) {
            // If superType is not a list, maybeSubType must also be not a list.
            return false;
        }

        // If superType type is an abstract type, maybeSubType type may be a currently
        // possible object type.
        if (Type::isAbstractType($superType) && $maybeSubType instanceof ObjectType && $schema->isPossibleType($superType, $maybeSubType)) {
            return true;
        }

        // Otherwise, the child type is not a valid subtype of the parent type.
        return false;
    }


    /**
     * Provided two composite types, determine if they "overlap". Two composite
     * types overlap when the Sets of possible concrete types for each intersect.
     *
     * This is often used to determine if a fragment of a given type could possibly
     * be visited in a context of another type.
     *
     * This function is commutative.
     */
    static function doTypesOverlap(Schema $schema, CompositeType $typeA, CompositeType $typeB)
    {
        // Equivalent types overlap
        if ($typeA === $typeB) {
            return true;
        }

        if ($typeA instanceof AbstractType) {
            if ($typeB instanceof AbstractType) {
                // If both types are abstract, then determine if there is any intersection
                // between possible concrete types of each.
                foreach ($schema->getPossibleTypes($typeA) as $type) {
                    if ($schema->isPossibleType($typeB, $type)) {
                        return true;
                    }
                }
                return false;
            }

            /** @var $typeB ObjectType */
            // Determine if the latter type is a possible concrete type of the former.
            return $schema->isPossibleType($typeA, $typeB);
        }

        if ($typeB instanceof AbstractType) {
            /** @var $typeA ObjectType */
            // Determine if the former type is a possible concrete type of the latter.
            return $schema->isPossibleType($typeB, $typeA);
        }

        // Otherwise the types do not overlap.
        return false;
    }


    /**
     * @param Schema $schema
     * @param $inputTypeAst
     * @return Type
     * @throws \Exception
     */
    public static function typeFromAST(Schema $schema, $inputTypeAst)
    {
        if ($inputTypeAst instanceof ListType) {
            $innerType = self::typeFromAST($schema, $inputTypeAst->getType());
            return $innerType ? new ListOfType($innerType) : null;
        }
        if ($inputTypeAst instanceof NonNullType) {
            $innerType = self::typeFromAST($schema, $inputTypeAst->getType());
            return $innerType ? new NonNull($innerType) : null;
        }

        Utils::invariant($inputTypeAst && $inputTypeAst instanceof NamedType, 'Must be a named type');
        return $schema->getType($inputTypeAst->getName()->getValue());
    }

    /**
     * Not exactly the same as the executor's definition of getFieldDef, in this
     * statically evaluated environment we do not always have an Object type,
     * and need to handle Interface and Union types.
     *
     * @return FieldDefinition
     */
    static private function getFieldDefinition(Schema $schema, Type $parentType, Field $fieldAST)
    {
        $name = $fieldAST->getName()->getValue();
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
            $parentType instanceof InterfaceType) {
            $fields = $parentType->getFields();
            return isset($fields[$name]) ? $fields[$name] : null;
        }
        return null;
    }


    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var \SplStack<OutputType>
     */
    private $typeStack;

    /**
     * @var \SplStack<CompositeType>
     */
    private $parentTypeStack;

    /**
     * @var \SplStack<InputType>
     */
    private $inputTypeStack;

    /**
     * @var \SplStack<FieldDefinition>
     */
    private $fieldDefStack;

    /**
     * @var Directive
     */
    private $directive;

    /**
     * @var FieldArgument
     */
    private $argument;

    /**
     * TypeInfo constructor.
     * @param Schema $schema
     */
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->typeStack = [];
        $this->parentTypeStack = [];
        $this->inputTypeStack = [];
        $this->fieldDefStack = [];
    }

    /**
     * @return Type
     */
    function getType()
    {
        if (!empty($this->typeStack)) {
            return $this->typeStack[count($this->typeStack) - 1];
        }
        return null;
    }

    /**
     * @return Type
     */
    function getParentType()
    {
        if (!empty($this->parentTypeStack)) {
            return $this->parentTypeStack[count($this->parentTypeStack) - 1];
        }
        return null;
    }

    /**
     * @return InputType
     */
    function getInputType()
    {
        if (!empty($this->inputTypeStack)) {
            return $this->inputTypeStack[count($this->inputTypeStack) - 1];
        }
        return null;
    }

    /**
     * @return FieldDefinition
     */
    function getFieldDef()
    {
        if (!empty($this->fieldDefStack)) {
            return $this->fieldDefStack[count($this->fieldDefStack) - 1];
        }
        return null;
    }

    /**
     * @return Directive|null
     */
    function getDirective()
    {
        return $this->directive;
    }

    /**
     * @return FieldArgument|null
     */
    function getArgument()
    {
        return $this->argument;
    }

    /**
     * @param Node $node
     */
    function enter(Node $node)
    {
        $schema = $this->schema;

        switch ($node->getKind()) {
            case NodeType::SELECTION_SET:
                $namedType = Type::getNamedType($this->getType());
                $compositeType = null;
                if (Type::isCompositeType($namedType)) {
                    // isCompositeType is a type refining predicate, so this is safe.
                    $compositeType = $namedType;
                }
                $this->parentTypeStack[] = $compositeType; // push
                break;

            case NodeType::FIELD:
                $parentType = $this->getParentType();
                $fieldDef = null;
                if ($parentType) {
                    $fieldDef = self::getFieldDefinition($schema, $parentType, $node);
                }
                $this->fieldDefStack[] = $fieldDef; // push
                $this->typeStack[] = $fieldDef ? $fieldDef->getType() : null; // push
                break;

            case NodeType::DIRECTIVE:
                $this->directive = $schema->getDirective($node->getName()->getValue());
                break;

            case NodeType::OPERATION_DEFINITION:
                $type = null;
                if ($node->getOperation() === 'query') {
                    $type = $schema->getQueryType();
                } else if ($node->getOperation() === 'mutation') {
                    $type = $schema->getMutationType();
                } else if ($node->getOperation() === 'subscription') {
                    $type = $schema->getSubscriptionType();
                }
                $this->typeStack[] = $type; // push
                break;

            case NodeType::INLINE_FRAGMENT:
            case NodeType::FRAGMENT_DEFINITION:
                $typeConditionAST = $node->getTypeCondition();
                $outputType = $typeConditionAST ? self::typeFromAST($schema, $typeConditionAST) : $this->getType();
                $this->typeStack[] = $outputType; // push
                break;

            case NodeType::VARIABLE_DEFINITION:
                $inputType = self::typeFromAST($schema, $node->getType());
                $this->inputTypeStack[] = $inputType; // push
                break;

            case NodeType::ARGUMENT:
                $fieldOrDirective = $this->getDirective() ?: $this->getFieldDef();
                $argDef = $argType = null;
                if ($fieldOrDirective) {
                    $argDef = Utils::find($fieldOrDirective->args, function($arg) use ($node) {return $arg->name === $node->getName()->getValue();});
                    if ($argDef) {
                        $argType = $argDef->getType();
                    }
                }
                $this->argument = $argDef;
                $this->inputTypeStack[] = $argType; // push
                break;

            case NodeType::LST:
                $listType = Type::getNullableType($this->getInputType());
                $this->inputTypeStack[] = ($listType instanceof ListOfType ? $listType->getWrappedType() : null); // push
                break;

            case NodeType::OBJECT_FIELD:
                $objectType = Type::getNamedType($this->getInputType());
                $fieldType = null;
                if ($objectType instanceof InputObjectType) {
                    $tmp = $objectType->getFields();
                    $inputField = isset($tmp[$node->getName()->getValue()]) ? $tmp[$node->getName()->getValue()] : null;
                    $fieldType = $inputField ? $inputField->getType() : null;
                }
                $this->inputTypeStack[] = $fieldType;
            break;
        }
    }

    /**
     * @param Node $node
     */
    function leave(Node $node)
    {
        switch ($node->getKind()) {
            case NodeType::SELECTION_SET:
                array_pop($this->parentTypeStack);
                break;

            case NodeType::FIELD:
                array_pop($this->fieldDefStack);
                array_pop($this->typeStack);
                break;

            case NodeType::DIRECTIVE:
                $this->directive = null;
                break;

            case NodeType::OPERATION_DEFINITION:
            case NodeType::INLINE_FRAGMENT:
            case NodeType::FRAGMENT_DEFINITION:
                array_pop($this->typeStack);
                break;
            case NodeType::VARIABLE_DEFINITION:
                array_pop($this->inputTypeStack);
                break;
            case NodeType::ARGUMENT:
                $this->argument = null;
                array_pop($this->inputTypeStack);
                break;
            case NodeType::LST:
            case NodeType::OBJECT_FIELD:
                array_pop($this->inputTypeStack);
                break;
        }
    }
}
