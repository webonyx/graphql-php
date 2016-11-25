<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Schema;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
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

    private static $defaultFieldResolver = [__CLASS__, 'defaultFieldResolver'];

    /**
     * Custom default resolve function
     *
     * @param $fn
     * @throws \Exception
     */
    public static function setDefaultFieldResolver(callable $fn)
    {
        self::$defaultFieldResolver = $fn;
    }

    /**
     * @param Schema $schema
     * @param DocumentNode $ast
     * @param $rootValue
     * @param $contextValue
     * @param array|\ArrayAccess $variableValues
     * @param null $operationName
     * @return ExecutionResult
     */
    public static function execute(Schema $schema, DocumentNode $ast, $rootValue = null, $contextValue = null, $variableValues = null, $operationName = null)
    {
        if (!self::$UNDEFINED) {
            self::$UNDEFINED = new \stdClass();
        }

        if (null !== $variableValues) {
            Utils::invariant(
                is_array($variableValues) || $variableValues instanceof \ArrayAccess,
                "Variable values are expected to be array or instance of ArrayAccess, got " . Utils::getVariableType($variableValues)
            );
        }
        if (null !== $operationName) {
            Utils::invariant(
                is_string($operationName),
                "Operation name is supposed to be string, got " . Utils::getVariableType($operationName)
            );
        }

        $exeContext = self::buildExecutionContext($schema, $ast, $rootValue, $contextValue, $variableValues, $operationName);

        try {
            $data = self::executeOperation($exeContext, $exeContext->operation, $rootValue);
        } catch (Error $e) {
            $exeContext->addError($e);
            $data = null;
        }

        return new ExecutionResult((array) $data, $exeContext->errors);
    }

    /**
     * Constructs a ExecutionContext object from the arguments passed to
     * execute, which we will pass throughout the other execution methods.
     */
    private static function buildExecutionContext(
        Schema $schema,
        DocumentNode $documentNode,
        $rootValue,
        $contextValue,
        $rawVariableValues,
        $operationName = null
    )
    {
        $errors = [];
        $fragments = [];
        $operation = null;

        foreach ($documentNode->definitions as $definition) {
            switch ($definition->kind) {
                case NodeKind::OPERATION_DEFINITION:
                    if (!$operationName && $operation) {
                        throw new Error(
                            'Must provide operation name if query contains multiple operations.'
                        );
                    }
                    if (!$operationName ||
                        (isset($definition->name) && $definition->name->value === $operationName)) {
                        $operation = $definition;
                    }
                    break;
                case NodeKind::FRAGMENT_DEFINITION:
                    $fragments[$definition->name->value] = $definition;
                    break;
                default:
                    throw new Error(
                        "GraphQL cannot execute a request containing a {$definition->kind}.",
                        [$definition]
                    );
            }
        }

        if (!$operation) {
            if ($operationName) {
                throw new Error("Unknown operation named \"$operationName\".");
            } else {
                throw new Error('Must provide an operation.');
            }
        }

        $variableValues = Values::getVariableValues(
            $schema,
            $operation->variableDefinitions ?: [],
            $rawVariableValues ?: []
        );

        $exeContext = new ExecutionContext($schema, $fragments, $rootValue, $contextValue, $operation, $variableValues, $errors);
        return $exeContext;
    }

    /**
     * Implements the "Evaluating operations" section of the spec.
     */
    private static function executeOperation(ExecutionContext $exeContext, OperationDefinitionNode $operation, $rootValue)
    {
        $type = self::getOperationRootType($exeContext->schema, $operation);
        $fields = self::collectFields($exeContext, $type, $operation->selectionSet, new \ArrayObject(), new \ArrayObject());

        $path = [];
        if ($operation->operation === 'mutation') {
            return self::executeFieldsSerially($exeContext, $type, $rootValue, $path, $fields);
        }

        return self::executeFields($exeContext, $type, $rootValue, $path, $fields);
    }


    /**
     * Extracts the root type of the operation from the schema.
     *
     * @param Schema $schema
     * @param OperationDefinitionNode $operation
     * @return ObjectType
     * @throws Error
     */
    private static function getOperationRootType(Schema $schema, OperationDefinitionNode $operation)
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
            case 'subscription':
                $subscriptionType = $schema->getSubscriptionType();
                if (!$subscriptionType) {
                    throw new Error(
                        'Schema is not configured for subscriptions',
                        [ $operation ]
                    );
                }
                return $subscriptionType;
            default:
                throw new Error(
                    'Can only execute queries, mutations and subscriptions',
                    [$operation]
                );
        }
    }

    /**
     * Implements the "Evaluating selection sets" section of the spec
     * for "write" mode.
     */
    private static function executeFieldsSerially(ExecutionContext $exeContext, ObjectType $parentType, $sourceValue, $path, $fields)
    {
        $results = [];
        foreach ($fields as $responseName => $fieldNodes) {
            $fieldPath = $path;
            $fieldPath[] = $responseName;
            $result = self::resolveField($exeContext, $parentType, $sourceValue, $fieldNodes, $fieldPath);

            if ($result !== self::$UNDEFINED) {
                // Undefined means that field is not defined in schema
                $results[$responseName] = $result;
            }
        }
        // see #59
        if ([] === $results) {
            $results = new \stdClass();
        }
        return  $results;
    }

    /**
     * Implements the "Evaluating selection sets" section of the spec
     * for "read" mode.
     */
    private static function executeFields(ExecutionContext $exeContext, ObjectType $parentType, $source, $path, $fields)
    {
        // Native PHP doesn't support promises.
        // Custom executor should be built for platforms like ReactPHP
        return self::executeFieldsSerially($exeContext, $parentType, $source, $path, $fields);
    }


    /**
     * Given a selectionSet, adds all of the fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * CollectFields requires the "runtime type" of an object. For a field which
     * returns and Interface or Union type, the "runtime type" will be the actual
     * Object type returned by that field.
     *
     * @return \ArrayObject
     */
    private static function collectFields(
        ExecutionContext $exeContext,
        ObjectType $runtimeType,
        SelectionSetNode $selectionSet,
        $fields,
        $visitedFragmentNames
    )
    {
        foreach ($selectionSet->selections as $selection) {
            switch ($selection->kind) {
                case NodeKind::FIELD:
                    if (!self::shouldIncludeNode($exeContext, $selection->directives)) {
                        continue;
                    }
                    $name = self::getFieldEntryKey($selection);
                    if (!isset($fields[$name])) {
                        $fields[$name] = new \ArrayObject();
                    }
                    $fields[$name][] = $selection;
                    break;
                case NodeKind::INLINE_FRAGMENT:
                    if (!self::shouldIncludeNode($exeContext, $selection->directives) ||
                        !self::doesFragmentConditionMatch($exeContext, $selection, $runtimeType)
                    ) {
                        continue;
                    }
                    self::collectFields(
                        $exeContext,
                        $runtimeType,
                        $selection->selectionSet,
                        $fields,
                        $visitedFragmentNames
                    );
                    break;
                case NodeKind::FRAGMENT_SPREAD:
                    $fragName = $selection->name->value;
                    if (!empty($visitedFragmentNames[$fragName]) || !self::shouldIncludeNode($exeContext, $selection->directives)) {
                        continue;
                    }
                    $visitedFragmentNames[$fragName] = true;

                    /** @var FragmentDefinitionNode|null $fragment */
                    $fragment = isset($exeContext->fragments[$fragName]) ? $exeContext->fragments[$fragName] : null;
                    if (!$fragment || !self::doesFragmentConditionMatch($exeContext, $fragment, $runtimeType)) {
                        continue;
                    }
                    self::collectFields(
                        $exeContext,
                        $runtimeType,
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
     * Determines if a field should be included based on the @include and @skip
     * directives, where @skip has higher precedence than @include.
     */
    private static function shouldIncludeNode(ExecutionContext $exeContext, $directives)
    {
        $skipDirective = Directive::skipDirective();
        $includeDirective = Directive::includeDirective();

        /** @var \GraphQL\Language\AST\DirectiveNode $skipNode */
        $skipNode = $directives
            ? Utils::find($directives, function(\GraphQL\Language\AST\DirectiveNode $directive) use ($skipDirective) {
                return $directive->name->value === $skipDirective->name;
            })
            : null;

        if ($skipNode) {
            $argValues = Values::getArgumentValues($skipDirective, $skipNode, $exeContext->variableValues);
            if (isset($argValues['if']) && $argValues['if'] === true) {
                return false;
            }
        }

        /** @var \GraphQL\Language\AST\DirectiveNode $includeNode */
        $includeNode = $directives
            ? Utils::find($directives, function(\GraphQL\Language\AST\DirectiveNode $directive) use ($includeDirective) {
                return $directive->name->value === $includeDirective->name;
            })
            : null;

        if ($includeNode) {
            $argValues = Values::getArgumentValues($includeDirective, $includeNode, $exeContext->variableValues);
            if (isset($argValues['if']) && $argValues['if'] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if a fragment is applicable to the given type.
     */
    private static function doesFragmentConditionMatch(ExecutionContext $exeContext,/* FragmentDefinitionNode | InlineFragmentNode*/ $fragment, ObjectType $type)
    {
        $typeConditionNode = $fragment->typeCondition;

        if (!$typeConditionNode) {
            return true;
        }

        $conditionalType = Utils\TypeInfo::typeFromAST($exeContext->schema, $typeConditionNode);
        if ($conditionalType === $type) {
            return true;
        }
        if ($conditionalType instanceof AbstractType) {
            return $exeContext->schema->isPossibleType($conditionalType, $type);
        }
        return false;
    }

    /**
     * Implements the logic to compute the key of a given fields entry
     */
    private static function getFieldEntryKey(FieldNode $node)
    {
        return $node->alias ? $node->alias->value : $node->name->value;
    }

    /**
     * Resolves the field on the given source object. In particular, this
     * figures out the value that the field returns by calling its resolve function,
     * then calls completeValue to complete promises, serialize scalars, or execute
     * the sub-selection-set for objects.
     */
    private static function resolveField(ExecutionContext $exeContext, ObjectType $parentType, $source, $fieldNodes, $path)
    {
        $fieldNode = $fieldNodes[0];

        $fieldName = $fieldNode->name->value;

        $fieldDef = self::getFieldDef($exeContext->schema, $parentType, $fieldName);

        if (!$fieldDef) {
            return self::$UNDEFINED;
        }

        $returnType = $fieldDef->getType();

        // The resolve function's optional third argument is a collection of
        // information about the current execution state.
        $info = new ResolveInfo([
            'fieldName' => $fieldName,
            'fieldNodes' => $fieldNodes,
            'returnType' => $returnType,
            'parentType' => $parentType,
            'path' => $path,
            'schema' => $exeContext->schema,
            'fragments' => $exeContext->fragments,
            'rootValue' => $exeContext->rootValue,
            'operation' => $exeContext->operation,
            'variableValues' => $exeContext->variableValues,
        ]);


        if (isset($fieldDef->resolveFn)) {
            $resolveFn = $fieldDef->resolveFn;
        } else if (isset($parentType->resolveFieldFn)) {
            $resolveFn = $parentType->resolveFieldFn;
        } else {
            $resolveFn = self::$defaultFieldResolver;
        }

        // The resolve function's optional third argument is a context value that
        // is provided to every resolve function within an execution. It is commonly
        // used to represent an authenticated user, or request-specific caches.
        $context = $exeContext->contextValue;

        // Get the resolve function, regardless of if its result is normal
        // or abrupt (error).
        $result = self::resolveOrError(
            $exeContext,
            $fieldDef,
            $fieldNode,
            $resolveFn,
            $source,
            $context,
            $info
        );

        $result = self::completeValueCatchingError(
            $exeContext,
            $returnType,
            $fieldNodes,
            $info,
            $path,
            $result
        );

        return $result;
    }

    /**
     * Isolates the "ReturnOrAbrupt" behavior to not de-opt the `resolveField`
     * function. Returns the result of resolveFn or the abrupt-return Error object.
     *
     * @param ExecutionContext $exeContext
     * @param FieldDefinition $fieldDef
     * @param FieldNode $fieldNode
     * @param callable $resolveFn
     * @param mixed $source
     * @param mixed $context
     * @param ResolveInfo $info
     * @return \Exception|mixed
     */
    private static function resolveOrError($exeContext, $fieldDef, $fieldNode, $resolveFn, $source, $context, $info)
    {
        try {
            // Build hash of arguments from the field.arguments AST, using the
            // variables scope to fulfill any variable references.
            $args = Values::getArgumentValues(
                $fieldDef,
                $fieldNode,
                $exeContext->variableValues
            );

            return call_user_func($resolveFn, $source, $args, $context, $info);
        } catch (\Exception $error) {
            return $error;
        }
    }

    /**
     * This is a small wrapper around completeValue which detects and logs errors
     * in the execution context.
     *
     * @param ExecutionContext $exeContext
     * @param Type $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param $path
     * @param $result
     * @return array|null
     */
    private static function completeValueCatchingError(
        ExecutionContext $exeContext,
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        $result
    )
    {
        // If the field type is non-nullable, then it is resolved without any
        // protection from errors.
        if ($returnType instanceof NonNull) {
            return self::completeValueWithLocatedError(
                $exeContext,
                $returnType,
                $fieldNodes,
                $info,
                $path,
                $result
            );
        }

        // Otherwise, error protection is applied, logging the error and resolving
        // a null value for this field if one is encountered.
        try {
            return self::completeValueWithLocatedError(
                $exeContext,
                $returnType,
                $fieldNodes,
                $info,
                $path,
                $result
            );
        } catch (Error $err) {
            // If `completeValueWithLocatedError` returned abruptly (threw an error), log the error
            // and return null.
            $exeContext->addError($err);
            return null;
        }
    }


    /**
     * This is a small wrapper around completeValue which annotates errors with
     * location information.
     *
     * @param ExecutionContext $exeContext
     * @param Type $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param $path
     * @param $result
     * @return array|null
     * @throws Error
     */
    static function completeValueWithLocatedError(
        ExecutionContext $exeContext,
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        $result
    )
    {
        try {
            return self::completeValue(
                $exeContext,
                $returnType,
                $fieldNodes,
                $info,
                $path,
                $result
            );
        } catch (\Exception $error) {
            throw Error::createLocatedError($error, $fieldNodes, $path);
        }
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
     * value of the type by calling the `serialize` method of GraphQL type
     * definition.
     *
     * If the field is an abstract type, determine the runtime type of the value
     * and then complete based on that type
     *
     * Otherwise, the field type expects a sub-selection set, and will complete the
     * value by evaluating all sub-selections.
     *
     * @param ExecutionContext $exeContext
     * @param Type $returnType
     * @param FieldNode[] $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array|null
     * @throws Error
     * @throws \Exception
     */
    private static function completeValue(
        ExecutionContext $exeContext,
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        &$result
    )
    {
        if ($result instanceof \Exception) {
            throw $result;
        }

        // If field type is NonNull, complete for inner type, and throw field error
        // if result is null.
        if ($returnType instanceof NonNull) {
            $completed = self::completeValue(
                $exeContext,
                $returnType->getWrappedType(),
                $fieldNodes,
                $info,
                $path,
                $result
            );
            if ($completed === null) {
                throw new InvariantViolation(
                    'Cannot return null for non-nullable field ' . $info->parentType . '.' . $info->fieldName . '.'
                );
            }
            return $completed;
        }

        // If result is null-like, return null.
        if (null === $result) {
            return null;
        }

        // If field type is List, complete each item in the list with the inner type
        if ($returnType instanceof ListOfType) {
            return self::completeListValue($exeContext, $returnType, $fieldNodes, $info, $path, $result);
        }

        // If field type is Scalar or Enum, serialize to a valid value, returning
        // null if serialization is not possible.
        if ($returnType instanceof LeafType) {
            return self::completeLeafValue($returnType, $result);
        }

        if ($returnType instanceof AbstractType) {
            return self::completeAbstractValue($exeContext, $returnType, $fieldNodes, $info, $path, $result);
        }

        // Field type must be Object, Interface or Union and expect sub-selections.
        if ($returnType instanceof ObjectType) {
            return self::completeObjectValue($exeContext, $returnType, $fieldNodes, $info, $path, $result);
        }

        throw new \RuntimeException("Cannot complete value of unexpected type \"{$returnType}\".");
    }

    /**
     * If a resolve function is not given, then a default resolve behavior is used
     * which takes the property of the source object of the same name as the field
     * and returns it as the result, or if it's a function, returns the result
     * of calling that function while passing along args and context.
     */
    public static function defaultFieldResolver($source, $args, $context, ResolveInfo $info)
    {
        $fieldName = $info->fieldName;
        $property = null;

        if (is_array($source) || $source instanceof \ArrayAccess) {
            if (isset($source[$fieldName])) {
                $property = $source[$fieldName];
            }
        } else if (is_object($source)) {
            if (isset($source->{$fieldName})) {
                $property = $source->{$fieldName};
            }
        }

        return $property instanceof \Closure ? $property($source, $args, $context) : $property;
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
    private static function getFieldDef(Schema $schema, ObjectType $parentType, $fieldName)
    {
        static $schemaMetaFieldDef, $typeMetaFieldDef, $typeNameMetaFieldDef;

        $schemaMetaFieldDef = $schemaMetaFieldDef ?: Introspection::schemaMetaFieldDef();
        $typeMetaFieldDef = $typeMetaFieldDef ?: Introspection::typeMetaFieldDef();
        $typeNameMetaFieldDef = $typeNameMetaFieldDef ?: Introspection::typeNameMetaFieldDef();

        if ($fieldName === $schemaMetaFieldDef->name && $schema->getQueryType() === $parentType) {
            return $schemaMetaFieldDef;
        } else if ($fieldName === $typeMetaFieldDef->name && $schema->getQueryType() === $parentType) {
            return $typeMetaFieldDef;
        } else if ($fieldName === $typeNameMetaFieldDef->name) {
            return $typeNameMetaFieldDef;
        }

        $tmp = $parentType->getFields();
        return isset($tmp[$fieldName]) ? $tmp[$fieldName] : null;
    }

    /**
     * Complete a value of an abstract type by determining the runtime object type
     * of that value, then complete the value for that type.
     *
     * @param ExecutionContext $exeContext
     * @param AbstractType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return mixed
     * @throws Error
     */
    private static function completeAbstractValue(ExecutionContext $exeContext, AbstractType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        $runtimeType = $returnType->resolveType($result, $exeContext->contextValue, $info);

        if (null === $runtimeType) {
            $runtimeType = self::inferTypeOf($result, $exeContext->contextValue, $info, $returnType);
        }

        // If resolveType returns a string, we assume it's a ObjectType name.
        if (is_string($runtimeType)) {
            $runtimeType = $exeContext->schema->getType($runtimeType);
        }

        if (!($runtimeType instanceof ObjectType)) {
            throw new Error(
                "Abstract type {$returnType} must resolve to an Object type at runtime " .
                "for field {$info->parentType}.{$info->fieldName} with value \"" . print_r($result, true) . "\"," .
                "received \"$runtimeType\".",
                $fieldNodes
            );
        }

        if (!$exeContext->schema->isPossibleType($returnType, $runtimeType)) {
            throw new Error(
                "Runtime Object type \"$runtimeType\" is not a possible type for \"$returnType\".",
                $fieldNodes
            );
        }
        return self::completeObjectValue($exeContext, $runtimeType, $fieldNodes, $info, $path, $result);
    }

    /**
     * Complete a list value by completing each item in the list with the
     * inner type
     *
     * @param ExecutionContext $exeContext
     * @param ListOfType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array
     * @throws \Exception
     */
    private static function completeListValue(ExecutionContext $exeContext, ListOfType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        $itemType = $returnType->getWrappedType();
        Utils::invariant(
            is_array($result) || $result instanceof \Traversable,
            'User Error: expected iterable, but did not find one for field ' . $info->parentType . '.' . $info->fieldName . '.'
        );

        $i = 0;
        $tmp = [];
        foreach ($result as $item) {
            $fieldPath = $path;
            $fieldPath[] = $i++;
            $tmp[] = self::completeValueCatchingError($exeContext, $itemType, $fieldNodes, $info, $fieldPath, $item);
        }
        return $tmp;
    }

    /**
     * Complete a Scalar or Enum by serializing to a valid value, returning
     * null if serialization is not possible.
     *
     * @param LeafType $returnType
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    private static function completeLeafValue(LeafType $returnType, &$result)
    {
        $serializedResult = $returnType->serialize($result);

        if ($serializedResult === null) {
            throw new InvariantViolation(
                'Expected a value of type "'. Utils::printSafe($returnType) . '" but received: ' . Utils::printSafe($result)
            );
        }
        return $serializedResult;
    }

    /**
     * Complete an Object value by executing all sub-selections.
     *
     * @param ExecutionContext $exeContext
     * @param ObjectType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array
     * @throws Error
     */
    private static function completeObjectValue(ExecutionContext $exeContext, ObjectType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        // If there is an isTypeOf predicate function, call it with the
        // current result. If isTypeOf returns false, then raise an error rather
        // than continuing execution.
        if (false === $returnType->isTypeOf($result, $exeContext->contextValue, $info)) {
            throw new Error(
                "Expected value of type $returnType but got: " . Utils::getVariableType($result),
                $fieldNodes
            );
        }

        // Collect sub-fields to execute to complete this value.
        $subFieldNodes = new \ArrayObject();
        $visitedFragmentNames = new \ArrayObject();

        foreach ($fieldNodes as $fieldNode) {
            if (isset($fieldNode->selectionSet)) {
                $subFieldNodes = self::collectFields(
                    $exeContext,
                    $returnType,
                    $fieldNode->selectionSet,
                    $subFieldNodes,
                    $visitedFragmentNames
                );
            }
        }

        return self::executeFields($exeContext, $returnType, $result, $path, $subFieldNodes);
    }

    /**
     * Infer type of the value using isTypeOf of corresponding AbstractType
     *
     * @param $value
     * @param $context
     * @param ResolveInfo $info
     * @param AbstractType $abstractType
     * @return ObjectType|null
     */
    private static function inferTypeOf($value, $context, ResolveInfo $info, AbstractType $abstractType)
    {
        $possibleTypes = $info->schema->getPossibleTypes($abstractType);

        foreach ($possibleTypes as $type) {
            if ($type->isTypeOf($value, $context, $info)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * @deprecated as of 8.0
     */
    public static function defaultResolveFn($source, $args, $context, ResolveInfo $info)
    {
        trigger_error(__METHOD__ . ' is renamed to ' . __CLASS__ . '::defaultFieldResolver', E_USER_DEPRECATED);
        return self::defaultFieldResolver($source, $args, $context, $info);
    }

    /**
     * @deprecated as of 8.0
     */
    public static function setDefaultResolveFn($fn)
    {
        trigger_error(__METHOD__ . ' is renamed to ' . __CLASS__ . '::setDefaultFieldResolver', E_USER_DEPRECATED);
        self::setDefaultFieldResolver($fn);
    }
}
