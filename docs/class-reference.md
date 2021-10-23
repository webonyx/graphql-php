## GraphQL\GraphQL

This is the primary facade for fulfilling GraphQL operations.
See [related documentation](executing-queries.md).

### GraphQL\GraphQL Methods

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
 * contextValue:
 *    The context value is provided as an argument to resolver functions after
 *    field arguments. It is used to pass shared information useful at any point
 *    during executing this query, for example the currently logged in user and
 *    connections to databases or other services.
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
 * @param string|DocumentNode $source
 * @param mixed               $rootValue
 * @param mixed               $contextValue
 * @param mixed[]|null        $variableValues
 * @param ValidationRule[]    $validationRules
 *
 * @api
 */
static function executeQuery(
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $contextValue = null,
    $variableValues = null,
    string $operationName = null,
    callable $fieldResolver = null,
    array $validationRules = null
): GraphQL\Executor\ExecutionResult
```

```php
/**
 * Same as executeQuery(), but requires PromiseAdapter and always returns a Promise.
 * Useful for Async PHP platforms.
 *
 * @param string|DocumentNode        $source
 * @param mixed                      $rootValue
 * @param mixed                      $context
 * @param array<string, mixed>|null  $variableValues
 * @param array<ValidationRule>|null $validationRules
 *
 * @api
 */
static function promiseToExecute(
    GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter,
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $context = null,
    array $variableValues = null,
    string $operationName = null,
    callable $fieldResolver = null,
    array $validationRules = null
): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Returns directives defined in GraphQL spec.
 *
 * @return array<string, Directive>
 *
 * @api
 */
static function getStandardDirectives(): array
```

```php
/**
 * Returns types defined in GraphQL spec.
 *
 * @return array<string, ScalarType>
 *
 * @api
 */
static function getStandardTypes(): array
```

```php
/**
 * Replaces standard types with types from this list (matching by name).
 *
 * Standard types not listed here remain untouched.
 *
 * @param array<string, ScalarType> $types
 *
 * @api
 */
static function overrideStandardTypes(array $types): void
```

```php
/**
 * Returns standard validation rules implementing GraphQL spec.
 *
 * @return array<class-string<ValidationRule>, ValidationRule>
 *
 * @api
 */
static function getStandardValidationRules(): array
```

```php
/**
 * Set default resolver implementation.
 *
 * @param callable(mixed, array, mixed, ResolveInfo): mixed $fn
 *
 * @api
 */
static function setDefaultFieldResolver(callable $fn): void
```

## GraphQL\Type\Definition\Type

Registry of standard GraphQL types and base class for all other types.

### GraphQL\Type\Definition\Type Methods

```php
/**
 * @api
 */
static function id(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 */
static function string(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 */
static function boolean(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 */
static function int(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 */
static function float(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @param Type|callable():Type $type
 *
 * @api
 */
static function listOf($type): GraphQL\Type\Definition\ListOfType
```

```php
/**
 * code sniffer doesn't understand this syntax. Pr with a fix here: waiting on https://github.com/squizlabs/PHP_CodeSniffer/pull/2919
 * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
 * @param (NullableType&Type)|callable():(NullableType&Type) $type
 *
 * @api
 */
static function nonNull($type): GraphQL\Type\Definition\NonNull
```

```php
/**
 * @api
 */
static function isInputType($type): bool
```

```php
/**
 * @api
 */
static function getNamedType($type): GraphQL\Type\Definition\Type
```

```php
/**
 * @api
 */
static function isOutputType($type): bool
```

```php
/**
 * @api
 */
static function isLeafType($type): bool
```

```php
/**
 * @api
 */
static function isCompositeType($type): bool
```

```php
/**
 * @api
 */
static function isAbstractType($type): bool
```

```php
/**
 * @api
 */
static function getNullableType(GraphQL\Type\Definition\Type $type): GraphQL\Type\Definition\Type
```

## GraphQL\Type\Definition\ResolveInfo

Structure containing information useful for field resolution process.

Passed as 4th argument to every field resolver. See [docs on field resolving (data fetching)](data-fetching.md).

### GraphQL\Type\Definition\ResolveInfo Props

```php
/**
 * The definition of the field being resolved.
 *
 * @api
 */
public $fieldDefinition;

/**
 * The name of the field being resolved.
 *
 * @api
 */
public $fieldName;

/**
 * Expected return type of the field being resolved.
 *
 * @api
 */
public $returnType;

/**
 * AST of all nodes referencing this field in the query.
 *
 * @api
 * @var iterable<int, FieldNode>
 */
public $fieldNodes;

/**
 * Parent type of the field being resolved.
 *
 * @api
 */
public $parentType;

/**
 * Path to this field from the very root value.
 *
 * @api
 * @var array<int, string|int>
 */
public $path;

/**
 * Instance of a schema used for execution.
 *
 * @api
 */
public $schema;

/**
 * AST of all fragments defined in query.
 *
 * @api
 * @var array<string, FragmentDefinitionNode>
 */
public $fragments;

/**
 * Root value passed to query execution.
 *
 * @api
 * @var mixed
 */
public $rootValue;

/**
 * AST of operation definition node (query, mutation).
 *
 * @api
 */
public $operation;

/**
 * Array of variables passed to query execution.
 *
 * @api
 * @var array<string, mixed>
 */
public $variableValues;
```

### GraphQL\Type\Definition\ResolveInfo Methods

```php
/**
 * Helper method that returns names of all fields selected in query for
 * $this->fieldName up to $depth levels.
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
 * @param int $depth How many levels to include in output
 *
 * @return array<string, mixed>
 *
 * @api
 */
function getFieldSelection(int $depth = 0): array
```

## GraphQL\Language\DirectiveLocation

Enumeration of available directive locations.

### GraphQL\Language\DirectiveLocation Constants

```php
const QUERY = 'QUERY';
const MUTATION = 'MUTATION';
const SUBSCRIPTION = 'SUBSCRIPTION';
const FIELD = 'FIELD';
const FRAGMENT_DEFINITION = 'FRAGMENT_DEFINITION';
const FRAGMENT_SPREAD = 'FRAGMENT_SPREAD';
const INLINE_FRAGMENT = 'INLINE_FRAGMENT';
const VARIABLE_DEFINITION = 'VARIABLE_DEFINITION';
const EXECUTABLE_LOCATIONS = [
    'QUERY' => 'QUERY',
    'MUTATION' => 'MUTATION',
    'SUBSCRIPTION' => 'SUBSCRIPTION',
    'FIELD' => 'FIELD',
    'FRAGMENT_DEFINITION' => 'FRAGMENT_DEFINITION',
    'FRAGMENT_SPREAD' => 'FRAGMENT_SPREAD',
    'INLINE_FRAGMENT' => 'INLINE_FRAGMENT',
    'VARIABLE_DEFINITION' => 'VARIABLE_DEFINITION',
];
const SCHEMA = 'SCHEMA';
const SCALAR = 'SCALAR';
const OBJECT = 'OBJECT';
const FIELD_DEFINITION = 'FIELD_DEFINITION';
const ARGUMENT_DEFINITION = 'ARGUMENT_DEFINITION';
const IFACE = 'INTERFACE';
const UNION = 'UNION';
const ENUM = 'ENUM';
const ENUM_VALUE = 'ENUM_VALUE';
const INPUT_OBJECT = 'INPUT_OBJECT';
const INPUT_FIELD_DEFINITION = 'INPUT_FIELD_DEFINITION';
const TYPE_SYSTEM_LOCATIONS = [
    'SCHEMA' => 'SCHEMA',
    'SCALAR' => 'SCALAR',
    'OBJECT' => 'OBJECT',
    'FIELD_DEFINITION' => 'FIELD_DEFINITION',
    'ARGUMENT_DEFINITION' => 'ARGUMENT_DEFINITION',
    'INTERFACE' => 'INTERFACE',
    'UNION' => 'UNION',
    'ENUM' => 'ENUM',
    'ENUM_VALUE' => 'ENUM_VALUE',
    'INPUT_OBJECT' => 'INPUT_OBJECT',
    'INPUT_FIELD_DEFINITION' => 'INPUT_FIELD_DEFINITION',
];
const LOCATIONS = [
    'QUERY' => 'QUERY',
    'MUTATION' => 'MUTATION',
    'SUBSCRIPTION' => 'SUBSCRIPTION',
    'FIELD' => 'FIELD',
    'FRAGMENT_DEFINITION' => 'FRAGMENT_DEFINITION',
    'FRAGMENT_SPREAD' => 'FRAGMENT_SPREAD',
    'INLINE_FRAGMENT' => 'INLINE_FRAGMENT',
    'VARIABLE_DEFINITION' => 'VARIABLE_DEFINITION',
    'SCHEMA' => 'SCHEMA',
    'SCALAR' => 'SCALAR',
    'OBJECT' => 'OBJECT',
    'FIELD_DEFINITION' => 'FIELD_DEFINITION',
    'ARGUMENT_DEFINITION' => 'ARGUMENT_DEFINITION',
    'INTERFACE' => 'INTERFACE',
    'UNION' => 'UNION',
    'ENUM' => 'ENUM',
    'ENUM_VALUE' => 'ENUM_VALUE',
    'INPUT_OBJECT' => 'INPUT_OBJECT',
    'INPUT_FIELD_DEFINITION' => 'INPUT_FIELD_DEFINITION',
];
```

## GraphQL\Type\SchemaConfig

Configuration options for schema construction.

The options accepted by the **create** method are described
in the [schema definition docs](schema-definition.md#configuration-options).

Usage example:

    $config = SchemaConfig::create()
        ->setQuery($myQueryType)
        ->setTypeLoader($myTypeLoader);

    $schema = new Schema($config);

### GraphQL\Type\SchemaConfig Methods

```php
/**
 * Converts an array of options to instance of SchemaConfig
 * (or just returns empty config when array is not passed).
 *
 * @param array<string, mixed> $options
 *
 * @api
 */
static function create(array $options = []): self
```

```php
/**
 * @api
 */
function getQuery(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * @api
 */
function setQuery(GraphQL\Type\Definition\ObjectType $query): self
```

```php
/**
 * @api
 */
function getMutation(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * @api
 */
function setMutation(GraphQL\Type\Definition\ObjectType $mutation): self
```

```php
/**
 * @api
 */
function getSubscription(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * @api
 */
function setSubscription(GraphQL\Type\Definition\ObjectType $subscription): self
```

```php
/**
 * @return array<Type>|(callable(): array<Type>)
 *
 * @api
 */
function getTypes()
```

```php
/**
 * @param array<Type>|(callable(): array<Type>) $types
 *
 * @api
 */
function setTypes($types): self
```

```php
/**
 * @return array<Directive>|null
 *
 * @api
 */
function getDirectives(): array
```

```php
/**
 * @param array<Directive>|null $directives
 *
 * @api
 */
function setDirectives(array $directives): self
```

```php
/**
 * @return (callable(string $typeName): Type|(callable(): Type)|null)|null
 *
 * @api
 */
function getTypeLoader(): callable
```

```php
/**
 * @param (callable(string $typeName): Type|(callable(): Type)|null)|null $typeLoader
 *
 * @api
 */
function setTypeLoader(callable $typeLoader): self
```

## GraphQL\Type\Schema

Schema Definition (see [schema definition docs](schema-definition.md))

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

### GraphQL\Type\Schema Methods

```php
/**
 * @param SchemaConfig|array<string, mixed> $config
 *
 * @api
 */
function __construct($config)
```

```php
/**
 * Returns all types in this schema.
 *
 * This operation requires a full schema scan. Do not use in production environment.
 *
 * @return array<string, Type> Keys represent type names, values are instances of corresponding type definitions
 *
 * @api
 */
function getTypeMap(): array
```

```php
/**
 * Returns a list of directives supported by this schema
 *
 * @return array<Directive>
 *
 * @api
 */
function getDirectives(): array
```

```php
/**
 * Returns root query type.
 *
 * @api
 */
function getQueryType(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * Returns root mutation type.
 *
 * @api
 */
function getMutationType(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * Returns schema subscription
 *
 * @api
 */
function getSubscriptionType(): GraphQL\Type\Definition\ObjectType
```

```php
/**
 * @api
 */
function getConfig(): GraphQL\Type\SchemaConfig
```

```php
/**
 * Returns a type by name.
 *
 * @api
 */
function getType(string $name): GraphQL\Type\Definition\Type
```

```php
/**
 * Returns all possible concrete types for given abstract type
 * (implementations for interfaces and members of union type for unions)
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @param InterfaceType|UnionType $abstractType
 *
 * @return array<Type&ObjectType>
 *
 * @api
 */
function getPossibleTypes(GraphQL\Type\Definition\Type $abstractType): array
```

```php
/**
 * Returns all types that implement a given interface type.
 *
 * This operations requires full schema scan. Do not use in production environment.
 *
 * @api
 */
function getImplementations(GraphQL\Type\Definition\InterfaceType $abstractType): GraphQL\Utils\InterfaceImplementations
```

```php
/**
 * Returns true if the given type is a sub type of the given abstract type.
 *
 * @param UnionType|InterfaceType  $abstractType
 * @param ObjectType|InterfaceType $maybeSubType
 *
 * @api
 */
function isSubType(
    GraphQL\Type\Definition\AbstractType $abstractType,
    GraphQL\Type\Definition\ImplementingType $maybeSubType
): bool
```

```php
/**
 * Returns instance of directive by name
 *
 * @api
 */
function getDirective(string $name): GraphQL\Type\Definition\Directive
```

```php
/**
 * Throws if the schema is not valid.
 *
 * This operation requires a full schema scan. Do not use in production environment.
 *
 * @throws InvariantViolation
 *
 * @api
 */
function assertValid(): void
```

```php
/**
 * Validate the schema and return any errors.
 *
 * This operation requires a full schema scan. Do not use in production environment.
 *
 * @return array<int, Error>
 *
 * @api
 */
function validate(): array
```

## GraphQL\Language\Parser

Parses string containing GraphQL query language or [schema definition language](schema-definition-language.md) to Abstract Syntax Tree.

Those magic functions allow partial parsing:

@method static NameNode name(Source|string $source, bool[] $options = [])
@method static DocumentNode document(Source|string $source, bool[] $options = [])
@method static ExecutableDefinitionNode|TypeSystemDefinitionNode definition(Source|string $source, bool[] $options = [])
@method static ExecutableDefinitionNode executableDefinition(Source|string $source, bool[] $options = [])
@method static OperationDefinitionNode operationDefinition(Source|string $source, bool[] $options = [])
@method static string operationType(Source|string $source, bool[] $options = [])
@method static NodeList<VariableDefinitionNode> variableDefinitions(Source|string $source, bool[] $options = [])
@method static VariableDefinitionNode variableDefinition(Source|string $source, bool[] $options = [])
@method static VariableNode variable(Source|string $source, bool[] $options = [])
@method static SelectionSetNode selectionSet(Source|string $source, bool[] $options = [])
@method static mixed selection(Source|string $source, bool[] $options = [])
@method static FieldNode field(Source|string $source, bool[] $options = [])
@method static NodeList<ArgumentNode> arguments(Source|string $source, bool[] $options = [])
@method static NodeList<ArgumentNode> constArguments(Source|string $source, bool[] $options = [])
@method static ArgumentNode argument(Source|string $source, bool[] $options = [])
@method static ArgumentNode constArgument(Source|string $source, bool[] $options = [])
@method static FragmentSpreadNode|InlineFragmentNode fragment(Source|string $source, bool[] $options = [])
@method static FragmentDefinitionNode fragmentDefinition(Source|string $source, bool[] $options = [])
@method static NameNode fragmentName(Source|string $source, bool[] $options = [])
@method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|NullValueNode|ObjectValueNode|StringValueNode|VariableNode valueLiteral(Source|string $source, bool[] $options = [])
@method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|NullValueNode|ObjectValueNode|StringValueNode constValueLiteral(Source|string $source, bool[] $options = [])
@method static StringValueNode stringLiteral(Source|string $source, bool[] $options = [])
@method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode constValue(Source|string $source, bool[] $options = [])
@method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode variableValue(Source|string $source, bool[] $options = [])
@method static ListValueNode array(Source|string $source, bool[] $options = [])
@method static ListValueNode constArray(Source|string $source, bool[] $options = [])
@method static ObjectValueNode object(Source|string $source, bool[] $options = [])
@method static ObjectValueNode constObject(Source|string $source, bool[] $options = [])
@method static ObjectFieldNode objectField(Source|string $source, bool[] $options = [])
@method static ObjectFieldNode constObjectField(Source|string $source, bool[] $options = [])
@method static NodeList<DirectiveNode> directives(Source|string $source, bool[] $options = [])
@method static NodeList<DirectiveNode> constDirectives(Source|string $source, bool[] $options = [])
@method static DirectiveNode directive(Source|string $source, bool[] $options = [])
@method static DirectiveNode constDirective(Source|string $source, bool[] $options = [])
@method static ListTypeNode|NamedTypeNode|NonNullTypeNode typeReference(Source|string $source, bool[] $options = [])
@method static NamedTypeNode namedType(Source|string $source, bool[] $options = [])
@method static TypeSystemDefinitionNode typeSystemDefinition(Source|string $source, bool[] $options = [])
@method static StringValueNode|null description(Source|string $source, bool[] $options = [])
@method static SchemaDefinitionNode schemaDefinition(Source|string $source, bool[] $options = [])
@method static OperationTypeDefinitionNode operationTypeDefinition(Source|string $source, bool[] $options = [])
@method static ScalarTypeDefinitionNode scalarTypeDefinition(Source|string $source, bool[] $options = [])
@method static ObjectTypeDefinitionNode objectTypeDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<NamedTypeNode> implementsInterfaces(Source|string $source, bool[] $options = [])
@method static NodeList<FieldDefinitionNode> fieldsDefinition(Source|string $source, bool[] $options = [])
@method static FieldDefinitionNode fieldDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<InputValueDefinitionNode> argumentsDefinition(Source|string $source, bool[] $options = [])
@method static InputValueDefinitionNode inputValueDefinition(Source|string $source, bool[] $options = [])
@method static InterfaceTypeDefinitionNode interfaceTypeDefinition(Source|string $source, bool[] $options = [])
@method static UnionTypeDefinitionNode unionTypeDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<NamedTypeNode> unionMemberTypes(Source|string $source, bool[] $options = [])
@method static EnumTypeDefinitionNode enumTypeDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<EnumValueDefinitionNode> enumValuesDefinition(Source|string $source, bool[] $options = [])
@method static EnumValueDefinitionNode enumValueDefinition(Source|string $source, bool[] $options = [])
@method static InputObjectTypeDefinitionNode inputObjectTypeDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<InputValueDefinitionNode> inputFieldsDefinition(Source|string $source, bool[] $options = [])
@method static TypeExtensionNode typeExtension(Source|string $source, bool[] $options = [])
@method static SchemaTypeExtensionNode schemaTypeExtension(Source|string $source, bool[] $options = [])
@method static ScalarTypeExtensionNode scalarTypeExtension(Source|string $source, bool[] $options = [])
@method static ObjectTypeExtensionNode objectTypeExtension(Source|string $source, bool[] $options = [])
@method static InterfaceTypeExtensionNode interfaceTypeExtension(Source|string $source, bool[] $options = [])
@method static UnionTypeExtensionNode unionTypeExtension(Source|string $source, bool[] $options = [])
@method static EnumTypeExtensionNode enumTypeExtension(Source|string $source, bool[] $options = [])
@method static InputObjectTypeExtensionNode inputObjectTypeExtension(Source|string $source, bool[] $options = [])
@method static DirectiveDefinitionNode directiveDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<NameNode> directiveLocations(Source|string $source, bool[] $options = [])
@method static NameNode directiveLocation(Source|string $source, bool[] $options = [])

### GraphQL\Language\Parser Methods

```php
/**
 * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * Available options:
 *
 * noLocation: boolean,
 *   (By default, the parser creates AST nodes that know the location
 *   in the source that they correspond to. This configuration flag
 *   disables that behavior for performance or testing.)
 *
 * allowLegacySDLEmptyFields: boolean
 *   If enabled, the parser will parse empty fields sets in the Schema
 *   Definition Language. Otherwise, the parser will follow the current
 *   specification.
 *
 *   This option is provided to ease adoption of the final SDL specification
 *   and will be removed in a future major release.
 *
 * allowLegacySDLImplementsInterfaces: boolean
 *   If enabled, the parser will parse implemented interfaces with no `&`
 *   character between each interface. Otherwise, the parser will follow the
 *   current specification.
 *
 *   This option is provided to ease adoption of the final SDL specification
 *   and will be removed in a future major release.
 *
 * experimentalFragmentVariables: boolean,
 *   (If enabled, the parser will understand and parse variable definitions
 *   contained in a fragment definition. They'll be represented in the
 *   `variableDefinitions` field of the FragmentDefinitionNode.
 *
 *   The syntax is identical to normal, query-defined variables. For example:
 *
 *     fragment A($var: Boolean = false) on T  {
 *       ...
 *     }
 *
 *   Note: this feature is experimental and may change or be removed in the
 *   future.)
 *
 * @param Source|string $source
 * @param bool[]        $options
 *
 * @throws SyntaxError
 *
 * @api
 */
static function parse($source, array $options = []): GraphQL\Language\AST\DocumentNode
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
 * @param Source|string $source
 * @param bool[]        $options
 *
 * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
 *
 * @api
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
 * @param Source|string $source
 * @param bool[]        $options
 *
 * @return ListTypeNode|NamedTypeNode|NonNullTypeNode
 *
 * @api
 */
static function parseType($source, array $options = [])
```

## GraphQL\Language\Printer

Prints AST to string. Capable of printing GraphQL queries and Type definition language.
Useful for pretty-printing queries or printing back AST for logging, documentation, etc.

Usage example:

```php
$query = 'query myQuery {someField}';
$ast = GraphQL\Language\Parser::parse($query);
$printed = GraphQL\Language\Printer::doPrint($ast);
```

### GraphQL\Language\Printer Methods

```php
/**
 * Converts the AST of a GraphQL node to a string.
 *
 * Handles both executable definitions and schema definitions.
 *
 * @api
 */
static function doPrint(GraphQL\Language\AST\Node $ast): string
```

## GraphQL\Language\Visitor

Utility for efficient AST traversal and modification.

`visit()` will walk through an AST using a depth first traversal, calling
the visitor's enter function at each node in the traversal, and calling the
leave function after visiting that node and all of its child nodes.

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
instead provide functions named the same as the [kinds of AST nodes](class-reference.md#graphqllanguageastnodekind),
or enter/leave visitors at a named key, leading to four permutations of
visitor API:

1. Named visitors triggered when entering a node a specific kind.

    Visitor::visit($ast, [
      'Kind' => function ($node) {
        // enter the "Kind" node
      }
    ]);

2. Named visitors that trigger upon entering and leaving a node of
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

3. Generic visitors that trigger upon entering and leaving any node.

    Visitor::visit($ast, [
      'enter' => function ($node) {
        // enter any node
      },
      'leave' => function ($node) {
        // leave any node
      }
    ]);

4. Parallel visitors for entering and leaving nodes of a specific kind.

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

### GraphQL\Language\Visitor Methods

```php
/**
 * Visit the AST (see class description for details)
 *
 * @param Node|ArrayObject|stdClass $root
 * @param callable[]                $visitor
 * @param mixed[]|null              $keyMap
 *
 * @return Node|mixed
 *
 * @throws Exception
 *
 * @api
 */
static function visit($root, $visitor, $keyMap = null)
```

```php
/**
 * Returns marker for visitor break
 *
 * @api
 */
static function stop(): GraphQL\Language\VisitorOperation
```

```php
/**
 * Returns marker for skipping current node
 *
 * @api
 */
static function skipNode(): GraphQL\Language\VisitorOperation
```

```php
/**
 * Returns marker for removing a node
 *
 * @api
 */
static function removeNode(): GraphQL\Language\VisitorOperation
```

## GraphQL\Language\AST\NodeKind

Holds constants of possible AST nodes.

### GraphQL\Language\AST\NodeKind Constants

```php
const NAME = 'Name';
const DOCUMENT = 'Document';
const OPERATION_DEFINITION = 'OperationDefinition';
const VARIABLE_DEFINITION = 'VariableDefinition';
const VARIABLE = 'Variable';
const SELECTION_SET = 'SelectionSet';
const FIELD = 'Field';
const ARGUMENT = 'Argument';
const FRAGMENT_SPREAD = 'FragmentSpread';
const INLINE_FRAGMENT = 'InlineFragment';
const FRAGMENT_DEFINITION = 'FragmentDefinition';
const INT = 'IntValue';
const FLOAT = 'FloatValue';
const STRING = 'StringValue';
const BOOLEAN = 'BooleanValue';
const ENUM = 'EnumValue';
const NULL = 'NullValue';
const LST = 'ListValue';
const OBJECT = 'ObjectValue';
const OBJECT_FIELD = 'ObjectField';
const DIRECTIVE = 'Directive';
const NAMED_TYPE = 'NamedType';
const LIST_TYPE = 'ListType';
const NON_NULL_TYPE = 'NonNullType';
const SCHEMA_DEFINITION = 'SchemaDefinition';
const OPERATION_TYPE_DEFINITION = 'OperationTypeDefinition';
const SCALAR_TYPE_DEFINITION = 'ScalarTypeDefinition';
const OBJECT_TYPE_DEFINITION = 'ObjectTypeDefinition';
const FIELD_DEFINITION = 'FieldDefinition';
const INPUT_VALUE_DEFINITION = 'InputValueDefinition';
const INTERFACE_TYPE_DEFINITION = 'InterfaceTypeDefinition';
const UNION_TYPE_DEFINITION = 'UnionTypeDefinition';
const ENUM_TYPE_DEFINITION = 'EnumTypeDefinition';
const ENUM_VALUE_DEFINITION = 'EnumValueDefinition';
const INPUT_OBJECT_TYPE_DEFINITION = 'InputObjectTypeDefinition';
const SCALAR_TYPE_EXTENSION = 'ScalarTypeExtension';
const OBJECT_TYPE_EXTENSION = 'ObjectTypeExtension';
const INTERFACE_TYPE_EXTENSION = 'InterfaceTypeExtension';
const UNION_TYPE_EXTENSION = 'UnionTypeExtension';
const ENUM_TYPE_EXTENSION = 'EnumTypeExtension';
const INPUT_OBJECT_TYPE_EXTENSION = 'InputObjectTypeExtension';
const DIRECTIVE_DEFINITION = 'DirectiveDefinition';
const SCHEMA_EXTENSION = 'SchemaExtension';
```

## GraphQL\Executor\Executor

Implements the "Evaluating requests" section of the GraphQL specification.

@phpstan-type FieldResolver callable(mixed, array<string, mixed>, mixed, ResolveInfo): mixed
@phpstan-type ImplementationFactory callable(PromiseAdapter, Schema, DocumentNode, mixed=, mixed=, ?array<mixed>=, ?string=, ?callable=): ExecutorImplementation

### GraphQL\Executor\Executor Methods

```php
/**
 * Executes DocumentNode against given $schema.
 *
 * Always returns ExecutionResult and never throws.
 * All errors which occur during operation execution are collected in `$result->errors`.
 *
 * @param mixed                     $rootValue
 * @param mixed                     $contextValue
 * @param array<string, mixed>|null $variableValues
 * @phpstan-param FieldResolver|null $fieldResolver
 *
 * @return ExecutionResult|array<ExecutionResult>
 *
 * @api
 */
static function execute(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $documentNode,
    $rootValue = null,
    $contextValue = null,
    array $variableValues = null,
    string $operationName = null,
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
 * @param mixed                     $rootValue
 * @param mixed                     $contextValue
 * @param array<string, mixed>|null $variableValues
 * @phpstan-param FieldResolver|null $fieldResolver
 *
 * @api
 */
static function promiseToExecute(
    GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter,
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $documentNode,
    $rootValue = null,
    $contextValue = null,
    array $variableValues = null,
    string $operationName = null,
    callable $fieldResolver = null
): GraphQL\Executor\Promise\Promise
```

## GraphQL\Executor\ExecutionResult

Returned after [query execution](executing-queries.md).
Represents both - result of successful execution and of a failed one
(with errors collected in `errors` prop)

Could be converted to [spec-compliant](https://facebook.github.io/graphql/#sec-Response-Format)
serializable array using `toArray()`

### GraphQL\Executor\ExecutionResult Props

```php
/**
 * Data collected from resolvers during query execution
 *
 * @api
 * @var mixed[]
 */
public $data;

/**
 * Errors registered during query execution.
 *
 * If an error was caused by exception thrown in resolver, $error->getPrevious() would
 * contain original exception.
 *
 * @api
 * @var Error[]
 */
public $errors;

/**
 * User-defined serializable array of extensions included in serialized result.
 * Conforms to
 *
 * @api
 * @var mixed[]
 */
public $extensions;
```

### GraphQL\Executor\ExecutionResult Methods

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
 */
function setErrorFormatter(callable $errorFormatter): self
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
 */
function setErrorsHandler(callable $handler): self
```

```php
/**
 * Converts GraphQL query result to spec-compliant serializable array using provided
 * errors handler and formatter.
 *
 * If debug argument is passed, output of error formatter is enriched which debugging information
 * ("debugMessage", "trace" keys depending on flags).
 *
 * $debug argument must sum of flags from @see \GraphQL\Error\DebugFlag
 *
 * @return mixed[]
 *
 * @api
 */
function toArray(int $debug = 'GraphQL\\Error\\DebugFlag::NONE'): array
```

## GraphQL\Executor\Promise\PromiseAdapter

Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php))

### GraphQL\Executor\Promise\PromiseAdapter Methods

```php
/**
 * Return true if the value is a promise or a deferred of the underlying platform
 *
 * @param mixed $value
 *
 * @api
 */
function isThenable($value): bool
```

```php
/**
 * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance
 *
 * @param object $thenable
 *
 * @api
 */
function convertThenable($thenable): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
 * in Promises/A+ specs. Then returns new wrapped instance of GraphQL\Executor\Promise\Promise.
 *
 * @api
 */
function then(
    GraphQL\Executor\Promise\Promise $promise,
    callable $onFulfilled = null,
    callable $onRejected = null
): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Creates a Promise
 *
 * Expected resolver signature:
 *     function(callable $resolve, callable $reject)
 *
 * @api
 */
function create(callable $resolver): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Creates a fulfilled Promise for a value if the value is not a promise.
 *
 * @param mixed $value
 *
 * @api
 */
function createFulfilled($value = null): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Creates a rejected promise for a reason if the reason is not a promise. If
 * the provided reason is a promise, then it is returned as-is.
 *
 * @param Throwable $reason
 *
 * @api
 */
function createRejected($reason): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Given an array of promises (or values), returns a promise that is fulfilled when all the
 * items in the array are fulfilled.
 *
 * @param Promise[]|mixed[] $promisesOrValues Promises or values.
 *
 * @api
 */
function all(array $promisesOrValues): GraphQL\Executor\Promise\Promise
```

## GraphQL\Validator\DocumentValidator

Implements the "Validation" section of the spec.

Validation runs synchronously, returning an array of encountered errors, or
an empty array if no errors were encountered and the document is valid.

A list of specific validation rules may be provided. If not provided, the
default list of rules defined by the GraphQL specification will be used.

Each validation rule is an instance of GraphQL\Validator\Rules\ValidationRule
which returns a visitor (see the [GraphQL\Language\Visitor API](class-reference.md#graphqllanguagevisitor)).

Visitor methods are expected to return an instance of [GraphQL\Error\Error](class-reference.md#graphqlerrorerror),
or array of such instances when invalid.

Optionally a custom TypeInfo instance may be provided. If not provided, one
will be created from the provided schema.

### GraphQL\Validator\DocumentValidator Methods

```php
/**
 * Primary method for query validation. See class description for details.
 *
 * @param array<ValidationRule>|null $rules
 *
 * @return array<int, Error>
 *
 * @api
 */
static function validate(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $ast,
    array $rules = null,
    GraphQL\Utils\TypeInfo $typeInfo = null
): array
```

```php
/**
 * Returns all global validation rules.
 *
 * @return array<class-string<ValidationRule>, ValidationRule>
 *
 * @api
 */
static function allRules(): array
```

```php
/**
 * Returns global validation rule by name. Standard rules are named by class name, so
 * example usage for such rules:
 *
 * $rule = DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
 *
 * @param string $name
 *
 * @api
 */
static function getRule($name): GraphQL\Validator\Rules\ValidationRule
```

```php
/**
 * Add rule to list of global validation rules
 *
 * @api
 */
static function addRule(GraphQL\Validator\Rules\ValidationRule $rule): void
```

## GraphQL\Error\Error

Describes an Error found during the parse, validate, or
execute phases of performing a GraphQL operation. In addition to a message
and stack trace, it also includes information about the locations in a
GraphQL document and/or execution result that correspond to the Error.

When the error was caused by an exception thrown in resolver, original exception
is available via `getPrevious()`.

Also read related docs on [error handling](error-handling.md)

Class extends standard PHP `\Exception`, so all standard methods of base `\Exception` class
are available in addition to those listed below.

### GraphQL\Error\Error Methods

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
 * @return array<int, SourceLocation>
 *
 * @api
 */
function getLocations(): array
```

```php
/**
 * Returns an array describing the path from the root value to the field which produced this error.
 * Only included for execution errors.
 *
 * @return array<int, int|string>|null
 *
 * @api
 */
function getPath(): array
```

## GraphQL\Error\Warning

Encapsulates warnings produced by the library.

Warnings can be suppressed (individually or all) if required.
Also it is possible to override warning handler (which is **trigger_error()** by default)

### GraphQL\Error\Warning Constants

```php
const WARNING_ASSIGN = 2;
const WARNING_CONFIG = 4;
const WARNING_FULL_SCHEMA_SCAN = 8;
const WARNING_CONFIG_DEPRECATION = 16;
const WARNING_NOT_A_TYPE = 32;
const ALL = 63;
```

### GraphQL\Error\Warning Methods

```php
/**
 * Sets warning handler which can intercept all system warnings.
 * When not set, trigger_error() is used to notify about warnings.
 *
 * @api
 */
static function setWarningHandler(callable $warningHandler = null): void
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
 * @param bool|int $suppress
 *
 * @api
 */
static function suppress($suppress = true): void
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
 * @param bool|int $enable
 *
 * @api
 */
static function enable($enable = true): void
```

## GraphQL\Error\ClientAware

Implementing ClientAware allows graphql-php to decide if this error is safe to be shown to clients.

Only errors that both implement this interface and return true from `isClientSafe()`
will retain their original error message during formatting.

All other errors will have their message replaced with "Internal server error".

### GraphQL\Error\ClientAware Methods

```php
/**
 * Is it safe to show the error message to clients?
 *
 * @api
 */
function isClientSafe(): bool
```

## GraphQL\Error\DebugFlag

Collection of flags for [error debugging](error-handling.md#debugging-tools).

### GraphQL\Error\DebugFlag Constants

```php
const NONE = 0;
const INCLUDE_DEBUG_MESSAGE = 1;
const INCLUDE_TRACE = 2;
const RETHROW_INTERNAL_EXCEPTIONS = 4;
const RETHROW_UNSAFE_EXCEPTIONS = 8;
```

## GraphQL\Error\FormattedError

This class is used for [default error formatting](error-handling.md).
It converts PHP exceptions to [spec-compliant errors](https://facebook.github.io/graphql/#sec-Errors)
and provides tools for error debugging.

@phpstan-type FormattedErrorArray array{
 message: string,
 locations?: array<int, array{line: int, column: int}>,
 path?: array<int, int|string>,
 extensions?: array<string, mixed>,
}

### GraphQL\Error\FormattedError Methods

```php
/**
 * Set default error message for internal errors formatted using createFormattedError().
 * This value can be overridden by passing 3rd argument to `createFormattedError()`.
 *
 * @api
 */
static function setInternalErrorMessage(string $msg): void
```

```php
/**
 * Convert any exception to a GraphQL spec compliant array.
 *
 * This method only exposes the exception message when the given exception
 * implements the ClientAware interface, or when debug flags are passed.
 *
 * For a list of available debug flags @see \GraphQL\Error\DebugFlag constants.
 *
 * @return FormattedErrorArray
 *
 * @api
 */
static function createFromException(
    Throwable $exception,
    int $debugFlag = 'GraphQL\\Error\\DebugFlag::NONE',
    string $internalErrorMessage = null
): array
```

```php
/**
 * Returns error trace as serializable array.
 *
 * @return array<int, array{
 *     file: string,
 *     line: int,
 *     function?: string,
 *     call?: string,
 * }>
 *
 * @api
 */
static function toSafeTrace(Throwable $error): array
```

## GraphQL\Server\StandardServer

GraphQL server compatible with both: [express-graphql](https://github.com/graphql/express-graphql)
and [Apollo Server](https://github.com/apollographql/graphql-server).
Usage Example:

    $server = new StandardServer([
      'schema' => $mySchema
    ]);
    $server->handleRequest();

Or using [ServerConfig](class-reference.md#graphqlserverserverconfig) instance:

    $config = GraphQL\Server\ServerConfig::create()
        ->setSchema($mySchema)
        ->setContext($myContext);

    $server = new GraphQL\Server\StandardServer($config);
    $server->handleRequest();

See [dedicated section in docs](executing-queries.md#using-server) for details.

### GraphQL\Server\StandardServer Methods

```php
/**
 * Converts and exception to error and sends spec-compliant HTTP 500 error.
 * Useful when an exception is thrown somewhere outside of server execution context
 * (e.g. during schema instantiation).
 *
 * @api
 */
static function send500Error(
    Throwable $error,
    int $debug = 'GraphQL\\Error\\DebugFlag::NONE',
    bool $exitWhenDone = false
): void
```

```php
/**
 * Creates new instance of a standard GraphQL HTTP server
 *
 * @param ServerConfig|array<string, mixed> $config
 *
 * @api
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
 * @param OperationParams|array<OperationParams> $parsedBody
 *
 * @api
 */
function handleRequest($parsedBody = null, bool $exitWhenDone = false): void
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
 * @param OperationParams|array<OperationParams> $parsedBody
 *
 * @return ExecutionResult|array<int, ExecutionResult>|Promise
 *
 * @throws InvariantViolation
 *
 * @api
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
 * @return ResponseInterface|Promise
 *
 * @api
 */
function processPsrRequest(
    Psr\Http\Message\RequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    Psr\Http\Message\StreamInterface $writableBodyStream
)
```

```php
/**
 * Executes GraphQL operation and returns execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter)
 *
 * @return ExecutionResult|array<int, ExecutionResult>|Promise
 *
 * @api
 */
function executePsrRequest(Psr\Http\Message\RequestInterface $request)
```

```php
/**
 * Returns an instance of Server helper, which contains most of the actual logic for
 * parsing / validating / executing request (which could be re-used by other server implementations)
 *
 * @api
 */
function getHelper(): GraphQL\Server\Helper
```

## GraphQL\Server\ServerConfig

Server configuration class.
Could be passed directly to server constructor. List of options accepted by **create** method is
[described in docs](executing-queries.md#server-configuration-options).

Usage example:

    $config = GraphQL\Server\ServerConfig::create()
        ->setSchema($mySchema)
        ->setContext($myContext);

    $server = new GraphQL\Server\StandardServer($config);

### GraphQL\Server\ServerConfig Methods

```php
/**
 * Converts an array of options to instance of ServerConfig
 * (or just returns empty config when array is not passed).
 *
 * @param array<string, mixed> $config
 *
 * @api
 */
static function create(array $config = []): self
```

```php
/**
 * @api
 */
function setSchema(GraphQL\Type\Schema $schema): self
```

```php
/**
 * @param mixed|callable $context
 *
 * @api
 */
function setContext($context): self
```

```php
/**
 * @param mixed|callable $rootValue
 *
 * @api
 */
function setRootValue($rootValue): self
```

```php
/**
 * @param callable(Error): array<string, mixed> $errorFormatter
 *
 * @api
 */
function setErrorFormatter(callable $errorFormatter): self
```

```php
/**
 * @param callable(array<int, Error> $errors, callable(Error): array<string, mixed> $formatter): array<int, array<string, mixed>> $handler
 *
 * @api
 */
function setErrorsHandler(callable $handler): self
```

```php
/**
 * Set validation rules for this server.
 *
 * @param array<ValidationRule>|callable|null $validationRules
 *
 * @api
 */
function setValidationRules($validationRules): self
```

```php
/**
 * @api
 */
function setFieldResolver(callable $fieldResolver): self
```

```php
/**
 * @param callable(string $queryId, OperationParams $params): (string|DocumentNode) $persistentQueryLoader
 *
 * @api
 */
function setPersistentQueryLoader(callable $persistentQueryLoader): self
```

```php
/**
 * Set response debug flags.
 *
 * @see \GraphQL\Error\DebugFlag class for a list of all available flags
 *
 * @api
 */
function setDebugFlag(int $debugFlag = 'GraphQL\\Error\\DebugFlag::INCLUDE_DEBUG_MESSAGE'): self
```

```php
/**
 * Allow batching queries (disabled by default).
 *
 * @api
 */
function setQueryBatching(bool $enableBatching): self
```

```php
/**
 * @api
 */
function setPromiseAdapter(GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter): self
```

## GraphQL\Server\Helper

Contains functionality that could be re-used by various server implementations.

### GraphQL\Server\Helper Methods

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
 * @return OperationParams|array<int, OperationParams>
 *
 * @throws RequestError
 *
 * @api
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
 * @param array<mixed> $bodyParams
 * @param array<mixed> $queryParams
 *
 * @return OperationParams|array<int, OperationParams>
 *
 * @throws RequestError
 *
 * @api
 */
function parseRequestParams(string $method, array $bodyParams, array $queryParams)
```

```php
/**
 * Checks validity of OperationParams extracted from HTTP request and returns an array of errors
 * if params are invalid (or empty array when params are valid)
 *
 * @return array<int, RequestError>
 *
 * @api
 */
function validateOperationParams(GraphQL\Server\OperationParams $params): array
```

```php
/**
 * Executes GraphQL operation with given server configuration and returns execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter)
 *
 * @return ExecutionResult|Promise
 *
 * @api
 */
function executeOperation(GraphQL\Server\ServerConfig $config, GraphQL\Server\OperationParams $op)
```

```php
/**
 * Executes batched GraphQL operations with shared promise queue
 * (thus, effectively batching deferreds|promises of all queries at once)
 *
 * @param array<OperationParams> $operations
 *
 * @return ExecutionResult|array<int, ExecutionResult>|Promise
 *
 * @api
 */
function executeBatch(GraphQL\Server\ServerConfig $config, array $operations)
```

```php
/**
 * Send response using standard PHP `header()` and `echo`.
 *
 * @param Promise|ExecutionResult|array<ExecutionResult> $result
 *
 * @api
 */
function sendResponse($result, bool $exitWhenDone = false): void
```

```php
/**
 * Converts PSR-7 request to OperationParams or an array thereof.
 *
 * @return OperationParams|array<OperationParams>
 *
 * @throws RequestError
 *
 * @api
 */
function parsePsrRequest(Psr\Http\Message\RequestInterface $request)
```

```php
/**
 * Converts query execution result to PSR-7 response.
 *
 * @param Promise|ExecutionResult|array<ExecutionResult> $result
 *
 * @return Promise|ResponseInterface
 *
 * @api
 */
function toPsrResponse(
    $result,
    Psr\Http\Message\ResponseInterface $response,
    Psr\Http\Message\StreamInterface $writableBodyStream
)
```

## GraphQL\Server\OperationParams

Structure representing parsed HTTP parameters for GraphQL operation

### GraphQL\Server\OperationParams Props

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
 * @var string|null
 */
public $queryId;

/**
 * @api
 * @var string|null
 */
public $query;

/**
 * @api
 * @var string
 */
public $operation;

/**
 * @api
 * @var array<string, mixed>|null
 */
public $variables;

/**
 * @api
 * @var array<string, mixed>|null
 */
public $extensions;
```

### GraphQL\Server\OperationParams Methods

```php
/**
 * Creates an instance from given array
 *
 * @param array<string, mixed> $params
 *
 * @api
 */
static function create(array $params, bool $readonly = false): GraphQL\Server\OperationParams
```

```php
/**
 * @return mixed
 *
 * @api
 */
function getOriginalInput(string $key)
```

```php
/**
 * Indicates that operation is executed in read-only context
 * (e.g. via HTTP GET request)
 *
 * @api
 */
function isReadOnly(): bool
```

## GraphQL\Utils\BuildSchema

Build instance of @see \GraphQL\Type\Schema out of schema language definition (string or parsed AST).

See [schema definition language docs](schema-definition-language.md) for details.

@phpstan-type Options array{
  commentDescriptions?: bool,
}

   - commentDescriptions:
       Provide true to use preceding comments as the description.
       This option is provided to ease adoption and will be removed in v16.

### GraphQL\Utils\BuildSchema Methods

```php
/**
 * A helper function to build a GraphQLSchema directly from a source
 * document.
 *
 * @param DocumentNode|Source|string $source
 * @param array<string, bool>        $options
 * @phpstan-param Options $options
 *
 * @api
 */
static function build($source, callable $typeConfigDecorator = null, array $options = []): GraphQL\Type\Schema
```

```php
/**
 * This takes the AST of a schema from @see \GraphQL\Language\Parser::parse().
 *
 * If no schema definition is provided, then it will look for types named Query and Mutation.
 *
 * Given that AST it constructs a @see \GraphQL\Type\Schema. The resulting schema
 * has no resolve methods, so execution will use default resolvers.
 *
 * @param array<string, bool> $options
 * @phpstan-param Options $options
 *
 * @throws Error
 *
 * @api
 */
static function buildAST(
    GraphQL\Language\AST\DocumentNode $ast,
    callable $typeConfigDecorator = null,
    array $options = []
): GraphQL\Type\Schema
```

## GraphQL\Utils\AST

Various utilities dealing with AST

### GraphQL\Utils\AST Methods

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
 * @param mixed[] $node
 *
 * @api
 */
static function fromArray(array $node): GraphQL\Language\AST\Node
```

```php
/**
 * Convert AST node to serializable array
 *
 * @return mixed[]
 *
 * @api
 */
static function toArray(GraphQL\Language\AST\Node $node): array
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
 * @param Type|mixed|null $value
 *
 * @return ObjectValueNode|ListValueNode|BooleanValueNode|IntValueNode|FloatValueNode|EnumValueNode|StringValueNode|NullValueNode|null
 *
 * @api
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
 * @param VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode|null $valueNode
 * @param mixed[]|null                                                                                                                             $variables
 *
 * @return mixed[]|stdClass|null
 *
 * @throws Exception
 *
 * @api
 */
static function valueFromAST(
    GraphQL\Language\AST\ValueNode $valueNode,
    GraphQL\Type\Definition\Type $type,
    array $variables = null
)
```

```php
/**
 * Produces a PHP value given a GraphQL Value AST.
 *
 * Unlike `valueFromAST()`, no type is provided. The resulting PHP value
 * will reflect the provided GraphQL value AST.
 *
 * | GraphQL Value        | PHP Value     |
 * | -------------------- | ------------- |
 * | Input Object         | Assoc Array   |
 * | List                 | Array         |
 * | Boolean              | Boolean       |
 * | String               | String        |
 * | Int / Float          | Int / Float   |
 * | Enum                 | Mixed         |
 * | Null                 | null          |
 *
 * @param Node         $valueNode
 * @param mixed[]|null $variables
 *
 * @return mixed
 *
 * @throws Exception
 *
 * @api
 */
static function valueFromASTUntyped($valueNode, array $variables = null)
```

```php
/**
 * Returns type definition for given AST Type node
 *
 * @param NamedTypeNode|ListTypeNode|NonNullTypeNode $inputTypeNode
 *
 * @throws Exception
 *
 * @api
 */
static function typeFromAST(GraphQL\Type\Schema $schema, $inputTypeNode): GraphQL\Type\Definition\Type
```

```php
/**
 * Returns the operation within a document by name.
 *
 * If a name is not provided, an operation is only returned if the document has exactly one.
 *
 * @api
 */
static function getOperationAST(GraphQL\Language\AST\DocumentNode $document, string $operationName = null): GraphQL\Language\AST\OperationDefinitionNode
```

```php
/**
 * Provided a collection of ASTs, presumably each from different files,
 * concatenate the ASTs together into batched AST, useful for validating many
 * GraphQL source files which together represent one conceptual application.
 *
 * @param array<DocumentNode> $documents
 *
 * @api
 */
static function concatAST(array $documents): GraphQL\Language\AST\DocumentNode
```

## GraphQL\Utils\SchemaPrinter

Prints the contents of a Schema in schema definition language.

@phpstan-type Options array{commentDescriptions?: bool}
   Available options:
   - commentDescriptions:
       Provide true to use preceding comments as the description.
       This option is provided to ease adoption and will be removed in v16.

### GraphQL\Utils\SchemaPrinter Methods

```php
/**
 * @param array<string, bool> $options
 * @phpstan-param Options $options
 *
 * @api
 */
static function doPrint(GraphQL\Type\Schema $schema, array $options = []): string
```

```php
/**
 * @param array<string, bool> $options
 * @phpstan-param Options $options
 *
 * @api
 */
static function printIntrospectionSchema(GraphQL\Type\Schema $schema, array $options = []): string
```

