# Intro
Stable library API. Not all class methods are covered here, only those which are considered stable.
**Backwards compatibility of these methods is preserved whenever possible.**

# GraphQL\GraphQL

Facade class ([related docs](/executing-queries/)) with following public interface:
```php
namespace GraphQL;

class GraphQL 
{
    /**
     * @return GraphQL\Executor\ExecutionResult
     */
    public static function executeQuery(
        GraphQL\Type\Schema $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null,
        callable $fieldResolver = null,
        array $validationRules = null,
        GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter = null
    );
    
    /**
     * Returns directives defined in GraphQL spec
     * 
     * @return Directive[]
     */
    public static function getInternalDirectives();

    /**
     * Returns types defined in GraphQL spec
     * 
     * @return Type[]
     */
    public static function getInternalTypes();
}
```

# GraphQL\Type\Definition\Type

Returns internal GraphQL types ([related docs](type-system/scalar-types/#built-in-scalar-types)):

```php
namespace GraphQL\Type\Definition;

class Type
{
    /**
     * @return IDType
     */
    public static function id();

    /**
     * @return StringType
     */
    public static function string();

    /**
     * @return BooleanType
     */
    public static function boolean();

    /**
     * @return IntType
     */
    public static function int();

    /**
     * @return FloatType
     */
    public static function float();

    /**
     * @param Type $wrappedType
     * @return ListOfType
     */
    public static function listOf($wrappedType);

    /**
     * @param Type $wrappedType
     * @return NonNull
     */
    public static function nonNull($wrappedType);

    /**
     * @return Type[]
     */
    public static function getInternalTypes();
```

# GraphQL\Type\Definition\\*

- [GraphQL\Type\Definition\ObjectType](type-system/object-types/)
- [GraphQL\Type\Definition\ScalarType](type-system/scalar-types/)
- [GraphQL\Type\Definition\CustomScalarType](type-system/scalar-types/)
- [GraphQL\Type\Definition\EnumType](type-system/enum-types/)
- [GraphQL\Type\Definition\ListType](type-system/lists-and-nonnulls/)
- [GraphQL\Type\Definition\NonNull](type-system/lists-and-nonnulls/)
- [GraphQL\Type\Definition\InterfaceType](type-system/interfaces/)
- [GraphQL\Type\Definition\UnionType](type-system/unions/)
- [GraphQL\Type\Definition\InputObjectType](type-system/input-types/#input-object-type)
- [GraphQL\Type\Definition\Directive](type-system/directives/)

# GraphQL\Type\SchemaConfig

Instance accepted by [Schema](type-system/schema/#configuration-options) constructor.

```php
namespace GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\Directive;

class SchemaConfig
{
    /**
     * Converts array of options to instance of SchemaConfig
     *
     * @param array $options
     * @return SchemaConfig
     */
    public static function create(array $options = []);

    /**
     * @return $this
     */
    public function setQuery(ObjectType $query);

    /**
     * @return $this
     */
    public function setMutation(ObjectType $mutation);
    
    /**
     * @return $this
     */
    public function setSubscription(ObjectType $subscription);
    
    /**
     * Pass additional schema types (or callable returning these types)
     * 
     * @param Type[]|callable $types
     * @return $this
     */
    public function setTypes($types);

    /**
     * @param Directive[] $directives
     * @return $this
     */
    public function setDirectives(array $directives);

    /**
     * @return $this
     */
    public function setTypeLoader(callable $typeLoader);

    /**
     * @return ObjectType
     */
    public function getQuery();

    /**
     * @return ObjectType
     */
    public function getMutation();

    /**
     * @return ObjectType
     */
    public function getSubscription();

    /**
     * @return Type[]
     */
    public function getTypes();

    /**
     * @return Directive[]
     */
    public function getDirectives();

    /**
     * @return callable
     */
    public function getTypeLoader();
}

```

# GraphQL\Type\Schema
See [related docs](type-system/schema).

```php
namespace GraphQL\Type;

class Schema
{
    /**
     * Schema constructor.
     *
     * @param array|SchemaConfig $config
     */
    public function __construct($config);

    /**
     * @return SchemaConfig
     */
    public function getConfig();

    /**
     * Returns schema query type
     *
     * @return ObjectType
     */
    public function getQueryType();

    /**
     * Returns schema mutation type
     *
     * @return ObjectType|null
     */
    public function getMutationType();

    /**
     * Returns schema subscription
     *
     * @return ObjectType|null
     */
    public function getSubscriptionType();
    
    /**
     * Returns a list of directives supported by this schema
     *
     * @return Directive[]
     */
    public function getDirectives();

    /**
     * Returns instance of directive by name
     *
     * @return Directive
     */
    public function getDirective($name);

    /**
     * Returns true if object type is concrete type of given abstract type
     * (implementation for interfaces and members of union type for unions)
     *
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType);

    /**
     * Returns type by it's name
     *
     * @return Type
     */
    public function getType($name);
    
    /**
     * Returns array of all types in this schema. Keys of this array represent type names, 
     * values are instances of corresponding type definitions
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @return Type[]
     */
    public function getTypeMap();

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions)
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType);

    /**
     * Validates schema.
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @throws InvariantViolation
     */
    public function assertValid();
}
```

# GraphQL\Language\Parser
Parses query string to AST

```php
class Parser
{
    /**
     * Available options:
     *
     * noLocation: boolean,
     * (By default, the parser creates AST nodes that know the location
     * in the source that they correspond to. This configuration flag
     * disables that behavior for performance or testing.)
     *
     * @return DocumentNode
     */
    public static function parse($source, array $options = []);

    /**
     * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
     * that value.
     * Throws GraphQL\Error\SyntaxError if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Values directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: GraphQL\Utils\AST::valueFromAST().
     *
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    public static function parseValue($source, array $options = []);

    /**
     * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
     * that type.
     * Throws GraphQL\Error\SyntaxError if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Types directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: GraphQL\Utils\AST::typeFromAST().
     *
     * @return ListTypeNode|NameNode|NonNullTypeNode
     */
    public static function parseType($source, array $options = []);
}
```

# GraphQL\Language\Printer
Prints AST to string.

```php
namespace GraphQL\Language;

class Printer
{
    /**
     * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
     *
     * @param GraphQL\Language\AST\Node $ast
     * @return string
     */
    public static function doPrint($ast);
}
```

# GraphQL\Language\Visitor

```php
namespace GraphQL\Language;

class Visitor 
{
    /**
     * visit() will walk through an AST using a depth first traversal, calling
     * the visitor's enter function at each node in the traversal, and calling the
     * leave function after visiting that node and all of it's child nodes.
     *
     * By returning different values from the enter and leave functions, the
     * behavior of the visitor can be altered, including skipping over a sub-tree of
     * the AST (by returning false), editing the AST by returning a value or null
     * to remove the value, or to stop the whole traversal by returning BREAK.
     *
     * When using visit() to edit an AST, the original AST will not be modified, and
     * a new version of the AST with the changes applied will be returned from the
     * visit function.
     *
     *     $editedAST = Visitor::visit($ast, [
     *       'enter' => function ($node, $key, $parent, $path, $ancestors) {
     *         // return
     *         //   null: no action
     *         //   Visitor::skipNode(): skip visiting this node
     *         //   Visitor::stop(): stop visiting altogether
     *         //   Visitor::removeNode(): delete this node
     *         //   any value: replace this node with the returned value
     *       },
     *       'leave' => function ($node, $key, $parent, $path, $ancestors) {
     *         // return
     *         //   null: no action
     *         //   Visitor::stop(): stop visiting altogether
     *         //   Visitor::removeNode(): delete this node
     *         //   any value: replace this node with the returned value
     *       }
     *     ]);
     *
     * Alternatively to providing enter() and leave() functions, a visitor can
     * instead provide functions named the same as the kinds of AST nodes, or
     * enter/leave visitors at a named key, leading to four permutations of
     * visitor API:
     *
     * 1) Named visitors triggered when entering a node a specific kind.
     *
     *     Visitor::visit($ast, [
     *       'Kind' => function ($node) {
     *         // enter the "Kind" node
     *       }
     *     ]);
     *
     * 2) Named visitors that trigger upon entering and leaving a node of
     *    a specific kind.
     *
     *     Visitor::visit($ast, [
     *       'Kind' => [
     *         'enter' => function ($node) {
     *           // enter the "Kind" node
     *         }
     *         'leave' => function ($node) {
     *           // leave the "Kind" node
     *         }
     *       ]
     *     ]);
     *
     * 3) Generic visitors that trigger upon entering and leaving any node.
     *
     *     Visitor::visit($ast, [
     *       'enter' => function ($node) {
     *         // enter any node
     *       },
     *       'leave' => function ($node) {
     *         // leave any node
     *       }
     *     ]);
     *
     * 4) Parallel visitors for entering and leaving nodes of a specific kind.
     *
     *     Visitor::visit($ast, [
     *       'enter' => [
     *         'Kind' => function($node) {
     *           // enter the "Kind" node
     *         }
     *       },
     *       'leave' => [
     *         'Kind' => function ($node) {
     *           // leave the "Kind" node
     *         }
     *       ]
     *     ]);
     *
     * @param Node $root
     * @param array $visitor
     * @param array $keyMap
     * @return Node|mixed
     * @throws \Exception
     */
    public static function visit($root, $visitor, $keyMap = null);
    
    /**
     * Returns marker for visitor break
     *
     * @return VisitorOperation
     */
    public static function stop();

    /**
     * Returns marker for skipping current node
     *
     * @return VisitorOperation
     */
    public static function skipNode();

    /**
     * Returns marker for removing a node
     *
     * @return VisitorOperation
     */
    public static function removeNode();
}
```

# GraphQL\Language\AST\\*

Simple structural classes representing AST Nodes. 
[Check out source code](https://github.com/webonyx/graphql-php/tree/master/src/Language/AST).


# GraphQL\Executor\Executor
Implements [execution phase](/executing-queries/#executing) of whole query execution process.

```php
namespace GraphQL\Executor;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;

class Executor
{
    /**
     * Executes DocumentNode against given schema.
     * 
     * When $promiseAdapter is passed returns Promise instance produced by this adapter. 
     * By default simply returns ExecutionResult
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
        callable $fieldResolver = null,
        PromiseAdapter $promiseAdapter = null
    );
}
```

# GraphQL\Executor\ExecutionResult

Result of [query execution process](/executing-queries/#overview).

```php
namespace GraphQL\Executor;

class ExecutionResult implements \JsonSerializable
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var Error[]
     */
    public $errors;
    
    /**
     * @var array[]
     */
    public $extensions;

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
     * @param callable $errorFormatter
     * @return $this
     */
    public function setErrorFormatter(callable $errorFormatter);

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
     * @param callable $handler
     * @return $this
     */
    public function setErrorsHandler(callable $handler);

    /**
     * Converts GraphQL result to array using provided errors handler and formatter.
     *
     * Default error formatter is GraphQL\Error\FormattedError::createFromException
     * Default error handler will simply return all errors formatted. No errors are filtered.
     *
     * @param bool|int $debug
     * @return array
     */
    public function toArray($debug = false);

    /**
     * Part of \JsonSerializable interface
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
```

# GraphQL\Type\Definition\ResolveInfo

```php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Schema;

class ResolveInfo
{
    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var FieldNode[]
     */
    public $fieldNodes;

    /**
     * @var ScalarType|ObjectType|InterfaceType|UnionType|EnumType|ListOfType|NonNull
     */
    public $returnType;

    /**
     * @var ObjectType|InterfaceType|UnionType
     */
    public $parentType;

    /**
     * @var array
     */
    public $path;

    /**
     * @var Schema
     */
    public $schema;

    /**
     * @var FragmentDefinitionNode[]
     */
    public $fragments;

    /**
     * @var mixed
     */
    public $rootValue;

    /**
     * @var OperationDefinitionNode
     */
    public $operation;

    /**
     * @var array<variableName, mixed>
     */
    public $variableValues;

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
     * @param int $depth How many levels to include in output
     * @return array
     */
    public function getFieldSelection($depth = 0);
}
```

# GraphQL\Validator\DocumentValidator
```php
namespace GraphQL\Validator;

use GraphQL\Error\Error;

class DocumentValidator
{
    /**
     * Returns all validation rules
     *
     * @return callable[]
     */
    public static function allRules();

    /**
     * Returns validation rule
     *
     * @param string $name
     * @return callable|null
     */
    public static function getRule($name);

    /**
     * Implements the "Validation" section of the spec.
     *
     * Validation runs synchronously, returning an array of encountered errors, or
     * an empty array if no errors were encountered and the document is valid.
     *
     * A list of specific validation rules may be provided. If not provided, the
     * default list of rules defined by the GraphQL specification will be used.
     *
     * Each validation rules is a function which returns a visitor
     * (see the GraphQL\Language\Visitor API). Visitor methods are expected to return
     * GraphQL\Error\Error, or arrays of GraphQL\Error\Error when invalid.
     *
     * Optionally a custom TypeInfo instance may be provided. If not provided, one
     * will be created from the provided schema.
     *
     * @return Error[]
     */
    public static function validate(
        Schema $schema,
        DocumentNode $ast,
        array $rules = null,
        TypeInfo $typeInfo = null
    );
}
```

# GraphQL\Error\Error
Class describing an Error found during the parse, validate, or
execute phases of performing a GraphQL operation. 

In addition to a message and stack trace, it also includes information about the locations in a
GraphQL document and/or execution result that correspond to the Error.

```php
namespace GraphQL\Error;

class Error extends \Exception implements \JsonSerializable, ClientAware
{
    const CATEGORY_GRAPHQL = 'graphql';
    const CATEGORY_INTERNAL = 'internal';

    /**
     * Part of ClientAware interface
     *
     * @return boolean
     */
    public function isClientSafe();

    /**
     * Part of ClientAware interface
     *
     * @return string
     */
    public function getCategory();

    /**
     * @return SourceLocation[]
     */
    public function getLocations();

    /**
     * Returns an array describing the JSON-path into the execution response which
     * corresponds to this error. Only included for errors during execution.
     *
     * @return array|null
     */
    public function getPath();
```

# GraphQL\Error\Warning
```php
final class Warning
{
    const NAME_WARNING = 1;
    const ASSIGN_WARNING = 2;
    const CONFIG_WARNING = 4;
    const FULL_SCHEMA_SCAN_WARNING = 8;
    const CONFIG_DEPRECATION_WARNING = 16;
    const NOT_A_TYPE = 32;

    /**
     * Sets warning handler which (when set) will intercept all system warnings.
     * When not set, trigger_error() is used to notify about warnings.
     *
     * @param callable|null $warningHandler
     */
    public static function setWarningHandler(callable $warningHandler = null);

    /**
     * Suppress warning by id (has no effect when custom warning handler is set)
     *
     * Usage example:
     * Warning::suppress(Warning::NOT_A_TYPE)
     *
     * When passing true - suppresses all warnings.
     *
     * @param bool|int $suppress
     */
    static function suppress($suppress = true);

    /**
     * Re-enable previously suppressed warning by id
     *
     * Usage example:
     * Warning::suppress(Warning::NOT_A_TYPE)
     *
     * When passing true - re-enables all warnings.
     *
     * @param bool|int $enable
     */
    public static function enable($enable = true);
}
```

# GraphQL\Error\ClientAware
```
namespace GraphQL\Error;

interface ClientAware
{
    /**
     * Returns true when exception message is safe to be displayed to client
     *
     * @return bool
     */
    public function isClientSafe();

    /**
     * Returns string describing error category.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @return string
     */
    public function getCategory();
}
```

# GraphQL\Error\FormattedError
```php
namespace GraphQL\Error;

class FormattedError
{
    const INCLUDE_DEBUG_MESSAGE = 1;
    const INCLUDE_TRACE = 2;
    const RETHROW_RESOLVER_EXCEPTIONS = 4;

    public static function setInternalErrorMessage($msg);

    /**
     * Standard GraphQL error formatter. Converts any exception to GraphQL error
     * conforming to GraphQL spec
     *
     * @param \Throwable $e
     * @param bool|int $debug
     * @param string $internalErrorMessage
     *
     * @return array
     */
    public static function createFromException($e, $debug = false, $internalErrorMessage = null);
}
```
