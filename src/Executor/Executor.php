<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;

/**
 * Implements the "Evaluating requests" section of the GraphQL specification.
 */
class Executor
{
    private static $UNDEFINED;

    private static $defaultFieldResolver = [__CLASS__, 'defaultFieldResolver'];

    /**
     * @var PromiseAdapter
     */
    private static $promiseAdapter;

    /**
     * @param PromiseAdapter|null $promiseAdapter
     */
    public static function setPromiseAdapter(PromiseAdapter $promiseAdapter = null)
    {
        self::$promiseAdapter = $promiseAdapter;
    }

    /**
     * @return PromiseAdapter
     */
    public static function getPromiseAdapter()
    {
        return self::$promiseAdapter ?: (self::$promiseAdapter = new SyncPromiseAdapter());
    }

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
     * Executes DocumentNode against given $schema.
     *
     * Always returns ExecutionResult and never throws. All errors which occur during operation
     * execution are collected in `$result->errors`.
     *
     * @api
     * @param Schema $schema
     * @param DocumentNode $ast
     * @param $rootValue
     * @param $contextValue
     * @param array|\ArrayAccess $variableValues
     * @param null $operationName
     * @param callable $fieldResolver
     *
     * @return ExecutionResult|Promise
     */
    public static function execute(
        Schema $schema,
        DocumentNode $ast,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null,
        callable $fieldResolver = null
    )
    {
        // TODO: deprecate (just always use SyncAdapter here) and have `promiseToExecute()` for other cases
        $promiseAdapter = self::getPromiseAdapter();

        $result = self::promiseToExecute($promiseAdapter, $schema, $ast, $rootValue, $contextValue,
            $variableValues, $operationName, $fieldResolver);

        // Wait for promised results when using sync promises
        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result);
        }

        return $result;
    }

    /**
     * Same as execute(), but requires promise adapter and returns a promise which is always
     * fulfilled with an instance of ExecutionResult and never rejected.
     *
     * Useful for async PHP platforms.
     *
     * @api
     * @param PromiseAdapter $promiseAdapter
     * @param Schema $schema
     * @param DocumentNode $ast
     * @param null $rootValue
     * @param null $contextValue
     * @param null $variableValues
     * @param null $operationName
     * @param callable|null $fieldResolver
     * @return Promise
     */
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        Schema $schema,
        DocumentNode $ast,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null,
        callable $fieldResolver = null
    )
    {
        try {
            $exeContext = self::buildExecutionContext($schema, $ast, $rootValue, $contextValue, $variableValues,
                $operationName, $fieldResolver, $promiseAdapter);
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(new ExecutionResult(null, [$e]));
        }

        $executor = new self($exeContext);
        return $executor->doExecute();
    }

    /**
     * Constructs an ExecutionContext object from the arguments passed to
     * execute, which we will pass throughout the other execution methods.
     *
     * @param Schema $schema
     * @param DocumentNode $documentNode
     * @param $rootValue
     * @param $contextValue
     * @param $rawVariableValues
     * @param string $operationName
     * @param callable $fieldResolver
     * @param PromiseAdapter $promiseAdapter
     *
     * @return ExecutionContext
     * @throws Error
     */
    private static function buildExecutionContext(
        Schema $schema,
        DocumentNode $documentNode,
        $rootValue,
        $contextValue,
        $rawVariableValues,
        $operationName = null,
        callable $fieldResolver = null,
        PromiseAdapter $promiseAdapter = null
    )
    {
        if (null !== $rawVariableValues) {
            Utils::invariant(
                is_array($rawVariableValues) || $rawVariableValues instanceof \ArrayAccess,
                "Variable values are expected to be array or instance of ArrayAccess, got " . Utils::getVariableType($rawVariableValues)
            );
        }
        if (null !== $operationName) {
            Utils::invariant(
                is_string($operationName),
                "Operation name is supposed to be string, got " . Utils::getVariableType($operationName)
            );
        }

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

        $exeContext = new ExecutionContext(
            $schema,
            $fragments,
            $rootValue,
            $contextValue,
            $operation,
            $variableValues,
            $errors,
            $fieldResolver ?: self::$defaultFieldResolver,
            $promiseAdapter ?: self::getPromiseAdapter()
        );
        return $exeContext;
    }

    /**
     * @var ExecutionContext
     */
    private $exeContext;

    /**
     * @var PromiseAdapter
     */
    private $promises;

    /**
     * Executor constructor.
     *
     * @param ExecutionContext $context
     */
    private function __construct(ExecutionContext $context)
    {
        if (!self::$UNDEFINED) {
            self::$UNDEFINED = Utils::undefined();
        }

        $this->exeContext = $context;
    }

    /**
     * @return Promise
     */
    private function doExecute()
    {
        // Return a Promise that will eventually resolve to the data described by
        // The "Response" section of the GraphQL specification.
        //
        // If errors are encountered while executing a GraphQL field, only that
        // field and its descendants will be omitted, and sibling fields will still
        // be executed. An execution which encounters errors will still result in a
        // resolved Promise.
        $result = $this->exeContext->promises->create(function (callable $resolve) {
            return $resolve($this->executeOperation($this->exeContext->operation, $this->exeContext->rootValue));
        });
        return $result
            ->then(null, function ($error) {
                // Errors from sub-fields of a NonNull type may propagate to the top level,
                // at which point we still log the error and null the parent field, which
                // in this case is the entire response.
                $this->exeContext->addError($error);
                return null;
            })
            ->then(function ($data) {
                return new ExecutionResult((array) $data, $this->exeContext->errors);
            });
    }

    /**
     * Implements the "Evaluating operations" section of the spec.
     *
     * @param OperationDefinitionNode $operation
     * @param $rootValue
     * @return Promise|\stdClass|array
     */
    private function executeOperation(OperationDefinitionNode $operation, $rootValue)
    {
        $type = $this->getOperationRootType($this->exeContext->schema, $operation);
        $fields = $this->collectFields($type, $operation->selectionSet, new \ArrayObject(), new \ArrayObject());

        $path = [];

        // Errors from sub-fields of a NonNull type may propagate to the top level,
        // at which point we still log the error and null the parent field, which
        // in this case is the entire response.
        //
        // Similar to completeValueCatchingError.
        try {
            $result = $operation->operation === 'mutation' ?
                $this->executeFieldsSerially($type, $rootValue, $path, $fields) :
                $this->executeFields($type, $rootValue, $path, $fields);

            $promise = $this->getPromise($result);
            if ($promise) {
                return $promise->then(null, function($error) {
                    $this->exeContext->addError($error);
                    return null;
                });
            }
            return $result;

        } catch (Error $error) {
            $this->exeContext->addError($error);
            return null;
        }
    }


    /**
     * Extracts the root type of the operation from the schema.
     *
     * @param Schema $schema
     * @param OperationDefinitionNode $operation
     * @return ObjectType
     * @throws Error
     */
    private function getOperationRootType(Schema $schema, OperationDefinitionNode $operation)
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
     *
     * @param ObjectType $parentType
     * @param $sourceValue
     * @param $path
     * @param $fields
     * @return Promise|\stdClass|array
     */
    private function executeFieldsSerially(ObjectType $parentType, $sourceValue, $path, $fields)
    {
        $prevPromise = $this->exeContext->promises->createFulfilled([]);

        $process = function ($results, $responseName, $path, $parentType, $sourceValue, $fieldNodes) {
            $fieldPath = $path;
            $fieldPath[] = $responseName;
            $result = $this->resolveField($parentType, $sourceValue, $fieldNodes, $fieldPath);
            if ($result === self::$UNDEFINED) {
                return $results;
            }
            $promise = $this->getPromise($result);
            if ($promise) {
                return $promise->then(function ($resolvedResult) use ($responseName, $results) {
                    $results[$responseName] = $resolvedResult;
                    return $results;
                });
            }
            $results[$responseName] = $result;
            return $results;
        };

        foreach ($fields as $responseName => $fieldNodes) {
            $prevPromise = $prevPromise->then(function ($resolvedResults) use ($process, $responseName, $path, $parentType, $sourceValue, $fieldNodes) {
                return $process($resolvedResults, $responseName, $path, $parentType, $sourceValue, $fieldNodes);
            });
        }

        return $prevPromise->then(function ($resolvedResults) {
            return self::fixResultsIfEmptyArray($resolvedResults);
        });
    }

    /**
     * Implements the "Evaluating selection sets" section of the spec
     * for "read" mode.
     *
     * @param ObjectType $parentType
     * @param $source
     * @param $path
     * @param $fields
     * @return Promise|\stdClass|array
     */
    private function executeFields(ObjectType $parentType, $source, $path, $fields)
    {
        $containsPromise = false;
        $finalResults = [];

        foreach ($fields as $responseName => $fieldNodes) {
            $fieldPath = $path;
            $fieldPath[] = $responseName;
            $result = $this->resolveField($parentType, $source, $fieldNodes, $fieldPath);
            if ($result === self::$UNDEFINED) {
                continue;
            }
            if (!$containsPromise && $this->getPromise($result)) {
                $containsPromise = true;
            }
            $finalResults[$responseName] = $result;
        }

        // If there are no promises, we can just return the object
        if (!$containsPromise) {
            return self::fixResultsIfEmptyArray($finalResults);
        }

        // Otherwise, results is a map from field name to the result
        // of resolving that field, which is possibly a promise. Return
        // a promise that will return this same map, but with any
        // promises replaced with the values they resolved to.
        return $this->promiseForAssocArray($finalResults);
    }

    /**
     * This function transforms a PHP `array<string, Promise|scalar|array>` into
     * a `Promise<array<key,scalar|array>>`
     *
     * In other words it returns a promise which resolves to normal PHP associative array which doesn't contain
     * any promises.
     *
     * @param array $assoc
     * @return mixed
     */
    private function promiseForAssocArray(array $assoc)
    {
        $keys = array_keys($assoc);
        $valuesAndPromises = array_values($assoc);

        $promise = $this->exeContext->promises->all($valuesAndPromises);

        return $promise->then(function($values) use ($keys) {
            $resolvedResults = [];
            foreach ($values as $i => $value) {
                $resolvedResults[$keys[$i]] = $value;
            }
            return self::fixResultsIfEmptyArray($resolvedResults);
        });
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/59
     *
     * @param $results
     * @return \stdClass|array
     */
    private static function fixResultsIfEmptyArray($results)
    {
        if ([] === $results) {
            $results = new \stdClass();
        }

        return $results;
    }

    /**
     * Given a selectionSet, adds all of the fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * CollectFields requires the "runtime type" of an object. For a field which
     * returns an Interface or Union type, the "runtime type" will be the actual
     * Object type returned by that field.
     *
     * @param ObjectType $runtimeType
     * @param SelectionSetNode $selectionSet
     * @param $fields
     * @param $visitedFragmentNames
     *
     * @return \ArrayObject
     */
    private function collectFields(
        ObjectType $runtimeType,
        SelectionSetNode $selectionSet,
        $fields,
        $visitedFragmentNames
    )
    {
        $exeContext = $this->exeContext;
        foreach ($selectionSet->selections as $selection) {
            switch ($selection->kind) {
                case NodeKind::FIELD:
                    if (!$this->shouldIncludeNode($selection)) {
                        continue;
                    }
                    $name = self::getFieldEntryKey($selection);
                    if (!isset($fields[$name])) {
                        $fields[$name] = new \ArrayObject();
                    }
                    $fields[$name][] = $selection;
                    break;
                case NodeKind::INLINE_FRAGMENT:
                    if (!$this->shouldIncludeNode($selection) ||
                        !$this->doesFragmentConditionMatch($selection, $runtimeType)
                    ) {
                        continue;
                    }
                    $this->collectFields(
                        $runtimeType,
                        $selection->selectionSet,
                        $fields,
                        $visitedFragmentNames
                    );
                    break;
                case NodeKind::FRAGMENT_SPREAD:
                    $fragName = $selection->name->value;
                    if (!empty($visitedFragmentNames[$fragName]) || !$this->shouldIncludeNode($selection)) {
                        continue;
                    }
                    $visitedFragmentNames[$fragName] = true;

                    /** @var FragmentDefinitionNode|null $fragment */
                    $fragment = isset($exeContext->fragments[$fragName]) ? $exeContext->fragments[$fragName] : null;
                    if (!$fragment || !$this->doesFragmentConditionMatch($fragment, $runtimeType)) {
                        continue;
                    }
                    $this->collectFields(
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
     *
     * @param FragmentSpreadNode | FieldNode | InlineFragmentNode $node
     * @return bool
     */
    private function shouldIncludeNode($node)
    {
        $variableValues = $this->exeContext->variableValues;
        $skipDirective = Directive::skipDirective();

        $skip = Values::getDirectiveValues(
            $skipDirective,
            $node,
            $variableValues
        );

        if (isset($skip['if']) && $skip['if'] === true) {
            return false;
        }

        $includeDirective = Directive::includeDirective();

        $include = Values::getDirectiveValues(
            $includeDirective,
            $node,
            $variableValues
        );

        if (isset($include['if']) && $include['if'] === false) {
            return false;
        }
        return true;
    }

    /**
     * Determines if a fragment is applicable to the given type.
     *
     * @param $fragment
     * @param ObjectType $type
     * @return bool
     */
    private function doesFragmentConditionMatch(/* FragmentDefinitionNode | InlineFragmentNode*/ $fragment, ObjectType $type)
    {
        $typeConditionNode = $fragment->typeCondition;

        if (!$typeConditionNode) {
            return true;
        }

        $conditionalType = TypeInfo::typeFromAST($this->exeContext->schema, $typeConditionNode);
        if ($conditionalType === $type) {
            return true;
        }
        if ($conditionalType instanceof AbstractType) {
            return $this->exeContext->schema->isPossibleType($conditionalType, $type);
        }
        return false;
    }

    /**
     * Implements the logic to compute the key of a given fields entry
     *
     * @param FieldNode $node
     * @return string
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
     *
     * @param ObjectType $parentType
     * @param $source
     * @param $fieldNodes
     * @param $path
     *
     * @return array|\Exception|mixed|null
     */
    private function resolveField(ObjectType $parentType, $source, $fieldNodes, $path)
    {
        $exeContext = $this->exeContext;
        $fieldNode = $fieldNodes[0];

        $fieldName = $fieldNode->name->value;
        $fieldDef = $this->getFieldDef($exeContext->schema, $parentType, $fieldName);

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
            $resolveFn = $this->exeContext->fieldResolver;
        }

        // The resolve function's optional third argument is a context value that
        // is provided to every resolve function within an execution. It is commonly
        // used to represent an authenticated user, or request-specific caches.
        $context = $exeContext->contextValue;

        // Get the resolve function, regardless of if its result is normal
        // or abrupt (error).
        $result = $this->resolveOrError(
            $fieldDef,
            $fieldNode,
            $resolveFn,
            $source,
            $context,
            $info
        );

        $result = $this->completeValueCatchingError(
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
     * @param FieldDefinition $fieldDef
     * @param FieldNode $fieldNode
     * @param callable $resolveFn
     * @param mixed $source
     * @param mixed $context
     * @param ResolveInfo $info
     * @return \Throwable|Promise|mixed
     */
    private function resolveOrError($fieldDef, $fieldNode, $resolveFn, $source, $context, $info)
    {
        try {
            // Build hash of arguments from the field.arguments AST, using the
            // variables scope to fulfill any variable references.
            $args = Values::getArgumentValues(
                $fieldDef,
                $fieldNode,
                $this->exeContext->variableValues
            );

            return $resolveFn($source, $args, $context, $info);
        } catch (\Exception $error) {
            return $error;
        } catch (\Throwable $error) {
            return $error;
        }
    }

    /**
     * This is a small wrapper around completeValue which detects and logs errors
     * in the execution context.
     *
     * @param Type $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param $path
     * @param $result
     * @return array|null|Promise
     */
    private function completeValueCatchingError(
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        $result
    )
    {
        $exeContext = $this->exeContext;

        // If the field type is non-nullable, then it is resolved without any
        // protection from errors.
        if ($returnType instanceof NonNull) {
            return $this->completeValueWithLocatedError(
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
            $completed = $this->completeValueWithLocatedError(
                $returnType,
                $fieldNodes,
                $info,
                $path,
                $result
            );

            $promise = $this->getPromise($completed);
            if ($promise) {
                return $promise->then(null, function ($error) use ($exeContext) {
                    $exeContext->addError($error);
                    return $this->exeContext->promises->createFulfilled(null);
                });
            }
            return $completed;
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
     * @param Type $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param $path
     * @param $result
     * @return array|null|Promise
     * @throws Error
     */
    public function completeValueWithLocatedError(
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        $result
    )
    {
        try {
            $completed = $this->completeValue(
                $returnType,
                $fieldNodes,
                $info,
                $path,
                $result
            );
            $promise = $this->getPromise($completed);
            if ($promise) {
                return $promise->then(null, function ($error) use ($fieldNodes, $path) {
                    return $this->exeContext->promises->createRejected(Error::createLocatedError($error, $fieldNodes, $path));
                });
            }
            return $completed;
        } catch (\Exception $error) {
            throw Error::createLocatedError($error, $fieldNodes, $path);
        } catch (\Throwable $error) {
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
     * @param Type $returnType
     * @param FieldNode[] $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array|null|Promise
     * @throws Error
     * @throws \Throwable
     */
    private function completeValue(
        Type $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        &$result
    )
    {
        $promise = $this->getPromise($result);

        // If result is a Promise, apply-lift over completeValue.
        if ($promise) {
            return $promise->then(function (&$resolved) use ($returnType, $fieldNodes, $info, $path) {
                return $this->completeValue($returnType, $fieldNodes, $info, $path, $resolved);
            });
        }

        if ($result instanceof \Exception || $result instanceof \Throwable) {
            throw $result;
        }

        // If field type is NonNull, complete for inner type, and throw field error
        // if result is null.
        if ($returnType instanceof NonNull) {
            $completed = $this->completeValue(
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
            return $this->completeListValue($returnType, $fieldNodes, $info, $path, $result);
        }

        // Account for invalid schema definition when typeLoader returns different
        // instance than `resolveType` or $field->getType() or $arg->getType()
        if ($returnType !== $this->exeContext->schema->getType($returnType->name)) {
            $hint = "";
            if ($this->exeContext->schema->getConfig()->typeLoader) {
                $hint = "Make sure that type loader returns the same instance as defined in {$info->parentType}.{$info->fieldName}";
            }
            throw new InvariantViolation(
                "Schema must contain unique named types but contains multiple types named \"$returnType\". ".
                "$hint ".
                "(see http://webonyx.github.io/graphql-php/type-system/#type-registry)."
            );
        }

        // If field type is Scalar or Enum, serialize to a valid value, returning
        // null if serialization is not possible.
        if ($returnType instanceof LeafType) {
            return $this->completeLeafValue($returnType, $result);
        }

        if ($returnType instanceof AbstractType) {
            return $this->completeAbstractValue($returnType, $fieldNodes, $info, $path, $result);
        }

        // Field type must be Object, Interface or Union and expect sub-selections.
        if ($returnType instanceof ObjectType) {
            return $this->completeObjectValue($returnType, $fieldNodes, $info, $path, $result);
        }

        throw new \RuntimeException("Cannot complete value of unexpected type \"{$returnType}\".");
    }

    /**
     * If a resolve function is not given, then a default resolve behavior is used
     * which takes the property of the source object of the same name as the field
     * and returns it as the result, or if it's a function, returns the result
     * of calling that function while passing along args and context.
     *
     * @param $source
     * @param $args
     * @param $context
     * @param ResolveInfo $info
     *
     * @return mixed|null
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

        return $property instanceof \Closure ? $property($source, $args, $context, $info) : $property;
    }

    /**
     * This method looks up the field on the given type definition.
     * It has special casing for the two introspection fields, __schema
     * and __typename. __typename is special because it can always be
     * queried as a field, even in situations where no other fields
     * are allowed, like on a Union. __schema could get automatically
     * added to the query type, but that would require mutating type
     * definitions, which would cause issues.
     *
     * @param Schema $schema
     * @param ObjectType $parentType
     * @param $fieldName
     *
     * @return FieldDefinition
     */
    private function getFieldDef(Schema $schema, ObjectType $parentType, $fieldName)
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
     * @param AbstractType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return mixed
     * @throws Error
     */
    private function completeAbstractValue(AbstractType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        $exeContext = $this->exeContext;
        $runtimeType = $returnType->resolveType($result, $exeContext->contextValue, $info);

        if (null === $runtimeType) {
            if ($returnType instanceof InterfaceType && $info->schema->getConfig()->typeLoader) {
                Warning::warnOnce(
                    "GraphQL Interface Type `{$returnType->name}` returned `null` from it`s `resolveType` function ".
                    'for value: ' . Utils::printSafe($result) . '. Switching to slow resolution method using `isTypeOf` ' .
                    'of all possible implementations. It requires full schema scan and degrades query performance significantly. '.
                    ' Make sure your `resolveType` always returns valid implementation or throws.',
                    Warning::WARNING_FULL_SCHEMA_SCAN
                );
            }
            $runtimeType = self::defaultTypeResolver($result, $exeContext->contextValue, $info, $returnType);
        }

        $promise = $this->getPromise($runtimeType);
        if ($promise) {
            return $promise->then(function($resolvedRuntimeType) use ($returnType, $fieldNodes, $info, $path, &$result) {
                return $this->completeObjectValue(
                    $this->ensureValidRuntimeType(
                        $resolvedRuntimeType,
                        $returnType,
                        $fieldNodes,
                        $info,
                        $result
                    ),
                    $fieldNodes,
                    $info,
                    $path,
                    $result
                );
            });
        }

        return $this->completeObjectValue(
            $this->ensureValidRuntimeType(
                $runtimeType,
                $returnType,
                $fieldNodes,
                $info,
                $result
            ),
            $fieldNodes,
            $info,
            $path,
            $result
        );
    }

    /**
     * @param string|ObjectType|null $runtimeTypeOrName
     * @param AbstractType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param $result
     * @return ObjectType
     * @throws Error
     */
    private function ensureValidRuntimeType(
        $runtimeTypeOrName,
        AbstractType $returnType,
        $fieldNodes,
        ResolveInfo $info,
        &$result
    )
    {
        $runtimeType = is_string($runtimeTypeOrName) ?
            $this->exeContext->schema->getType($runtimeTypeOrName) :
            $runtimeTypeOrName;

        if (!$runtimeType instanceof ObjectType) {
            throw new InvariantViolation(
                "Abstract type {$returnType} must resolve to an Object type at runtime " .
                "for field {$info->parentType}.{$info->fieldName} with " .
                'value "' . Utils::printSafe($result) . '", received "'. Utils::printSafe($runtimeType) . '".'
            );
        }

        if (!$this->exeContext->schema->isPossibleType($returnType, $runtimeType)) {
            throw new InvariantViolation(
                "Runtime Object type \"$runtimeType\" is not a possible type for \"$returnType\"."
            );
        }

        if ($runtimeType !== $this->exeContext->schema->getType($runtimeType->name)) {
            throw new InvariantViolation(
                "Schema must contain unique named types but contains multiple types named \"$runtimeType\". ".
                "Make sure that `resolveType` function of abstract type \"{$returnType}\" returns the same ".
                "type instance as referenced anywhere else within the schema " .
                "(see http://webonyx.github.io/graphql-php/type-system/#type-registry)."
            );
        }

        return $runtimeType;
    }

    /**
     * Complete a list value by completing each item in the list with the
     * inner type
     *
     * @param ListOfType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array|Promise
     * @throws \Exception
     */
    private function completeListValue(ListOfType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        $itemType = $returnType->getWrappedType();
        Utils::invariant(
            is_array($result) || $result instanceof \Traversable,
            'User Error: expected iterable, but did not find one for field ' . $info->parentType . '.' . $info->fieldName . '.'
        );
        $containsPromise = false;

        $i = 0;
        $completedItems = [];
        foreach ($result as $item) {
            $fieldPath = $path;
            $fieldPath[] = $i++;
            $completedItem = $this->completeValueCatchingError($itemType, $fieldNodes, $info, $fieldPath, $item);
            if (!$containsPromise && $this->getPromise($completedItem)) {
                $containsPromise = true;
            }
            $completedItems[] = $completedItem;
        }
        return $containsPromise ? $this->exeContext->promises->all($completedItems) : $completedItems;
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
    private function completeLeafValue(LeafType $returnType, &$result)
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
     * @param ObjectType $returnType
     * @param $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param $result
     * @return array|Promise|\stdClass
     * @throws Error
     */
    private function completeObjectValue(ObjectType $returnType, $fieldNodes, ResolveInfo $info, $path, &$result)
    {
        // If there is an isTypeOf predicate function, call it with the
        // current result. If isTypeOf returns false, then raise an error rather
        // than continuing execution.
        $isTypeOf = $returnType->isTypeOf($result, $this->exeContext->contextValue, $info);

        if (null !== $isTypeOf) {
            $promise = $this->getPromise($isTypeOf);
            if ($promise) {
                return $promise->then(function($isTypeOfResult) use ($returnType, $fieldNodes, $info, $path, &$result) {
                    if (!$isTypeOfResult) {
                        throw $this->invalidReturnTypeError($returnType, $result, $fieldNodes);
                    }

                    return $this->collectAndExecuteSubfields(
                        $returnType,
                        $fieldNodes,
                        $info,
                        $path,
                        $result
                    );
                });
            }
            if (!$isTypeOf) {
                throw $this->invalidReturnTypeError($returnType, $result, $fieldNodes);
            }
        }

        return $this->collectAndExecuteSubfields(
            $returnType,
            $fieldNodes,
            $info,
            $path,
            $result
        );
    }

    /**
     * @param ObjectType $returnType
     * @param array $result
     * @param FieldNode[] $fieldNodes
     * @return Error
     */
    private function invalidReturnTypeError(
        ObjectType $returnType,
        $result,
        $fieldNodes
    )
    {
        return new Error(
            'Expected value of type "' . $returnType->name . '" but got: ' . Utils::printSafe($result) . '.',
            $fieldNodes
        );
    }


    /**
     * @param ObjectType $returnType
     * @param FieldNode[] $fieldNodes
     * @param ResolveInfo $info
     * @param array $path
     * @param array $result
     * @return array|Promise|\stdClass
     * @throws Error
     */
    private function collectAndExecuteSubfields(
        ObjectType $returnType,
        $fieldNodes,
        ResolveInfo $info,
        $path,
        &$result
    )
    {
        // Collect sub-fields to execute to complete this value.
        $subFieldNodes = new \ArrayObject();
        $visitedFragmentNames = new \ArrayObject();

        foreach ($fieldNodes as $fieldNode) {
            if (isset($fieldNode->selectionSet)) {
                $subFieldNodes = $this->collectFields(
                    $returnType,
                    $fieldNode->selectionSet,
                    $subFieldNodes,
                    $visitedFragmentNames
                );
            }
        }

        return $this->executeFields($returnType, $result, $path, $subFieldNodes);
    }

    /**
     * If a resolveType function is not given, then a default resolve behavior is
     * used which tests each possible type for the abstract type by calling
     * isTypeOf for the object being coerced, returning the first type that matches.
     *
     * @param $value
     * @param $context
     * @param ResolveInfo $info
     * @param AbstractType $abstractType
     * @return ObjectType|Promise|null
     */
    private function defaultTypeResolver($value, $context, ResolveInfo $info, AbstractType $abstractType)
    {
        $possibleTypes = $info->schema->getPossibleTypes($abstractType);
        $promisedIsTypeOfResults = [];

        foreach ($possibleTypes as $index => $type) {
            $isTypeOfResult = $type->isTypeOf($value, $context, $info);

            if (null !== $isTypeOfResult) {
                $promise = $this->getPromise($isTypeOfResult);
                if ($promise) {
                    $promisedIsTypeOfResults[$index] = $promise;
                } else if ($isTypeOfResult) {
                    return $type;
                }
            }
        }

        if (!empty($promisedIsTypeOfResults)) {
            return $this->exeContext->promises->all($promisedIsTypeOfResults)
                ->then(function($isTypeOfResults) use ($possibleTypes) {
                    foreach ($isTypeOfResults as $index => $result) {
                        if ($result) {
                            return $possibleTypes[$index];
                        }
                    }
                    return null;
                });
        }

        return null;
    }

    /**
     * Only returns the value if it acts like a Promise, i.e. has a "then" function,
     * otherwise returns null.
     *
     * @param mixed $value
     * @return Promise|null
     */
    private function getPromise($value)
    {
        if (null === $value || $value instanceof Promise) {
            return $value;
        }
        if ($this->exeContext->promises->isThenable($value)) {
            $promise = $this->exeContext->promises->convertThenable($value);
            if (!$promise instanceof Promise) {
                throw new InvariantViolation(sprintf(
                    '%s::convertThenable is expected to return instance of GraphQL\Executor\Promise\Promise, got: %s',
                    get_class($this->exeContext->promises),
                    Utils::printSafe($promise)
                ));
            }
            return $promise;
        }
        return null;
    }

    /**
     * @deprecated as of v0.8.0 should use self::defaultFieldResolver method
     *
     * @param $source
     * @param $args
     * @param $context
     * @param ResolveInfo $info
     * @return mixed|null
     */
    public static function defaultResolveFn($source, $args, $context, ResolveInfo $info)
    {
        trigger_error(__METHOD__ . ' is renamed to ' . __CLASS__ . '::defaultFieldResolver', E_USER_DEPRECATED);
        return self::defaultFieldResolver($source, $args, $context, $info);
    }

    /**
     * @deprecated as of v0.8.0 should use self::setDefaultFieldResolver method
     *
     * @param callable $fn
     */
    public static function setDefaultResolveFn($fn)
    {
        trigger_error(__METHOD__ . ' is renamed to ' . __CLASS__ . '::setDefaultFieldResolver', E_USER_DEPRECATED);
        self::setDefaultFieldResolver($fn);
    }
}
