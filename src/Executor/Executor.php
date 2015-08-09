<?php
namespace GraphQL\Executor;

use GraphQL\Error;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Utils;

/**
 * Terminology
 *
 * "Definitions" are the generic name for top-level statements in the document.
 * Examples of this include:
 * 1) Operations (such as a query)
 * 2) Fragments
 *
 * "Operations" are a generic name for requests in the document.
 * Examples of this include:
 * 1) query,
 * 2) mutation
 *
 * "Selections" are the statements that can appear legally and at
 * single level of the query. These include:
 * 1) field references e.g "a"
 * 2) fragment "spreads" e.g. "...c"
 * 3) inline fragment "spreads" e.g. "...on Type { a }"
 */
class Executor
{
    private static $UNDEFINED;

    public static function execute(Schema $schema, $root, Document $ast, $operationName = null, array $args = null)
    {
        if (!self::$UNDEFINED) {
            self::$UNDEFINED = new \stdClass();
        }

        try {
            $errors = new \ArrayObject();
            $exeContext = self::buildExecutionContext($schema, $root, $ast, $operationName, $args, $errors);
            $data = self::executeOperation($exeContext, $root, $exeContext->operation);
        } catch (\Exception $e) {
            $errors[] = $e;
        }

        $result = [
            'data' => isset($data) ? $data : null
        ];
        if (count($errors) > 0) {
            $result['errors'] = array_map(['GraphQL\Error', 'formatError'], $errors->getArrayCopy());
        }

        return $result;
    }

    /**
     * Constructs a ExecutionContext object from the arguments passed to
     * execute, which we will pass throughout the other execution methods.
     */
    private static function buildExecutionContext(Schema $schema, $root, Document $ast, $operationName = null, array $args = null, &$errors)
    {
        $operations = [];
        $fragments = [];

        foreach ($ast->definitions as $statement) {
            switch ($statement->kind) {
                case Node::OPERATION_DEFINITION:
                    $operations[$statement->name ? $statement->name->value : ''] = $statement;
                    break;
                case Node::FRAGMENT_DEFINITION:
                    $fragments[$statement->name->value] = $statement;
                    break;
            }
        }

        if (!$operationName && count($operations) !== 1) {
            throw new Error(
                'Must provide operation name if query contains multiple operations'
            );
        }

        $opName = $operationName ?: key($operations);
        if (!isset($operations[$opName])) {
            throw new Error('Unknown operation name: ' . $opName);
        }
        $operation = $operations[$opName];

        $variables = Values::getVariableValues($schema, $operation->variableDefinitions ?: array(), $args ?: []);
        $exeContext = new ExecutionContext($schema, $fragments, $root, $operation, $variables, $errors);
        return $exeContext;
    }

    /**
     * Implements the "Evaluating operations" section of the spec.
     */
    private static function executeOperation(ExecutionContext $exeContext, $root, OperationDefinition $operation)
    {
        $type = self::getOperationRootType($exeContext->schema, $operation);
        $fields = self::collectFields($exeContext, $type, $operation->selectionSet, new \ArrayObject(), new \ArrayObject());
        if ($operation->operation === 'mutation') {
            return self::executeFieldsSerially($exeContext, $type, $root, $fields->getArrayCopy());
        }
        return self::executeFields($exeContext, $type, $root, $fields);
    }


    /**
     * Extracts the root type of the operation from the schema.
     *
     * @param Schema $schema
     * @param OperationDefinition $operation
     * @return ObjectType
     * @throws Error
     */
    private static function getOperationRootType(Schema $schema, OperationDefinition $operation)
    {
        switch ($operation->operation) {
            case 'query':
                return $schema->getQueryType();
            case 'mutation':
                $mutationType = $schema->getMutationType();
                if (!$mutationType) {
                    throw new Error(
                        'Schema is not configured for mutations',
                        [$operation]
                    );
                }
                return $mutationType;
            default:
                throw new Error(
                    'Can only execute queries and mutations',
                    [$operation]
                );
        }
    }

    /**
     * Implements the "Evaluating selection sets" section of the spec
     * for "write" mode.
     */
    private static function executeFieldsSerially(ExecutionContext $exeContext, ObjectType $parentType, $source, $fields)
    {
        $results = [];
        foreach ($fields as $responseName => $fieldASTs) {
            $result = self::resolveField($exeContext, $parentType, $source, $fieldASTs);

            if ($result !== self::$UNDEFINED) {
                // Undefined means that field is not defined in schema
                $results[$responseName] = $result;
            }
        }
        return $results;
    }

    /**
     * Implements the "Evaluating selection sets" section of the spec
     * for "read" mode.
     */
    private static function executeFields(ExecutionContext $exeContext, ObjectType $parentType, $source, $fields)
    {
        // Native PHP doesn't support promises.
        // Custom executor should be built for platforms like ReactPHP
        return self::executeFieldsSerially($exeContext, $parentType, $source, $fields);
    }


    /**
     * Given a selectionSet, adds all of the fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * @return \ArrayObject
     */
    private static function collectFields(
        ExecutionContext $exeContext,
        ObjectType $type,
        SelectionSet $selectionSet,
        $fields,
        $visitedFragmentNames
    )
    {
        for ($i = 0; $i < count($selectionSet->selections); $i++) {
            $selection = $selectionSet->selections[$i];
            switch ($selection->kind) {
                case Node::FIELD:
                    if (!self::shouldIncludeNode($exeContext, $selection->directives)) {
                        continue;
                    }
                    $name = self::getFieldEntryKey($selection);
                    if (!isset($fields[$name])) {
                        $fields[$name] = new \ArrayObject();
                    }
                    $fields[$name][] = $selection;
                    break;
                case Node::INLINE_FRAGMENT:
                    if (!self::shouldIncludeNode($exeContext, $selection->directives) ||
                        !self::doesFragmentConditionMatch($exeContext, $selection, $type)
                    ) {
                        continue;
                    }
                    self::collectFields(
                        $exeContext,
                        $type,
                        $selection->selectionSet,
                        $fields,
                        $visitedFragmentNames
                    );
                    break;
                case Node::FRAGMENT_SPREAD:
                    $fragName = $selection->name->value;
                    if (!empty($visitedFragmentNames[$fragName]) || !self::shouldIncludeNode($exeContext, $selection->directives)) {
                        continue;
                    }
                    $visitedFragmentNames[$fragName] = true;

                    /** @var FragmentDefinition|null $fragment */
                    $fragment = isset($exeContext->fragments[$fragName]) ? $exeContext->fragments[$fragName] : null;
                    if (!$fragment ||
                        !self::shouldIncludeNode($exeContext, $fragment->directives) ||
                        !self::doesFragmentConditionMatch($exeContext, $fragment, $type)
                    ) {
                        continue;
                    }
                    self::collectFields(
                        $exeContext,
                        $type,
                        $fragment->selectionSet,
                        $fields,
                        $visitedFragmentNames
                    );
                    break;
            }
        }
        return $fields;
    }

    /**
     * Determines if a field should be included based on @if and @unless directives.
     */
    private static function shouldIncludeNode(ExecutionContext $exeContext, $directives)
    {
        $ifDirective = Values::getDirectiveValue(Directive::ifDirective(), $directives, $exeContext->variables);
        if ($ifDirective !== null) {
            return $ifDirective;
        }

        $unlessDirective = Values::getDirectiveValue(Directive::unlessDirective(), $directives, $exeContext->variables);
        if ($unlessDirective !== null) {
            return !$unlessDirective;
        }

        return true;
    }

    /**
     * Determines if a fragment is applicable to the given type.
     */
    private static function doesFragmentConditionMatch(ExecutionContext $exeContext,/* FragmentDefinition | InlineFragment*/ $fragment, ObjectType $type)
    {
        $conditionalType = Utils\TypeInfo::typeFromAST($exeContext->schema, $fragment->typeCondition);
        if ($conditionalType === $type) {
            return true;
        }
        if ($conditionalType instanceof InterfaceType ||
            $conditionalType instanceof UnionType
        ) {
            return $conditionalType->isPossibleType($type);
        }
        return false;
    }

    /**
     * Implements the logic to compute the key of a given field�s entry
     */
    private static function getFieldEntryKey(Field $node)
    {
        return $node->alias ? $node->alias->value : $node->name->value;
    }

    /**
     * A wrapper function for resolving the field, that catches the error
     * and adds it to the context's global if the error is not rethrowable.
     */
    private static function resolveField(ExecutionContext $exeContext, ObjectType $parentType, $source, $fieldASTs)
    {
        $fieldDef = self::getFieldDef($exeContext->schema, $parentType, $fieldASTs[0]);
        if (!$fieldDef) {
            return self::$UNDEFINED;
        }

        // If the field type is non-nullable, then it is resolved without any
        // protection from errors.
        if ($fieldDef->getType() instanceof NonNull) {
            return self::resolveFieldOrError(
                $exeContext,
                $parentType,
                $source,
                $fieldASTs,
                $fieldDef
            );
        }

        // Otherwise, error protection is applied, logging the error and resolving
        // a null value for this field if one is encountered.
        try {
            $result = self::resolveFieldOrError(
                $exeContext,
                $parentType,
                $source,
                $fieldASTs,
                $fieldDef
            );

            return $result;
        } catch (\Exception $error) {
            $exeContext->addError($error);
            return null;
        }
    }

    /**
     * Resolves the field on the given source object. In particular, this
     * figures out the object that the field returns using the resolve function,
     * then calls completeField to coerce scalars or execute the sub
     * selection set for objects.
     */
    private static function resolveFieldOrError(
        ExecutionContext $exeContext,
        ObjectType $parentType,
        $source,
        /*array<Field>*/ $fieldASTs,
        FieldDefinition $fieldDef
    )
    {
        $fieldAST = $fieldASTs[0];
        $fieldType = $fieldDef->getType();
        $resolveFn = $fieldDef->resolve ?: [__CLASS__, 'defaultResolveFn'];

        // Build a JS object of arguments from the field.arguments AST, using the
        // variables scope to fulfill any variable references.
        // TODO: find a way to memoize, in case this field is within a Array type.
        $args = Values::getArgumentValues(
            $fieldDef->args,
            $fieldAST->arguments,
            $exeContext->variables
        );

        try {
            $result = call_user_func($resolveFn,
                $source,
                $args,
                $exeContext->root,
                // TODO: provide all fieldASTs, not just the first field
                $fieldAST,
                $fieldType,
                $parentType,
                $exeContext->schema
            );
        } catch (\Exception $error) {
            throw new Error($error->getMessage(), [$fieldAST], $error->getTrace());
        }

        return self::completeField(
            $exeContext,
            $fieldType,
            $fieldASTs,
            $result
        );
    }


    /**
     * Implements the instructions for completeValue as defined in the
     * "Field entries" section of the spec.
     *
     * If the field type is Non-Null, then this recursively completes the value
     * for the inner type. It throws a field error if that completion returns null,
     * as per the "Nullability" section of the spec.
     *
     * If the field type is a List, then this recursively completes the value
     * for the inner type on each item in the list.
     *
     * If the field type is a Scalar or Enum, ensures the completed value is a legal
     * value of the type by calling the `coerce` method of GraphQL type definition.
     *
     * Otherwise, the field type expects a sub-selection set, and will complete the
     * value by evaluating all sub-selections.
     */
    private static function completeField(ExecutionContext $exeContext, Type $fieldType,/* Array<Field> */ $fieldASTs, &$result)
    {
        // If field type is NonNull, complete for inner type, and throw field error
        // if result is null.
        if ($fieldType instanceof NonNull) {
            $completed = self::completeField(
                $exeContext,
                $fieldType->getWrappedType(),
                $fieldASTs,
                $result
            );
            if ($completed === null) {
                throw new Error(
                    'Cannot return null for non-nullable type.',
                    $fieldASTs instanceof \ArrayObject ? $fieldASTs->getArrayCopy() : $fieldASTs
                );
            }
            return $completed;
        }

        // If result is null-like, return null.
        if (null === $result) {
            return null;
        }

        // If field type is List, complete each item in the list with the inner type
        if ($fieldType instanceof ListOfType) {
            $itemType = $fieldType->getWrappedType();
            Utils::invariant(
                is_array($result) || $result instanceof \ArrayAccess,
                'User Error: expected iterable, but did not find one.'
            );

            $tmp = [];
            foreach ($result as $item) {
                $tmp[] = self::completeField($exeContext, $itemType, $fieldASTs, $item);
            }
            return $tmp;
        }

        // If field type is Scalar or Enum, coerce to a valid value, returning null
        // if coercion is not possible.
        if ($fieldType instanceof ScalarType ||
            $fieldType instanceof EnumType
        ) {
            Utils::invariant(method_exists($fieldType, 'coerce'), 'Missing coerce method on type');
            return $fieldType->coerce($result);
        }

        // Field type must be Object, Interface or Union and expect sub-selections.

        $objectType =
            $fieldType instanceof ObjectType ? $fieldType :
                ($fieldType instanceof InterfaceType ||
                $fieldType instanceof UnionType ? $fieldType->resolveType($result) :
                    null);

        if (!$objectType) {
            return null;
        }

        // Collect sub-fields to execute to complete this value.
        $subFieldASTs = new \ArrayObject();
        $visitedFragmentNames = new \ArrayObject();
        for ($i = 0; $i < count($fieldASTs); $i++) {
            $selectionSet = $fieldASTs[$i]->selectionSet;
            if ($selectionSet) {
                $subFieldASTs = self::collectFields(
                    $exeContext,
                    $objectType,
                    $selectionSet,
                    $subFieldASTs,
                    $visitedFragmentNames
                );
            }
        }

        return self::executeFields($exeContext, $objectType, $result, $subFieldASTs);
    }


    /**
     * If a resolve function is not given, then a default resolve behavior is used
     * which takes the property of the source object of the same name as the field
     * and returns it as the result, or if it's a function, returns the result
     * of calling that function.
     */
    public static function defaultResolveFn($source, $args, $root, $fieldAST)
    {
        $property = null;
        if (is_array($source) || $source instanceof \ArrayAccess) {
            if (isset($source[$fieldAST->name->value])) {
                $property = $source[$fieldAST->name->value];
            }
        } else if (is_object($source)) {
            if (property_exists($source, $fieldAST->name->value)) {
                $e = func_get_args();
                $property = $source->{$fieldAST->name->value};
            }
        }

        return is_callable($property) ? call_user_func($property, $source) : $property;
    }

    /**
     * This method looks up the field on the given type defintion.
     * It has special casing for the two introspection fields, __schema
     * and __typename. __typename is special because it can always be
     * queried as a field, even in situations where no other fields
     * are allowed, like on a Union. __schema could get automatically
     * added to the query type, but that would require mutating type
     * definitions, which would cause issues.
     *
     * @return FieldDefinition
     */
    private static function getFieldDef(Schema $schema, ObjectType $parentType, Field $fieldAST)
    {
        $name = $fieldAST->name->value;
        $schemaMetaFieldDef = Introspection::schemaMetaFieldDef();
        $typeMetaFieldDef = Introspection::typeMetaFieldDef();
        $typeNameMetaFieldDef = Introspection::typeNameMetaFieldDef();

        if ($name === $schemaMetaFieldDef->name &&
            $schema->getQueryType() === $parentType
        ) {
            return $schemaMetaFieldDef;
        } else if ($name === $typeMetaFieldDef->name &&
            $schema->getQueryType() === $parentType
        ) {
            return $typeMetaFieldDef;
        } else if ($name === $typeNameMetaFieldDef->name) {
            return $typeNameMetaFieldDef;
        }
        $tmp = $parentType->getFields();
        return isset($tmp[$name]) ? $tmp[$name] : null;
    }
}
