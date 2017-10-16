# GraphQL\GraphQL
This is the primary facade for fulfilling GraphQL operations.
See [related documentation](executing-queries.md).

**Class Methods:** 
```php
/**
 * Executes graphql query.
 *
 * More sophisticated GraphQL servers, such as those which persist queries,
 * may wish to separate the validation and execution phases to a static time
 * tooling step, and a server runtime step.
 *
 * Available options:
 *
 * schema:
 *    The GraphQL type system to use when validating and executing a query.
 * source:
 *    A GraphQL language formatted string representing the requested operation.
 * rootValue:
 *    The value provided as the first argument to resolver functions on the top
 *    level type (e.g. the query object type).
 * context:
 *    The value provided as the third argument to all resolvers.
 *    Use this to pass current session, user data, etc
 * variableValues:
 *    A mapping of variable name to runtime value to use for all variables
 *    defined in the requestString.
 * operationName:
 *    The name of the operation to use if requestString contains multiple
 *    possible operations. Can be omitted if requestString contains only
 *    one operation.
 * fieldResolver:
 *    A resolver function to use when one is not provided by the schema.
 *    If not provided, the default field resolver is used (which looks for a
 *    value on the source value with the field's name).
 * validationRules:
 *    A set of rules for query validation step. Default value is all available rules.
 *    Empty array would allow to skip query validation (may be convenient for persisted
 *    queries which are validated before persisting and assumed valid during execution)
 *
 * @api
 * @param \GraphQL\Type\Schema $schema
 * @param string|DocumentNode $source
 * @param mixed $rootValue
 * @param mixed $context
 * @param array|null $variableValues
 * @param string|null $operationName
 * @param callable $fieldResolver
 * @param array $validationRules
 *
 * @return ExecutionResult
 */
static function executeQuery(
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $context = null,
    $variableValues = null,
    $operationName = null,
    callable $fieldResolver = null,
    array $validationRules = null
)
```

```php
/**
 * Same as executeQuery(), but requires PromiseAdapter and always returns a Promise.
 * Useful for Async PHP platforms.
 *
 * @api
 * @param PromiseAdapter $promiseAdapter
 * @param \GraphQL\Type\Schema $schema
 * @param string|DocumentNode $source
 * @param mixed $rootValue
 * @param mixed $context
 * @param array|null $variableValues
 * @param string|null $operationName
 * @param callable $fieldResolver
 * @param array $validationRules
 *
 * @return Promise
 */
static function promiseToExecute(
    GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter,
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $context = null,
    $variableValues = null,
    $operationName = null,
    callable $fieldResolver = null,
    array $validationRules = null
)
```

```php
/**
 * Returns directives defined in GraphQL spec
 *
 * @api
 * @return Directive[]
 */
static function getStandardDirectives()
```

```php
/**
 * Returns types defined in GraphQL spec
 *
 * @api
 * @return Type[]
 */
static function getStandardTypes()
```

```php
/**
 * Returns standard validation rules implementing GraphQL spec
 *
 * @api
 * @return AbstractValidationRule[]
 */
static function getStandardValidationRules()
```
# GraphQL\Type\Definition\Type
Registry of standard GraphQL types
and a base class for all other types.

**Class Methods:** 
```php
/**
 * @api
 * @return IDType
 */
static function id()
```

```php
/**
 * @api
 * @return StringType
 */
static function string()
```

```php
/**
 * @api
 * @return BooleanType
 */
static function boolean()
```

```php
/**
 * @api
 * @return IntType
 */
static function int()
```

```php
/**
 * @api
 * @return FloatType
 */
static function float()
```

```php
/**
 * @api
 * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType|NonNull $wrappedType
 * @return ListOfType
 */
static function listOf($wrappedType)
```

```php
/**
 * @api
 * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $wrappedType
 * @return NonNull
 */
static function nonNull($wrappedType)
```

```php
/**
 * @api
 * @param Type $type
 * @return bool
 */
static function isInputType($type)
```

```php
/**
 * @api
 * @param Type $type
 * @return bool
 */
static function isOutputType($type)
```

```php
/**
 * @api
 * @param $type
 * @return bool
 */
static function isLeafType($type)
```

```php
/**
 * @api
 * @param Type $type
 * @return bool
 */
static function isCompositeType($type)
```

```php
/**
 * @api
 * @param Type $type
 * @return bool
 */
static function isAbstractType($type)
```

```php
/**
 * @api
 * @param Type $type
 * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
 */
static function getNullableType($type)
```

```php
/**
 * @api
 * @param Type $type
 * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
 */
static function getNamedType($type)
```
# GraphQL\Type\Definition\ResolveInfo
Structure containing information useful for field resolution process.
Passed as 3rd argument to every field resolver. See [docs on field resolving (data fetching)](data-fetching.md).

**Class Props:** 
```php
/**
 * The name of the field being resolved
 *
 * @api
 * @var string
 */
public $fieldName;

/**
 * AST of all nodes referencing this field in the query.
 *
 * @api
 * @var FieldNode[]
 */
public $fieldNodes;

/**
 * Expected return type of the field being resolved
 *
 * @api
 * @var ScalarType|ObjectType|InterfaceType|UnionType|EnumType|ListOfType|NonNull
 */
public $returnType;

/**
 * Parent type of the field being resolved
 *
 * @api
 * @var ObjectType
 */
public $parentType;

/**
 * Path to this field from the very root value
 *
 * @api
 * @var array
 */
public $path;

/**
 * Instance of a schema used for execution
 *
 * @api
 * @var Schema
 */
public $schema;

/**
 * AST of all fragments defined in query
 *
 * @api
 * @var FragmentDefinitionNode[]
 */
public $fragments;

/**
 * Root value passed to query execution
 *
 * @api
 * @var mixed
 */
public $rootValue;

/**
 * AST of operation definition node (query, mutation)
 *
 * @api
 * @var OperationDefinitionNode
 */
public $operation;

/**
 * Array of variables passed to query execution
 *
 * @api
 * @var array
 */
public $variableValues;
```

**Class Methods:** 
```php
/**
 * Helper method that returns names of all fields selected in query for
 * $this->fieldName up to $depth levels
 *
 * Example:
 * query MyQuery{
 * {
 *   root {
 *     id,
 *     nested {
 *      nested1
 *      nested2 {
 *        nested3
 *      }
 *     }
 *   }
 * }
 *
 * Given this ResolveInfo instance is a part of "root" field resolution, and $depth === 1,
 * method will return:
 * [
 *     'id' => true,
 *     'nested' => [
 *         nested1 => true,
 *         nested2 => true
 *     ]
 * ]
 *
 * Warning: this method it is a naive implementation which does not take into account
 * conditional typed fragments. So use it with care for fields of interface and union types.
 *
 * @api
 * @param int $depth How many levels to include in output
 * @return array
 */
function getFieldSelection($depth = 0)
```
# GraphQL\Type\Definition\DirectiveLocation
List of available directive locations

**Class Constants:** 
```php
const IFACE = "INTERFACE";
const SUBSCRIPTION = "SUBSCRIPTION";
const FRAGMENT_SPREAD = "FRAGMENT_SPREAD";
const QUERY = "QUERY";
const MUTATION = "MUTATION";
const FRAGMENT_DEFINITION = "FRAGMENT_DEFINITION";
const INPUT_OBJECT = "INPUT_OBJECT";
const INLINE_FRAGMENT = "INLINE_FRAGMENT";
const UNION = "UNION";
const SCALAR = "SCALAR";
const FIELD_DEFINITION = "FIELD_DEFINITION";
const ARGUMENT_DEFINITION = "ARGUMENT_DEFINITION";
const ENUM = "ENUM";
const OBJECT = "OBJECT";
const ENUM_VALUE = "ENUM_VALUE";
const FIELD = "FIELD";
const SCHEMA = "SCHEMA";
const INPUT_FIELD_DEFINITION = "INPUT_FIELD_DEFINITION";
```

# GraphQL\Type\SchemaConfig
Schema configuration class.
Could be passed directly to schema constructor. List of options accepted by **create** method is
[described in docs](type-system/schema.md#configuration-options).

Usage example:

    $config = SchemaConfig::create()
        ->setQuery($myQueryType)
        ->setTypeLoader($myTypeLoader);

    $schema = new Schema($config);

**Class Methods:** 
```php
/**
 * Converts an array of options to instance of SchemaConfig
 * (or just returns empty config when array is not passed).
 *
 * @api
 * @param array $options
 * @return SchemaConfig
 */
static function create(array $options = [])
```

```php
/**
 * @api
 * @param ObjectType $query
 * @return SchemaConfig
 */
function setQuery(GraphQL\Type\Definition\ObjectType $query)
```

```php
/**
 * @api
 * @param ObjectType $mutation
 * @return SchemaConfig
 */
function setMutation(GraphQL\Type\Definition\ObjectType $mutation)
```

```php
/**
 * @api
 * @param ObjectType $subscription
 * @return SchemaConfig
 */
function setSubscription(GraphQL\Type\Definition\ObjectType $subscription)
```

```php
/**
 * @api
 * @param Type[]|callable $types
 * @return SchemaConfig
 */
function setTypes($types)
```

```php
/**
 * @api
 * @param Directive[] $directives
 * @return SchemaConfig
 */
function setDirectives(array $directives)
```

```php
/**
 * @api
 * @param callable $typeLoader
 * @return SchemaConfig
 */
function setTypeLoader(callable $typeLoader)
```

```php
/**
 * @api
 * @return ObjectType
 */
function getQuery()
```

```php
/**
 * @api
 * @return ObjectType
 */
function getMutation()
```

```php
/**
 * @api
 * @return ObjectType
 */
function getSubscription()
```

```php
/**
 * @api
 * @return Type[]
 */
function getTypes()
```

```php
/**
 * @api
 * @return Directive[]
 */
function getDirectives()
```

```php
/**
 * @api
 * @return callable
 */
function getTypeLoader()
```
# GraphQL\Type\Schema
Schema Definition (see [related docs](type-system/schema.md))

A Schema is created by supplying the root types of each type of operation:
query, mutation (optional) and subscription (optional). A schema definition is
then supplied to the validator and executor. Usage Example:

    $schema = new GraphQL\Type\Schema([
      'query' => $MyAppQueryRootType,
      'mutation' => $MyAppMutationRootType,
    ]);

Or using Schema Config instance:

    $config = GraphQL\Type\SchemaConfig::create()
        ->setQuery($MyAppQueryRootType)
        ->setMutation($MyAppMutationRootType);

    $schema = new GraphQL\Type\Schema($config);

**Class Methods:** 
```php
/**
 * Schema constructor.
 *
 * @api
 * @param array|SchemaConfig $config
 */
function __construct($config)
```

```php
/**
 * Returns schema query type
 *
 * @api
 * @return ObjectType
 */
function getQueryType()
```

```php
/**
 * Returns schema mutation type
 *
 * @api
 * @return ObjectType|null
 */
function getMutationType()
```

```php
/**
 * Returns schema subscription
 *
 * @api
 * @return ObjectType|null
 */
function getSubscriptionType()
```

```php
/**
 * @api
 * @return SchemaConfig
 */
function getConfig()
```

```php
/**
 * Returns array of all types in this schema. Keys of this array represent type names, values are instances
 * of corresponding type definitions
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @api
 * @return Type[]
 */
function getTypeMap()
```

```php
/**
 * Returns type by it's name
 *
 * @api
 * @param string $name
 * @return Type
 */
function getType($name)
```

```php
/**
 * Returns all possible concrete types for given abstract type
 * (implementations for interfaces and members of union type for unions)
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @api
 * @param AbstractType $abstractType
 * @return ObjectType[]
 */
function getPossibleTypes(GraphQL\Type\Definition\AbstractType $abstractType)
```

```php
/**
 * Returns true if object type is concrete type of given abstract type
 * (implementation for interfaces and members of union type for unions)
 *
 * @api
 * @param AbstractType $abstractType
 * @param ObjectType $possibleType
 * @return bool
 */
function isPossibleType(
    GraphQL\Type\Definition\AbstractType $abstractType,
    GraphQL\Type\Definition\ObjectType $possibleType
)
```

```php
/**
 * Returns a list of directives supported by this schema
 *
 * @api
 * @return Directive[]
 */
function getDirectives()
```

```php
/**
 * Returns instance of directive by name
 *
 * @api
 * @param $name
 * @return Directive
 */
function getDirective($name)
```

```php
/**
 * Validates schema.
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @api
 * @throws InvariantViolation
 */
function assertValid()
```
# GraphQL\Language\Parser
Parses string containing GraphQL query or [type definition](type-system/type-language.md) to Abstract Syntax Tree.

**Class Methods:** 
```php
/**
 * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * Available options:
 *
 * noLocation: boolean,
 * (By default, the parser creates AST nodes that know the location
 * in the source that they correspond to. This configuration flag
 * disables that behavior for performance or testing.)
 *
 * @api
 * @param Source|string $source
 * @param array $options
 * @return DocumentNode
 */
static function parse($source, array $options = [])
```

```php
/**
 * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
 * that value.
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * This is useful within tools that operate upon GraphQL Values directly and
 * in isolation of complete GraphQL documents.
 *
 * Consider providing the results to the utility function: `GraphQL\Utils\AST::valueFromAST()`.
 *
 * @api
 * @param Source|string $source
 * @param array $options
 * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
 */
static function parseValue($source, array $options = [])
```

```php
/**
 * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
 * that type.
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * This is useful within tools that operate upon GraphQL Types directly and
 * in isolation of complete GraphQL documents.
 *
 * Consider providing the results to the utility function: `GraphQL\Utils\AST::typeFromAST()`.
 *
 * @api
 * @param Source|string $source
 * @param array $options
 * @return ListTypeNode|NameNode|NonNullTypeNode
 */
static function parseType($source, array $options = [])
```
# GraphQL\Language\Printer
Prints AST to string. Capable of printing GraphQL queries and Type definition language.
Useful for pretty-printing queries or printing back AST for logging, documentation, etc.

Usage example:

```php
$query = 'query myQuery {someField}';
$ast = GraphQL\Language\Parser::parse($query);
$printed = GraphQL\Language\Printer::doPrint($ast);
```

**Class Methods:** 
```php
/**
 * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
 *
 * @api
 * @param Node $ast
 * @return string
 */
static function doPrint($ast)
```
# GraphQL\Language\Visitor
Utility for efficient AST traversal and modification.

`visit()` will walk through an AST using a depth first traversal, calling
the visitor's enter function at each node in the traversal, and calling the
leave function after visiting that node and all of it's child nodes.

By returning different values from the enter and leave functions, the
behavior of the visitor can be altered, including skipping over a sub-tree of
the AST (by returning false), editing the AST by returning a value or null
to remove the value, or to stop the whole traversal by returning BREAK.

When using `visit()` to edit an AST, the original AST will not be modified, and
a new version of the AST with the changes applied will be returned from the
visit function.

    $editedAST = Visitor::visit($ast, [
      'enter' => function ($node, $key, $parent, $path, $ancestors) {
        // return
        //   null: no action
        //   Visitor::skipNode(): skip visiting this node
        //   Visitor::stop(): stop visiting altogether
        //   Visitor::removeNode(): delete this node
        //   any value: replace this node with the returned value
      },
      'leave' => function ($node, $key, $parent, $path, $ancestors) {
        // return
        //   null: no action
        //   Visitor::stop(): stop visiting altogether
        //   Visitor::removeNode(): delete this node
        //   any value: replace this node with the returned value
      }
    ]);

Alternatively to providing enter() and leave() functions, a visitor can
instead provide functions named the same as the [kinds of AST nodes](reference.md#graphqllanguageastnodekind),
or enter/leave visitors at a named key, leading to four permutations of
visitor API:

1) Named visitors triggered when entering a node a specific kind.

    Visitor::visit($ast, [
      'Kind' => function ($node) {
        // enter the "Kind" node
      }
    ]);

2) Named visitors that trigger upon entering and leaving a node of
   a specific kind.

    Visitor::visit($ast, [
      'Kind' => [
        'enter' => function ($node) {
          // enter the "Kind" node
        }
        'leave' => function ($node) {
          // leave the "Kind" node
        }
      ]
    ]);

3) Generic visitors that trigger upon entering and leaving any node.

    Visitor::visit($ast, [
      'enter' => function ($node) {
        // enter any node
      },
      'leave' => function ($node) {
        // leave any node
      }
    ]);

4) Parallel visitors for entering and leaving nodes of a specific kind.

    Visitor::visit($ast, [
      'enter' => [
        'Kind' => function($node) {
          // enter the "Kind" node
        }
      },
      'leave' => [
        'Kind' => function ($node) {
          // leave the "Kind" node
        }
      ]
    ]);

**Class Methods:** 
```php
/**
 * Visit the AST (see class description for details)
 *
 * @api
 * @param Node $root
 * @param array $visitor
 * @param array $keyMap
 * @return Node|mixed
 * @throws \Exception
 */
static function visit($root, $visitor, $keyMap = null)
```

```php
/**
 * Returns marker for visitor break
 *
 * @api
 * @return VisitorOperation
 */
static function stop()
```

```php
/**
 * Returns marker for skipping current node
 *
 * @api
 * @return VisitorOperation
 */
static function skipNode()
```

```php
/**
 * Returns marker for removing a node
 *
 * @api
 * @return VisitorOperation
 */
static function removeNode()
```
# GraphQL\Language\AST\NodeKind


**Class Constants:** 
```php
const NAME = "Name";
const DOCUMENT = "Document";
const OPERATION_DEFINITION = "OperationDefinition";
const VARIABLE_DEFINITION = "VariableDefinition";
const VARIABLE = "Variable";
const SELECTION_SET = "SelectionSet";
const FIELD = "Field";
const ARGUMENT = "Argument";
const FRAGMENT_SPREAD = "FragmentSpread";
const INLINE_FRAGMENT = "InlineFragment";
const FRAGMENT_DEFINITION = "FragmentDefinition";
const INT = "IntValue";
const FLOAT = "FloatValue";
const STRING = "StringValue";
const BOOLEAN = "BooleanValue";
const ENUM = "EnumValue";
const NULL = "NullValue";
const LST = "ListValue";
const OBJECT = "ObjectValue";
const OBJECT_FIELD = "ObjectField";
const DIRECTIVE = "Directive";
const NAMED_TYPE = "NamedType";
const LIST_TYPE = "ListType";
const NON_NULL_TYPE = "NonNullType";
const SCHEMA_DEFINITION = "SchemaDefinition";
const OPERATION_TYPE_DEFINITION = "OperationTypeDefinition";
const SCALAR_TYPE_DEFINITION = "ScalarTypeDefinition";
const OBJECT_TYPE_DEFINITION = "ObjectTypeDefinition";
const FIELD_DEFINITION = "FieldDefinition";
const INPUT_VALUE_DEFINITION = "InputValueDefinition";
const INTERFACE_TYPE_DEFINITION = "InterfaceTypeDefinition";
const UNION_TYPE_DEFINITION = "UnionTypeDefinition";
const ENUM_TYPE_DEFINITION = "EnumTypeDefinition";
const ENUM_VALUE_DEFINITION = "EnumValueDefinition";
const INPUT_OBJECT_TYPE_DEFINITION = "InputObjectTypeDefinition";
const TYPE_EXTENSION_DEFINITION = "TypeExtensionDefinition";
const DIRECTIVE_DEFINITION = "DirectiveDefinition";
```

# GraphQL\Executor\Executor
Implements the "Evaluating requests" section of the GraphQL specification.

**Class Methods:** 
```php
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
static function execute(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $ast,
    $rootValue = null,
    $contextValue = null,
    $variableValues = null,
    $operationName = null,
    callable $fieldResolver = null
)
```

```php
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
static function promiseToExecute(
    GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter,
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $ast,
    $rootValue = null,
    $contextValue = null,
    $variableValues = null,
    $operationName = null,
    callable $fieldResolver = null
)
```
# GraphQL\Executor\ExecutionResult
Returned after [query execution](executing-queries.md).
Represents both - result of successful execution and of a failed one
(with errors collected in `errors` prop)

Could be converted to [spec-compliant](https://facebook.github.io/graphql/#sec-Response-Format)
serializable array using `toArray()`

**Class Props:** 
```php
/**
 * Data collected from resolvers during query execution
 *
 * @api
 * @var array
 */
public $data;

/**
 * Errors registered during query execution.
 *
 * If an error was caused by exception thrown in resolver, $error->getPrevious() would
 * contain original exception.
 *
 * @api
 * @var \GraphQL\Error\Error[]
 */
public $errors;

/**
 * User-defined serializable array of extensions included in serialized result.
 * Conforms to
 *
 * @api
 * @var array
 */
public $extensions;
```

**Class Methods:** 
```php
/**
 * Define custom error formatting (must conform to http://facebook.github.io/graphql/#sec-Errors)
 *
 * Expected signature is: function (GraphQL\Error\Error $error): array
 *
 * Default formatter is "GraphQL\Error\FormattedError::createFromException"
 *
 * Expected returned value must be an array:
 * array(
 *    'message' => 'errorMessage',
 *    // ... other keys
 * );
 *
 * @api
 * @param callable $errorFormatter
 * @return $this
 */
function setErrorFormatter(callable $errorFormatter)
```

```php
/**
 * Define custom logic for error handling (filtering, logging, etc).
 *
 * Expected handler signature is: function (array $errors, callable $formatter): array
 *
 * Default handler is:
 * function (array $errors, callable $formatter) {
 *     return array_map($formatter, $errors);
 * }
 *
 * @api
 * @param callable $handler
 * @return $this
 */
function setErrorsHandler(callable $handler)
```

```php
/**
 * Converts GraphQL query result to spec-compliant serializable array using provided
 * errors handler and formatter.
 *
 * If debug argument is passed, output of error formatter is enriched which debugging information
 * ("debugMessage", "trace" keys depending on flags).
 *
 * $debug argument must be either bool (only adds "debugMessage" to result) or sum of flags from
 * GraphQL\Error\Debug
 *
 * @api
 * @param bool|int $debug
 * @return array
 */
function toArray($debug = false)
```
# GraphQL\Executor\Promise\PromiseAdapter
Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php))

**Interface Methods:** 
```php
/**
 * Return true if the value is a promise or a deferred of the underlying platform
 *
 * @api
 * @param mixed $value
 * @return bool
 */
function isThenable($value)
```

```php
/**
 * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance
 *
 * @api
 * @param object $thenable
 * @return Promise
 */
function convertThenable($thenable)
```

```php
/**
 * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
 * in Promises/A+ specs. Then returns new wrapped instance of GraphQL\Executor\Promise\Promise.
 *
 * @api
 * @param Promise $promise
 * @param callable|null $onFulfilled
 * @param callable|null $onRejected
 *
 * @return Promise
 */
function then(
    GraphQL\Executor\Promise\Promise $promise,
    callable $onFulfilled = null,
    callable $onRejected = null
)
```

```php
/**
 * Creates a Promise
 *
 * Expected resolver signature:
 *     function(callable $resolve, callable $reject)
 *
 * @api
 * @param callable $resolver
 * @return Promise
 */
function create(callable $resolver)
```

```php
/**
 * Creates a fulfilled Promise for a value if the value is not a promise.
 *
 * @api
 * @param mixed $value
 * @return Promise
 */
function createFulfilled($value = null)
```

```php
/**
 * Creates a rejected promise for a reason if the reason is not a promise. If
 * the provided reason is a promise, then it is returned as-is.
 *
 * @api
 * @param \Throwable $reason
 * @return Promise
 */
function createRejected($reason)
```

```php
/**
 * Given an array of promises (or values), returns a promise that is fulfilled when all the
 * items in the array are fulfilled.
 *
 * @api
 * @param array $promisesOrValues Promises or values.
 * @return Promise
 */
function all(array $promisesOrValues)
```
# GraphQL\Validator\DocumentValidator
Implements the "Validation" section of the spec.

Validation runs synchronously, returning an array of encountered errors, or
an empty array if no errors were encountered and the document is valid.

A list of specific validation rules may be provided. If not provided, the
default list of rules defined by the GraphQL specification will be used.

Each validation rule is an instance of GraphQL\Validator\Rules\AbstractValidationRule
which returns a visitor (see the [GraphQL\Language\Visitor API](reference.md#graphqllanguagevisitor)).

Visitor methods are expected to return an instance of [GraphQL\Error\Error](reference.md#graphqlerrorerror),
or array of such instances when invalid.

Optionally a custom TypeInfo instance may be provided. If not provided, one
will be created from the provided schema.

**Class Methods:** 
```php
/**
 * Primary method for query validation. See class description for details.
 *
 * @api
 * @param Schema $schema
 * @param DocumentNode $ast
 * @param AbstractValidationRule[]|null $rules
 * @param TypeInfo|null $typeInfo
 * @return Error[]
 */
static function validate(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $ast,
    array $rules = null,
    GraphQL\Utils\TypeInfo $typeInfo = null
)
```

```php
/**
 * Returns all global validation rules.
 *
 * @api
 * @return AbstractValidationRule[]
 */
static function allRules()
```

```php
/**
 * Returns global validation rule by name. Standard rules are named by class name, so
 * example usage for such rules:
 *
 * $rule = DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
 *
 * @api
 * @param string $name
 * @return AbstractValidationRule
 */
static function getRule($name)
```

```php
/**
 * Add rule to list of global validation rules
 *
 * @api
 * @param AbstractValidationRule $rule
 */
static function addRule(GraphQL\Validator\Rules\AbstractValidationRule $rule)
```
# GraphQL\Error\Error
Describes an Error found during the parse, validate, or
execute phases of performing a GraphQL operation. In addition to a message
and stack trace, it also includes information about the locations in a
GraphQL document and/or execution result that correspond to the Error.

When the error was caused by an exception thrown in resolver, original exception
is available via `getPrevious()`.

Also read related docs on [error handling](error-handling.md)

Class extends standard PHP `\Exception`, so all standard methods of base `\Exception` class
are available in addition to those listed below.

**Class Constants:** 
```php
const CATEGORY_GRAPHQL = "graphql";
const CATEGORY_INTERNAL = "internal";
```

**Class Methods:** 
```php
/**
 * An array of locations within the source GraphQL document which correspond to this error.
 *
 * Each entry has information about `line` and `column` within source GraphQL document:
 * $location->line;
 * $location->column;
 *
 * Errors during validation often contain multiple locations, for example to
 * point out to field mentioned in multiple fragments. Errors during execution include a
 * single location, the field which produced the error.
 *
 * @api
 * @return SourceLocation[]
 */
function getLocations()
```

```php
/**
 * Returns an array describing the path from the root value to the field which produced this error.
 * Only included for execution errors.
 *
 * @api
 * @return array|null
 */
function getPath()
```
# GraphQL\Error\Warning
Encapsulates warnings produced by the library.

Warnings can be suppressed (individually or all) if required.
Also it is possible to override warning handler (which is **trigger_error()** by default)

**Class Constants:** 
```php
const WARNING_NAME = 1;
const WARNING_ASSIGN = 2;
const WARNING_CONFIG = 4;
const WARNING_FULL_SCHEMA_SCAN = 8;
const WARNING_CONFIG_DEPRECATION = 16;
const WARNING_NOT_A_TYPE = 32;
const ALL = 63;
```

**Class Methods:** 
```php
/**
 * Sets warning handler which can intercept all system warnings.
 * When not set, trigger_error() is used to notify about warnings.
 *
 * @api
 * @param callable|null $warningHandler
 */
static function setWarningHandler(callable $warningHandler = null)
```

```php
/**
 * Suppress warning by id (has no effect when custom warning handler is set)
 *
 * Usage example:
 * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
 *
 * When passing true - suppresses all warnings.
 *
 * @api
 * @param bool|int $suppress
 */
static function suppress($suppress = false)
```

```php
/**
 * Re-enable previously suppressed warning by id
 *
 * Usage example:
 * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
 *
 * When passing true - re-enables all warnings.
 *
 * @api
 * @param bool|int $enable
 */
static function enable($enable = false)
```
# GraphQL\Error\ClientAware
This interface is used for [default error formatting](error-handling.md).

Only errors implementing this interface (and returning true from `isClientSafe()`)
will be formatted with original error message.

All other errors will be formatted with generic "Internal server error".

**Interface Methods:** 
```php
/**
 * Returns true when exception message is safe to be displayed to a client.
 *
 * @api
 * @return bool
 */
function isClientSafe()
```

```php
/**
 * Returns string describing a category of the error.
 *
 * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
 *
 * @api
 * @return string
 */
function getCategory()
```
# GraphQL\Error\Debug
Collection of flags for [error debugging](error-handling.md#debugging-tools).

**Class Constants:** 
```php
const INCLUDE_DEBUG_MESSAGE = 1;
const INCLUDE_TRACE = 2;
const RETHROW_INTERNAL_EXCEPTIONS = 4;
```

# GraphQL\Error\FormattedError
This class is used for [default error formatting](error-handling.md).
It converts PHP exceptions to [spec-compliant errors](https://facebook.github.io/graphql/#sec-Errors)
and provides tools for error debugging.

**Class Methods:** 
```php
/**
 * Set default error message for internal errors formatted using createFormattedError().
 * This value can be overridden by passing 3rd argument to `createFormattedError()`.
 *
 * @api
 * @param string $msg
 */
static function setInternalErrorMessage($msg)
```

```php
/**
 * Standard GraphQL error formatter. Converts any exception to array
 * conforming to GraphQL spec.
 *
 * This method only exposes exception message when exception implements ClientAware interface
 * (or when debug flags are passed).
 *
 * For a list of available debug flags see GraphQL\Error\Debug constants.
 *
 * @api
 * @param \Throwable $e
 * @param bool|int $debug
 * @param string $internalErrorMessage
 * @return array
 * @throws \Throwable
 */
static function createFromException($e, $debug = false, $internalErrorMessage = null)
```

```php
/**
 * Returns error trace as serializable array
 *
 * @api
 * @param \Throwable $error
 * @return array
 */
static function toSafeTrace($error)
```
# GraphQL\Server\StandardServer
GraphQL server compatible with both: [express-graphql](https://github.com/graphql/express-graphql)
and [Apollo Server](https://github.com/apollographql/graphql-server).
Usage Example:

    $server = new StandardServer([
      'schema' => $mySchema
    ]);
    $server->handleRequest();

Or using [ServerConfig](reference.md#graphqlserverserverconfig) instance:

    $config = GraphQL\Server\ServerConfig::create()
        ->setSchema($mySchema)
        ->setContext($myContext);

    $server = new GraphQL\Server\StandardServer($config);
    $server->handleRequest();

See [dedicated section in docs](executing-queries.md#using-server) for details.

**Class Methods:** 
```php
/**
 * Converts and exception to error and sends spec-compliant HTTP 500 error.
 * Useful when an exception is thrown somewhere outside of server execution context
 * (e.g. during schema instantiation).
 *
 * @api
 * @param \Throwable $error
 * @param bool $debug
 * @param bool $exitWhenDone
 */
static function send500Error($error, $debug = false, $exitWhenDone = false)
```

```php
/**
 * Creates new instance of a standard GraphQL HTTP server
 *
 * @api
 * @param ServerConfig|array $config
 */
function __construct($config)
```

```php
/**
 * Parses HTTP request, executes and emits response (using standard PHP `header` function and `echo`)
 *
 * By default (when $parsedBody is not set) it uses PHP globals to parse a request.
 * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
 * and then pass it to the server.
 *
 * See `executeRequest()` if you prefer to emit response yourself
 * (e.g. using Response object of some framework)
 *
 * @api
 * @param OperationParams|OperationParams[] $parsedBody
 * @param bool $exitWhenDone
 */
function handleRequest($parsedBody = null, $exitWhenDone = false)
```

```php
/**
 * Executes GraphQL operation and returns execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter).
 *
 * By default (when $parsedBody is not set) it uses PHP globals to parse a request.
 * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
 * and then pass it to the server.
 *
 * PSR-7 compatible method executePsrRequest() does exactly this.
 *
 * @api
 * @param OperationParams|OperationParams[] $parsedBody
 * @return ExecutionResult|ExecutionResult[]|Promise
 * @throws InvariantViolation
 */
function executeRequest($parsedBody = null)
```

```php
/**
 * Executes PSR-7 request and fulfills PSR-7 response.
 *
 * See `executePsrRequest()` if you prefer to create response yourself
 * (e.g. using specific JsonResponse instance of some framework).
 *
 * @api
 * @param ServerRequestInterface $request
 * @param ResponseInterface $response
 * @param StreamInterface $writableBodyStream
 * @return ResponseInterface|Promise
 */
function processPsrRequest(
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    Psr\Http\Message\StreamInterface $writableBodyStream
)
```

```php
/**
 * Executes GraphQL operation and returns execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter)
 *
 * @api
 * @param ServerRequestInterface $request
 * @return ExecutionResult|ExecutionResult[]|Promise
 */
function executePsrRequest(Psr\Http\Message\ServerRequestInterface $request)
```

```php
/**
 * Returns an instance of Server helper, which contains most of the actual logic for
 * parsing / validating / executing request (which could be re-used by other server implementations)
 *
 * @api
 * @return Helper
 */
function getHelper()
```
# GraphQL\Server\ServerConfig
Server configuration class.
Could be passed directly to server constructor. List of options accepted by **create** method is
[described in docs](executing-queries.md#server-configuration-options).

Usage example:

    $config = GraphQL\Server\ServerConfig::create()
        ->setSchema($mySchema)
        ->setContext($myContext);

    $server = new GraphQL\Server\StandardServer($config);

**Class Methods:** 
```php
/**
 * Converts an array of options to instance of ServerConfig
 * (or just returns empty config when array is not passed).
 *
 * @api
 * @param array $config
 * @return ServerConfig
 */
static function create(array $config = [])
```

```php
/**
 * @api
 * @param Schema $schema
 * @return $this
 */
function setSchema(GraphQL\Type\Schema $schema)
```

```php
/**
 * @api
 * @param mixed|\Closure $context
 * @return $this
 */
function setContext($context)
```

```php
/**
 * @api
 * @param mixed|\Closure $rootValue
 * @return $this
 */
function setRootValue($rootValue)
```

```php
/**
 * Expects function(Throwable $e) : array
 *
 * @api
 * @param callable $errorFormatter
 * @return $this
 */
function setErrorFormatter(callable $errorFormatter)
```

```php
/**
 * Expects function(array $errors, callable $formatter) : array
 *
 * @api
 * @param callable $handler
 * @return $this
 */
function setErrorsHandler(callable $handler)
```

```php
/**
 * Set validation rules for this server.
 *
 * @api
 * @param array|callable
 * @return $this
 */
function setValidationRules($validationRules)
```

```php
/**
 * @api
 * @param callable $fieldResolver
 * @return $this
 */
function setFieldResolver(callable $fieldResolver)
```

```php
/**
 * Expects function($queryId, OperationParams $params) : string|DocumentNode
 *
 * This function must return query string or valid DocumentNode.
 *
 * @api
 * @param callable $persistentQueryLoader
 * @return $this
 */
function setPersistentQueryLoader(callable $persistentQueryLoader)
```

```php
/**
 * Set response debug flags. See GraphQL\Error\Debug class for a list of all available flags
 *
 * @api
 * @param bool|int $set
 * @return $this
 */
function setDebug($set = false)
```

```php
/**
 * Allow batching queries (disabled by default)
 *
 * @api
 * @param bool $enableBatching
 * @return $this
 */
function setQueryBatching($enableBatching)
```

```php
/**
 * @api
 * @param PromiseAdapter $promiseAdapter
 * @return $this
 */
function setPromiseAdapter(GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter)
```
# GraphQL\Server\Helper
Contains functionality that could be re-used by various server implementations

**Class Methods:** 
```php
/**
 * Parses HTTP request using PHP globals and returns GraphQL OperationParams
 * contained in this request. For batched requests it returns an array of OperationParams.
 *
 * This function does not check validity of these params
 * (validation is performed separately in validateOperationParams() method).
 *
 * If $readRawBodyFn argument is not provided - will attempt to read raw request body
 * from `php://input` stream.
 *
 * Internally it normalizes input to $method, $bodyParams and $queryParams and
 * calls `parseRequestParams()` to produce actual return value.
 *
 * For PSR-7 request parsing use `parsePsrRequest()` instead.
 *
 * @api
 * @param callable|null $readRawBodyFn
 * @return OperationParams|OperationParams[]
 * @throws RequestError
 */
function parseHttpRequest(callable $readRawBodyFn = null)
```

```php
/**
 * Parses normalized request params and returns instance of OperationParams
 * or array of OperationParams in case of batch operation.
 *
 * Returned value is a suitable input for `executeOperation` or `executeBatch` (if array)
 *
 * @api
 * @param string $method
 * @param array $bodyParams
 * @param array $queryParams
 * @return OperationParams|OperationParams[]
 * @throws RequestError
 */
function parseRequestParams($method, array $bodyParams, array $queryParams)
```

```php
/**
 * Checks validity of OperationParams extracted from HTTP request and returns an array of errors
 * if params are invalid (or empty array when params are valid)
 *
 * @api
 * @param OperationParams $params
 * @return Error[]
 */
function validateOperationParams(GraphQL\Server\OperationParams $params)
```

```php
/**
 * Executes GraphQL operation with given server configuration and returns execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter)
 *
 * @api
 * @param ServerConfig $config
 * @param OperationParams $op
 *
 * @return ExecutionResult|Promise
 */
function executeOperation(GraphQL\Server\ServerConfig $config, GraphQL\Server\OperationParams $op)
```

```php
/**
 * Executes batched GraphQL operations with shared promise queue
 * (thus, effectively batching deferreds|promises of all queries at once)
 *
 * @api
 * @param ServerConfig $config
 * @param OperationParams[] $operations
 * @return ExecutionResult[]|Promise
 */
function executeBatch(GraphQL\Server\ServerConfig $config, array $operations)
```

```php
/**
 * Send response using standard PHP `header()` and `echo`.
 *
 * @api
 * @param Promise|ExecutionResult|ExecutionResult[] $result
 * @param bool $exitWhenDone
 */
function sendResponse($result, $exitWhenDone = false)
```

```php
/**
 * Converts PSR-7 request to OperationParams[]
 *
 * @api
 * @param ServerRequestInterface $request
 * @return array|Helper
 * @throws RequestError
 */
function parsePsrRequest(Psr\Http\Message\ServerRequestInterface $request)
```

```php
/**
 * Converts query execution result to PSR-7 response
 *
 * @api
 * @param Promise|ExecutionResult|ExecutionResult[] $result
 * @param ResponseInterface $response
 * @param StreamInterface $writableBodyStream
 * @return Promise|ResponseInterface
 */
function toPsrResponse(
    $result,
    Psr\Http\Message\ResponseInterface $response,
    Psr\Http\Message\StreamInterface $writableBodyStream
)
```
# GraphQL\Server\OperationParams
Structure representing parsed HTTP parameters for GraphQL operation

**Class Props:** 
```php
/**
 * Id of the query (when using persistent queries).
 *
 * Valid aliases (case-insensitive):
 * - id
 * - queryId
 * - documentId
 *
 * @api
 * @var string
 */
public $queryId;

/**
 * @api
 * @var string
 */
public $query;

/**
 * @api
 * @var string
 */
public $operation;

/**
 * @api
 * @var array
 */
public $variables;
```

**Class Methods:** 
```php
/**
 * Creates an instance from given array
 *
 * @api
 * @param array $params
 * @param bool $readonly
 * @return OperationParams
 */
static function create(array $params, $readonly = false)
```

```php
/**
 * @api
 * @param string $key
 * @return mixed
 */
function getOriginalInput($key)
```

```php
/**
 * Indicates that operation is executed in read-only context
 * (e.g. via HTTP GET request)
 *
 * @api
 * @return bool
 */
function isReadOnly()
```
# GraphQL\Utils\BuildSchema
Build instance of `GraphQL\Type\Schema` out of type language definition (string or parsed AST)
See [section in docs](type-system/type-language.md) for details.

**Class Methods:** 
```php
/**
 * This takes the ast of a schema document produced by the parse function in
 * GraphQL\Language\Parser.
 *
 * If no schema definition is provided, then it will look for types named Query
 * and Mutation.
 *
 * Given that AST it constructs a GraphQL\Type\Schema. The resulting schema
 * has no resolve methods, so execution will use default resolvers.
 *
 * @api
 * @param DocumentNode $ast
 * @param callable $typeConfigDecorator
 * @return Schema
 * @throws Error
 */
static function buildAST(GraphQL\Language\AST\DocumentNode $ast, callable $typeConfigDecorator = null)
```

```php
/**
 * A helper function to build a GraphQLSchema directly from a source
 * document.
 *
 * @api
 * @param DocumentNode|Source|string $source
 * @param callable $typeConfigDecorator
 * @return Schema
 */
static function build($source, callable $typeConfigDecorator = null)
```
# GraphQL\Utils\AST
Various utilities dealing with AST

**Class Methods:** 
```php
/**
 * Convert representation of AST as an associative array to instance of GraphQL\Language\AST\Node.
 *
 * For example:
 *
 * ```php
 * AST::fromArray([
 *     'kind' => 'ListValue',
 *     'values' => [
 *         ['kind' => 'StringValue', 'value' => 'my str'],
 *         ['kind' => 'StringValue', 'value' => 'my other str']
 *     ],
 *     'loc' => ['start' => 21, 'end' => 25]
 * ]);
 * ```
 *
 * Will produce instance of `ListValueNode` where `values` prop is a lazily-evaluated `NodeList`
 * returning instances of `StringValueNode` on access.
 *
 * This is a reverse operation for AST::toArray($node)
 *
 * @api
 * @param array $node
 * @return Node
 */
static function fromArray(array $node)
```

```php
/**
 * Convert AST node to serializable array
 *
 * @api
 * @param Node $node
 * @return array
 */
static function toArray(GraphQL\Language\AST\Node $node)
```

```php
/**
 * Produces a GraphQL Value AST given a PHP value.
 *
 * Optionally, a GraphQL type may be provided, which will be used to
 * disambiguate between value primitives.
 *
 * | PHP Value     | GraphQL Value        |
 * | ------------- | -------------------- |
 * | Object        | Input Object         |
 * | Assoc Array   | Input Object         |
 * | Array         | List                 |
 * | Boolean       | Boolean              |
 * | String        | String / Enum Value  |
 * | Int           | Int                  |
 * | Float         | Int / Float          |
 * | Mixed         | Enum Value           |
 * | null          | NullValue            |
 *
 * @api
 * @param $value
 * @param InputType $type
 * @return ObjectValueNode|ListValueNode|BooleanValueNode|IntValueNode|FloatValueNode|EnumValueNode|StringValueNode|NullValueNode
 */
static function astFromValue($value, GraphQL\Type\Definition\InputType $type)
```

```php
/**
 * Produces a PHP value given a GraphQL Value AST.
 *
 * A GraphQL type must be provided, which will be used to interpret different
 * GraphQL Value literals.
 *
 * Returns `null` when the value could not be validly coerced according to
 * the provided type.
 *
 * | GraphQL Value        | PHP Value     |
 * | -------------------- | ------------- |
 * | Input Object         | Assoc Array   |
 * | List                 | Array         |
 * | Boolean              | Boolean       |
 * | String               | String        |
 * | Int / Float          | Int / Float   |
 * | Enum Value           | Mixed         |
 * | Null Value           | null          |
 *
 * @api
 * @param $valueNode
 * @param InputType $type
 * @param null $variables
 * @return array|null|\stdClass
 * @throws \Exception
 */
static function valueFromAST($valueNode, GraphQL\Type\Definition\InputType $type, $variables = null)
```

```php
/**
 * Returns type definition for given AST Type node
 *
 * @api
 * @param Schema $schema
 * @param NamedTypeNode|ListTypeNode|NonNullTypeNode $inputTypeNode
 * @return Type
 * @throws InvariantViolation
 */
static function typeFromAST(GraphQL\Type\Schema $schema, $inputTypeNode)
```

```php
/**
 * Returns operation type ("query", "mutation" or "subscription") given a document and operation name
 *
 * @api
 * @param DocumentNode $document
 * @param string $operationName
 * @return bool
 */
static function getOperation(GraphQL\Language\AST\DocumentNode $document, $operationName = null)
```
# GraphQL\Utils\SchemaPrinter
Given an instance of Schema, prints it in GraphQL type language.

**Class Methods:** 
```php
/**
 * @api
 * @param Schema $schema
 * @return string
 */
static function doPrint(GraphQL\Type\Schema $schema)
```

```php
/**
 * @api
 * @param Schema $schema
 * @return string
 */
static function printIntrosepctionSchema(GraphQL\Type\Schema $schema)
```
