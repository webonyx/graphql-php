## GraphQL\GraphQL

This is the primary facade for fulfilling GraphQL operations.
See [related documentation](executing-queries.md).

@phpstan-import-type FieldResolver from Executor

@see \GraphQL\Tests\GraphQLTest

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
 *    If the passed object implements the `ScopedContext` interface,
 *    its `clone()` method will be called before passing the context down to a field.
 *    This allows passing information to child fields in the query tree without affecting sibling or parent fields.
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
 * @param string|DocumentNode        $source
 * @param mixed                      $rootValue
 * @param mixed                      $contextValue
 * @param array<string, mixed>|null  $variableValues
 * @param array<ValidationRule>|null $validationRules
 *
 * @api
 *
 * @throws \Exception
 * @throws InvariantViolation
 */
static function executeQuery(
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $contextValue = null,
    ?array $variableValues = null,
    ?string $operationName = null,
    ?callable $fieldResolver = null,
    ?array $validationRules = null
): GraphQL\Executor\ExecutionResult
```

```php
/**
 * Same as executeQuery(), but requires PromiseAdapter and always returns a Promise.
 * Useful for Async PHP platforms.
 *
 * @param string|DocumentNode $source
 * @param mixed $rootValue
 * @param mixed $context
 * @param array<string, mixed>|null $variableValues
 * @param array<ValidationRule>|null $validationRules Defaults to using all available rules
 *
 * @api
 *
 * @throws \Exception
 */
static function promiseToExecute(
    GraphQL\Executor\Promise\PromiseAdapter $promiseAdapter,
    GraphQL\Type\Schema $schema,
    $source,
    $rootValue = null,
    $context = null,
    ?array $variableValues = null,
    ?string $operationName = null,
    ?callable $fieldResolver = null,
    ?array $validationRules = null
): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Returns directives defined in GraphQL spec.
 *
 * @throws InvariantViolation
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
 * @throws InvariantViolation
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
 *
 * @throws InvariantViolation
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
 * @phpstan-param FieldResolver $fn
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
 *
 * @throws InvariantViolation
 */
static function int(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 *
 * @throws InvariantViolation
 */
static function float(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 *
 * @throws InvariantViolation
 */
static function string(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 *
 * @throws InvariantViolation
 */
static function boolean(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @api
 *
 * @throws InvariantViolation
 */
static function id(): GraphQL\Type\Definition\ScalarType
```

```php
/**
 * @template T of Type
 *
 * @param T|callable():T $type
 *
 * @return ListOfType<T>
 *
 * @api
 */
static function listOf($type): GraphQL\Type\Definition\ListOfType
```

```php
/**
 * @param (NullableType&Type)|callable():(NullableType&Type) $type
 *
 * @api
 */
static function nonNull($type): GraphQL\Type\Definition\NonNull
```

```php
/**
 * @param mixed $type
 *
 * @api
 */
static function isInputType($type): bool
```

```php
/**
 * @return (Type&NamedType)|null
 *
 * @api
 */
static function getNamedType(?GraphQL\Type\Definition\Type $type): ?GraphQL\Type\Definition\Type
```

```php
/**
 * @param mixed $type
 *
 * @api
 */
static function isOutputType($type): bool
```

```php
/**
 * @param mixed $type
 *
 * @api
 */
static function isLeafType($type): bool
```

```php
/**
 * @param mixed $type
 *
 * @api
 */
static function isCompositeType($type): bool
```

```php
/**
 * @param mixed $type
 *
 * @api
 */
static function isAbstractType($type): bool
```

```php
/**
 * @return Type&NullableType
 *
 * @api
 */
static function getNullableType(GraphQL\Type\Definition\Type $type): GraphQL\Type\Definition\Type
```

## GraphQL\Type\Definition\ResolveInfo

Structure containing information useful for field resolution process.

Passed as 4th argument to every field resolver. See [docs on field resolving (data fetching)](data-fetching.md).

@phpstan-import-type QueryPlanOptions from QueryPlan

@phpstan-type Path array<int, string|int>

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
 *
 * @var \ArrayObject<int, FieldNode>
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
 *
 * @var array<int, string|int>
 *
 * @phpstan-var Path
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
 *
 * @var array<string, FragmentDefinitionNode>
 */
public $fragments;

/**
 * Root value passed to query execution.
 *
 * @api
 *
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
 *
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

@see Type, NamedType

@phpstan-type MaybeLazyObjectType ObjectType|(callable(): (ObjectType|null))|null
@phpstan-type TypeLoader callable(string $typeName): ((Type&NamedType)|null)
@phpstan-type Types iterable<Type&NamedType>|(callable(): iterable<Type&NamedType>)
@phpstan-type SchemaConfigOptions array{
query?: MaybeLazyObjectType,
mutation?: MaybeLazyObjectType,
subscription?: MaybeLazyObjectType,
types?: Types|null,
directives?: array<Directive>|null,
typeLoader?: TypeLoader|null,
assumeValid?: bool|null,
astNode?: SchemaDefinitionNode|null,
extensionASTNodes?: array<SchemaExtensionNode>|null,
}

### GraphQL\Type\SchemaConfig Methods

```php
/**
 * Converts an array of options to instance of SchemaConfig
 * (or just returns empty config when array is not passed).
 *
 * @phpstan-param SchemaConfigOptions $options
 *
 * @throws InvariantViolation
 *
 * @api
 */
static function create(array $options = []): self
```

```php
/**
 * @return MaybeLazyObjectType
 *
 * @api
 */
function getQuery()
```

```php
/**
 * @param MaybeLazyObjectType $query
 *
 * @throws InvariantViolation
 *
 * @api
 */
function setQuery($query): self
```

```php
/**
 * @return MaybeLazyObjectType
 *
 * @api
 */
function getMutation()
```

```php
/**
 * @param MaybeLazyObjectType $mutation
 *
 * @throws InvariantViolation
 *
 * @api
 */
function setMutation($mutation): self
```

```php
/**
 * @return MaybeLazyObjectType
 *
 * @api
 */
function getSubscription()
```

```php
/**
 * @param MaybeLazyObjectType $subscription
 *
 * @throws InvariantViolation
 *
 * @api
 */
function setSubscription($subscription): self
```

```php
/**
 * @return array|callable
 *
 * @phpstan-return Types
 *
 * @api
 */
function getTypes()
```

```php
/**
 * @param array|callable $types
 *
 * @phpstan-param Types $types
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
function getDirectives(): ?array
```

```php
/**
 * @param array<Directive>|null $directives
 *
 * @api
 */
function setDirectives(?array $directives): self
```

```php
/**
 * @return callable|null $typeLoader
 *
 * @phpstan-return TypeLoader|null $typeLoader
 *
 * @api
 */
function getTypeLoader(): ?callable
```

```php
/**
 * @phpstan-param TypeLoader|null $typeLoader
 *
 * @api
 */
function setTypeLoader(?callable $typeLoader): self
```

## GraphQL\Type\Schema

Schema Definition (see [schema definition docs](schema-definition.md)).

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

@phpstan-import-type SchemaConfigOptions from SchemaConfig
@phpstan-import-type OperationType from OperationDefinitionNode

@see \GraphQL\Tests\Type\SchemaTest

### GraphQL\Type\Schema Methods

```php
/**
 * @param SchemaConfig|array<string, mixed> $config
 *
 * @phpstan-param SchemaConfig|SchemaConfigOptions $config
 *
 * @throws InvariantViolation
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
 * @throws InvariantViolation
 *
 * @return array<string, Type&NamedType> Keys represent type names, values are instances of corresponding type definitions
 *
 * @api
 */
function getTypeMap(): array
```

```php
/**
 * Returns a list of directives supported by this schema.
 *
 * @throws InvariantViolation
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
function getQueryType(): ?GraphQL\Type\Definition\ObjectType
```

```php
/**
 * Returns root mutation type.
 *
 * @api
 */
function getMutationType(): ?GraphQL\Type\Definition\ObjectType
```

```php
/**
 * Returns schema subscription.
 *
 * @api
 */
function getSubscriptionType(): ?GraphQL\Type\Definition\ObjectType
```

```php
/**
 * Returns a type by name.
 *
 * @throws InvariantViolation
 *
 * @return (Type&NamedType)|null
 *
 * @api
 */
function getType(string $name): ?GraphQL\Type\Definition\Type
```

```php
/**
 * Returns all possible concrete types for given abstract type
 * (implementations for interfaces and members of union type for unions).
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @param AbstractType&Type $abstractType
 *
 * @throws InvariantViolation
 *
 * @return array<ObjectType>
 *
 * @api
 */
function getPossibleTypes(GraphQL\Type\Definition\AbstractType $abstractType): array
```

```php
/**
 * Returns all types that implement a given interface type.
 *
 * This operation requires full schema scan. Do not use in production environment.
 *
 * @api
 *
 * @throws InvariantViolation
 */
function getImplementations(GraphQL\Type\Definition\InterfaceType $abstractType): GraphQL\Utils\InterfaceImplementations
```

```php
/**
 * Returns true if the given type is a sub type of the given abstract type.
 *
 * @param AbstractType&Type $abstractType
 * @param ImplementingType&Type $maybeSubType
 *
 * @api
 *
 * @throws InvariantViolation
 */
function isSubType(
    GraphQL\Type\Definition\AbstractType $abstractType,
    GraphQL\Type\Definition\ImplementingType $maybeSubType
): bool
```

```php
/**
 * Returns instance of directive by name.
 *
 * @api
 *
 * @throws InvariantViolation
 */
function getDirective(string $name): ?GraphQL\Type\Definition\Directive
```

```php
/**
 * Throws if the schema is not valid.
 *
 * This operation requires a full schema scan. Do not use in production environment.
 *
 * @throws Error
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
 * @throws InvariantViolation
 *
 * @return array<int, Error>
 *
 * @api
 */
function validate(): array
```

## GraphQL\Language\Parser

Parses string containing GraphQL query language or [schema definition language](schema-definition-language.md) to Abstract Syntax Tree.

@phpstan-type ParserOptions array{
noLocation?: bool,
allowLegacySDLEmptyFields?: bool,
allowLegacySDLImplementsInterfaces?: bool,
experimentalFragmentVariables?: bool
}

noLocation:
(By default, the parser creates AST nodes that know the location
in the source that they correspond to. This configuration flag
disables that behavior for performance or testing.)

allowLegacySDLEmptyFields:
If enabled, the parser will parse empty fields sets in the Schema
Definition Language. Otherwise, the parser will follow the current
specification.

This option is provided to ease adoption of the final SDL specification
and will be removed in a future major release.

allowLegacySDLImplementsInterfaces:
If enabled, the parser will parse implemented interfaces with no `&`
character between each interface. Otherwise, the parser will follow the
current specification.

This option is provided to ease adoption of the final SDL specification
and will be removed in a future major release.

experimentalFragmentVariables:
(If enabled, the parser will understand and parse variable definitions
contained in a fragment definition. They'll be represented in the
`variableDefinitions` field of the FragmentDefinitionNode.

The syntax is identical to normal, query-defined variables. For example:

    fragment A($var: Boolean = false) on T  {
      ...
    }

Note: this feature is experimental and may change or be removed in the
future.)
Those magic functions allow partial parsing:

@method static NameNode name(Source|string $source, bool[] $options = [])
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
@method static SchemaExtensionNode schemaTypeExtension(Source|string $source, bool[] $options = [])
@method static ScalarTypeExtensionNode scalarTypeExtension(Source|string $source, bool[] $options = [])
@method static ObjectTypeExtensionNode objectTypeExtension(Source|string $source, bool[] $options = [])
@method static InterfaceTypeExtensionNode interfaceTypeExtension(Source|string $source, bool[] $options = [])
@method static UnionTypeExtensionNode unionTypeExtension(Source|string $source, bool[] $options = [])
@method static EnumTypeExtensionNode enumTypeExtension(Source|string $source, bool[] $options = [])
@method static InputObjectTypeExtensionNode inputObjectTypeExtension(Source|string $source, bool[] $options = [])
@method static DirectiveDefinitionNode directiveDefinition(Source|string $source, bool[] $options = [])
@method static NodeList<NameNode> directiveLocations(Source|string $source, bool[] $options = [])
@method static NameNode directiveLocation(Source|string $source, bool[] $options = [])

@see \GraphQL\Tests\Language\ParserTest

### GraphQL\Language\Parser Methods

```php
/**
 * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
 *
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * @param Source|string $source
 *
 * @phpstan-param ParserOptions $options
 *
 * @api
 *
 * @throws \JsonException
 * @throws SyntaxError
 */
static function parse($source, array $options = []): GraphQL\Language\AST\DocumentNode
```

```php
/**
 * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for that value.
 *
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * This is useful within tools that operate upon GraphQL Values directly and
 * in isolation of complete GraphQL documents.
 *
 * Consider providing the results to the utility function: `GraphQL\Utils\AST::valueFromAST()`.
 *
 * @param Source|string $source
 *
 * @phpstan-param ParserOptions $options
 *
 * @throws \JsonException
 * @throws SyntaxError
 *
 * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|NullValueNode|ObjectValueNode|StringValueNode|VariableNode
 *
 * @api
 */
static function parseValue($source, array $options = [])
```

```php
/**
 * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for that type.
 *
 * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
 *
 * This is useful within tools that operate upon GraphQL Types directly and
 * in isolation of complete GraphQL documents.
 *
 * Consider providing the results to the utility function: `GraphQL\Utils\AST::typeFromAST()`.
 *
 * @param Source|string $source
 *
 * @phpstan-param ParserOptions $options
 *
 * @throws \JsonException
 * @throws SyntaxError
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

@see \GraphQL\Tests\Language\PrinterTest

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

By returning different values from the `enter` and `leave` functions, the
behavior of the visitor can be altered.

- no return (`void`) or return `null`: no action
- `Visitor::skipNode()`: skips over the subtree at the current node of the AST
- `Visitor::stop()`: stop the Visitor completely
- `Visitor::removeNode()`: remove the current node
- return any other value: replace this node with the returned value

When using `visit()` to edit an AST, the original AST will not be modified, and
a new version of the AST with the changes applied will be returned from the
visit function.

$editedAST = Visitor::visit($ast, [
'enter' => function ($node, $key, $parent, $path, $ancestors) {
// ...
},
'leave' => function ($node, $key, $parent, $path, $ancestors) {
// ...
}
]);

Alternatively to providing `enter` and `leave` functions, a visitor can
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

@phpstan-type NodeVisitor callable(Node): (VisitorOperation|null|false|void)
@phpstan-type VisitorArray array<string, NodeVisitor>|array<string, array<string, NodeVisitor>>

@see \GraphQL\Tests\Language\VisitorTest

### GraphQL\Language\Visitor Methods

```php
/**
 * Visit the AST (see class description for details).
 *
 * @param NodeList<Node>|Node $root
 * @param VisitorArray $visitor
 * @param array<string, mixed>|null $keyMap
 *
 * @throws \Exception
 *
 * @return mixed
 *
 * @api
 */
static function visit(object $root, array $visitor, ?array $keyMap = null)
```

```php
/**
 * Returns marker for stopping.
 *
 * @api
 */
static function stop(): GraphQL\Language\VisitorStop
```

```php
/**
 * Returns marker for skipping the subtree at the current node.
 *
 * @api
 */
static function skipNode(): GraphQL\Language\VisitorSkipNode
```

```php
/**
 * Returns marker for removing the current node.
 *
 * @api
 */
static function removeNode(): GraphQL\Language\VisitorRemoveNode
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
const CLASS_MAP = [
    'Name' => 'GraphQL\\Language\\AST\\NameNode',
    'Document' => 'GraphQL\\Language\\AST\\DocumentNode',
    'OperationDefinition' => 'GraphQL\\Language\\AST\\OperationDefinitionNode',
    'VariableDefinition' => 'GraphQL\\Language\\AST\\VariableDefinitionNode',
    'Variable' => 'GraphQL\\Language\\AST\\VariableNode',
    'SelectionSet' => 'GraphQL\\Language\\AST\\SelectionSetNode',
    'Field' => 'GraphQL\\Language\\AST\\FieldNode',
    'Argument' => 'GraphQL\\Language\\AST\\ArgumentNode',
    'FragmentSpread' => 'GraphQL\\Language\\AST\\FragmentSpreadNode',
    'InlineFragment' => 'GraphQL\\Language\\AST\\InlineFragmentNode',
    'FragmentDefinition' => 'GraphQL\\Language\\AST\\FragmentDefinitionNode',
    'IntValue' => 'GraphQL\\Language\\AST\\IntValueNode',
    'FloatValue' => 'GraphQL\\Language\\AST\\FloatValueNode',
    'StringValue' => 'GraphQL\\Language\\AST\\StringValueNode',
    'BooleanValue' => 'GraphQL\\Language\\AST\\BooleanValueNode',
    'EnumValue' => 'GraphQL\\Language\\AST\\EnumValueNode',
    'NullValue' => 'GraphQL\\Language\\AST\\NullValueNode',
    'ListValue' => 'GraphQL\\Language\\AST\\ListValueNode',
    'ObjectValue' => 'GraphQL\\Language\\AST\\ObjectValueNode',
    'ObjectField' => 'GraphQL\\Language\\AST\\ObjectFieldNode',
    'Directive' => 'GraphQL\\Language\\AST\\DirectiveNode',
    'NamedType' => 'GraphQL\\Language\\AST\\NamedTypeNode',
    'ListType' => 'GraphQL\\Language\\AST\\ListTypeNode',
    'NonNullType' => 'GraphQL\\Language\\AST\\NonNullTypeNode',
    'SchemaDefinition' => 'GraphQL\\Language\\AST\\SchemaDefinitionNode',
    'OperationTypeDefinition' => 'GraphQL\\Language\\AST\\OperationTypeDefinitionNode',
    'ScalarTypeDefinition' => 'GraphQL\\Language\\AST\\ScalarTypeDefinitionNode',
    'ObjectTypeDefinition' => 'GraphQL\\Language\\AST\\ObjectTypeDefinitionNode',
    'FieldDefinition' => 'GraphQL\\Language\\AST\\FieldDefinitionNode',
    'InputValueDefinition' => 'GraphQL\\Language\\AST\\InputValueDefinitionNode',
    'InterfaceTypeDefinition' => 'GraphQL\\Language\\AST\\InterfaceTypeDefinitionNode',
    'UnionTypeDefinition' => 'GraphQL\\Language\\AST\\UnionTypeDefinitionNode',
    'EnumTypeDefinition' => 'GraphQL\\Language\\AST\\EnumTypeDefinitionNode',
    'EnumValueDefinition' => 'GraphQL\\Language\\AST\\EnumValueDefinitionNode',
    'InputObjectTypeDefinition' => 'GraphQL\\Language\\AST\\InputObjectTypeDefinitionNode',
    'ScalarTypeExtension' => 'GraphQL\\Language\\AST\\ScalarTypeExtensionNode',
    'ObjectTypeExtension' => 'GraphQL\\Language\\AST\\ObjectTypeExtensionNode',
    'InterfaceTypeExtension' => 'GraphQL\\Language\\AST\\InterfaceTypeExtensionNode',
    'UnionTypeExtension' => 'GraphQL\\Language\\AST\\UnionTypeExtensionNode',
    'EnumTypeExtension' => 'GraphQL\\Language\\AST\\EnumTypeExtensionNode',
    'InputObjectTypeExtension' => 'GraphQL\\Language\\AST\\InputObjectTypeExtensionNode',
    'DirectiveDefinition' => 'GraphQL\\Language\\AST\\DirectiveDefinitionNode',
];
```

## GraphQL\Executor\Executor

Implements the "Evaluating requests" section of the GraphQL specification.

@phpstan-type FieldResolver callable(mixed, array<string, mixed>, mixed, ResolveInfo): mixed
@phpstan-type ImplementationFactory callable(PromiseAdapter, Schema, DocumentNode, mixed, mixed, array<mixed>, ?string, callable): ExecutorImplementation

@see \GraphQL\Tests\Executor\ExecutorTest

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
 *
 * @phpstan-param FieldResolver|null $fieldResolver
 *
 * @api
 *
 * @throws InvariantViolation
 */
static function execute(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $documentNode,
    $rootValue = null,
    $contextValue = null,
    ?array $variableValues = null,
    ?string $operationName = null,
    ?callable $fieldResolver = null
): GraphQL\Executor\ExecutionResult
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
 *
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
    ?array $variableValues = null,
    ?string $operationName = null,
    ?callable $fieldResolver = null
): GraphQL\Executor\Promise\Promise
```

## GraphQL\Executor\ScopedContext

When the object passed as `$contextValue` to GraphQL execution implements this,
its `clone()` method will be called before passing the context down to a field.
This allows passing information to child fields in the query tree without affecting sibling or parent fields.

## GraphQL\Executor\ExecutionResult

Returned after [query execution](executing-queries.md).
Represents both - result of successful execution and of a failed one
(with errors collected in `errors` prop).

Could be converted to [spec-compliant](https://facebook.github.io/graphql/#sec-Response-Format)
serializable array using `toArray()`.

@phpstan-type SerializableError array{
message: string,
locations?: array<int, array{line: int, column: int}>,
path?: array<int, int|string>,
extensions?: array<string, mixed>
}
@phpstan-type SerializableErrors array<int, SerializableError>
@phpstan-type SerializableResult array{
data?: array<string, mixed>,
errors?: SerializableErrors,
extensions?: array<string, mixed>
}
@phpstan-type ErrorFormatter callable(\Throwable): SerializableError
@phpstan-type ErrorsHandler callable(array<Error> $errors, ErrorFormatter $formatter): SerializableErrors

@see \GraphQL\Tests\Executor\ExecutionResultTest

### GraphQL\Executor\ExecutionResult Props

```php
/**
 * Data collected from resolvers during query execution.
 *
 * @api
 *
 * @var array<string, mixed>|null
 */
public $data;

/**
 * Errors registered during query execution.
 *
 * If an error was caused by exception thrown in resolver, $error->getPrevious() would
 * contain original exception.
 *
 * @api
 *
 * @var array<Error>
 */
public $errors;

/**
 * User-defined serializable array of extensions included in serialized result.
 *
 * @api
 *
 * @var array<string, mixed>|null
 */
public $extensions;
```

### GraphQL\Executor\ExecutionResult Methods

```php
/**
 * Define custom error formatting (must conform to http://facebook.github.io/graphql/#sec-Errors).
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
 * @phpstan-param ErrorFormatter|null $errorFormatter
 *
 * @api
 */
function setErrorFormatter(?callable $errorFormatter): self
```

```php
/**
 * Define custom logic for error handling (filtering, logging, etc).
 *
 * Expected handler signature is:
 * fn (array $errors, callable $formatter): array
 *
 * Default handler is:
 * fn (array $errors, callable $formatter): array => array_map($formatter, $errors)
 *
 * @phpstan-param ErrorsHandler|null $errorsHandler
 *
 * @api
 */
function setErrorsHandler(?callable $errorsHandler): self
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
 * @phpstan-return SerializableResult
 *
 * @api
 */
function toArray(int $debug = 'GraphQL\\Error\\DebugFlag::NONE'): array
```

## GraphQL\Executor\Promise\PromiseAdapter

Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php)).

### GraphQL\Executor\Promise\PromiseAdapter Methods

```php
/**
 * Is the value a promise or a deferred of the underlying platform?
 *
 * @param mixed $value
 *
 * @api
 */
function isThenable($value): bool
```

```php
/**
 * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance.
 *
 * @param mixed $thenable
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
    ?callable $onFulfilled = null,
    ?callable $onRejected = null
): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Creates a Promise from the given resolver callable.
 *
 * @param callable(callable $resolve, callable $reject): void $resolver
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
 * Creates a rejected promise for a reason if the reason is not a promise.
 *
 * If the provided reason is a promise, then it is returned as-is.
 *
 * @api
 */
function createRejected(Throwable $reason): GraphQL\Executor\Promise\Promise
```

```php
/**
 * Given an iterable of promises (or values), returns a promise that is fulfilled when all the
 * items in the iterable are fulfilled.
 *
 * @param iterable<Promise|mixed> $promisesOrValues
 *
 * @api
 */
function all(iterable $promisesOrValues): GraphQL\Executor\Promise\Promise
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
 * Validate a GraphQL query against a schema.
 *
 * @param array<ValidationRule>|null $rules Defaults to using all available rules
 *
 * @throws \Exception
 *
 * @return array<int, Error>
 *
 * @api
 */
static function validate(
    GraphQL\Type\Schema $schema,
    GraphQL\Language\AST\DocumentNode $ast,
    ?array $rules = null,
    ?GraphQL\Utils\TypeInfo $typeInfo = null
): array
```

```php
/**
 * Returns all global validation rules.
 *
 * @throws \InvalidArgumentException
 *
 * @return array<string, ValidationRule>
 *
 * @api
 */
static function allRules(): array
```

```php
/**
 * Returns global validation rule by name.
 *
 * Standard rules are named by class name, so example usage for such rules:
 *
 * @example DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
 *
 * @api
 *
 * @throws \InvalidArgumentException
 */
static function getRule(string $name): ?GraphQL\Validator\Rules\ValidationRule
```

```php
/**
 * Add rule to list of global validation rules.
 *
 * @api
 */
static function addRule(GraphQL\Validator\Rules\ValidationRule $rule): void
```

```php
/**
 * Remove rule from list of global validation rules.
 *
 * @api
 */
static function removeRule(GraphQL\Validator\Rules\ValidationRule $rule): void
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

@see \GraphQL\Tests\Error\ErrorTest

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
function getPath(): ?array
```

## GraphQL\Error\Warning

Encapsulates warnings produced by the library.

Warnings can be suppressed (individually or all) if required.
Also, it is possible to override warning handler (which is **trigger_error()** by default).

@phpstan-type WarningHandler callable(string $errorMessage, int $warningId, ?int $messageLevel): void

### GraphQL\Error\Warning Constants

```php
const NONE = 0;
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
 * @phpstan-param WarningHandler|null $warningHandler
 *
 * @api
 */
static function setWarningHandler(?callable $warningHandler = null): void
```

```php
/**
 * Suppress warning by id (has no effect when custom warning handler is set).
 *
 * @param bool|int $suppress
 *
 * @example Warning::suppress(Warning::WARNING_NOT_A_TYPE) suppress a specific warning
 * @example Warning::suppress(true) suppresses all warnings
 * @example Warning::suppress(false) enables all warnings
 *
 * @api
 */
static function suppress($suppress = true): void
```

```php
/**
 * Re-enable previously suppressed warning by id (has no effect when custom warning handler is set).
 *
 * @param bool|int $enable
 *
 * @example Warning::suppress(Warning::WARNING_NOT_A_TYPE) re-enables a specific warning
 * @example Warning::suppress(true) re-enables all warnings
 * @example Warning::suppress(false) suppresses all warnings
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

@see ExecutionResult

@phpstan-import-type SerializableError from ExecutionResult
@phpstan-import-type ErrorFormatter from ExecutionResult

@see \GraphQL\Tests\Error\FormattedErrorTest

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
 * @return SerializableError
 *
 * @api
 */
static function createFromException(
    Throwable $exception,
    int $debugFlag = 'GraphQL\\Error\\DebugFlag::NONE',
    ?string $internalErrorMessage = null
): array
```

```php
/**
 * Returns error trace as serializable array.
 *
 * @return array<int, array{
 *     file?: string,
 *     line?: int,
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
Usage Example:.

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

@see \GraphQL\Tests\Server\StandardServerTest

### GraphQL\Server\StandardServer Methods

```php
/**
 * @param ServerConfig|array<string, mixed> $config
 *
 * @api
 *
 * @throws InvariantViolation
 */
function __construct($config)
```

```php
/**
 * Parses HTTP request, executes and emits response (using standard PHP `header` function and `echo`).
 *
 * When $parsedBody is not set, it uses PHP globals to parse a request.
 * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
 * and then pass it to the server.
 *
 * See `executeRequest()` if you prefer to emit the response yourself
 * (e.g. using the Response object of some framework).
 *
 * @param OperationParams|array<OperationParams> $parsedBody
 *
 * @api
 *
 * @throws \Exception
 * @throws InvariantViolation
 * @throws RequestError
 */
function handleRequest($parsedBody = null): void
```

```php
/**
 * Executes a GraphQL operation and returns an execution result
 * (or promise when promise adapter is different from SyncPromiseAdapter).
 *
 * When $parsedBody is not set, it uses PHP globals to parse a request.
 * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
 * and then pass it to the server.
 *
 * PSR-7 compatible method executePsrRequest() does exactly this.
 *
 * @param OperationParams|array<OperationParams> $parsedBody
 *
 * @throws \Exception
 * @throws InvariantViolation
 * @throws RequestError
 *
 * @return ExecutionResult|array<int, ExecutionResult>|Promise
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
 * @throws \Exception
 * @throws \InvalidArgumentException
 * @throws \JsonException
 * @throws \RuntimeException
 * @throws InvariantViolation
 * @throws RequestError
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
 * (or promise when promise adapter is different from SyncPromiseAdapter).
 *
 * @throws \Exception
 * @throws \JsonException
 * @throws InvariantViolation
 * @throws RequestError
 *
 * @return ExecutionResult|array<int, ExecutionResult>|Promise
 *
 * @api
 */
function executePsrRequest(Psr\Http\Message\RequestInterface $request)
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

@see ExecutionResult

@phpstan-type PersistedQueryLoader callable(string $queryId, OperationParams $operation): (string|DocumentNode)
@phpstan-type RootValueResolver callable(OperationParams $operation, DocumentNode $doc, string $operationType): mixed
@phpstan-type ValidationRulesOption array<ValidationRule>|null|callable(OperationParams $operation, DocumentNode $doc, string $operationType): array<ValidationRule>

@phpstan-import-type ErrorsHandler from ExecutionResult
@phpstan-import-type ErrorFormatter from ExecutionResult

@see \GraphQL\Tests\Server\ServerConfigTest

### GraphQL\Server\ServerConfig Methods

```php
/**
 * Converts an array of options to instance of ServerConfig
 * (or just returns empty config when array is not passed).
 *
 * @param array<string, mixed> $config
 *
 * @api
 *
 * @throws InvariantViolation
 */
static function create(array $config = []): self
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
 * @phpstan-param mixed|RootValueResolver $rootValue
 *
 * @api
 */
function setRootValue($rootValue): self
```

```php
/**
 * @phpstan-param ErrorFormatter $errorFormatter
 *
 * @api
 */
function setErrorFormatter(callable $errorFormatter): self
```

```php
/**
 * @phpstan-param ErrorsHandler $handler
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
 * @phpstan-param ValidationRulesOption $validationRules
 *
 * @api
 */
function setValidationRules($validationRules): self
```

```php
/**
 * @phpstan-param PersistedQueryLoader|null $persistedQueryLoader
 *
 * @api
 */
function setPersistedQueryLoader(?callable $persistedQueryLoader): self
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

## GraphQL\Server\Helper

Contains functionality that could be re-used by various server implementations.

@see \GraphQL\Tests\Server\HelperTest

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
 * @throws RequestError
 *
 * @return OperationParams|array<int, OperationParams>
 *
 * @api
 */
function parseHttpRequest(?callable $readRawBodyFn = null)
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
 * @throws RequestError
 *
 * @return OperationParams|array<int, OperationParams>
 *
 * @api
 */
function parseRequestParams(string $method, array $bodyParams, array $queryParams)
```

```php
/**
 * Checks validity of OperationParams extracted from HTTP request and returns an array of errors
 * if params are invalid (or empty array when params are valid).
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
 * (or promise when promise adapter is different from SyncPromiseAdapter).
 *
 * @throws \Exception
 * @throws InvariantViolation
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
 * (thus, effectively batching deferreds|promises of all queries at once).
 *
 * @param array<OperationParams> $operations
 *
 * @throws \Exception
 * @throws InvariantViolation
 *
 * @return array<int, ExecutionResult>|Promise
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
 *
 * @throws \JsonException
 */
function sendResponse($result): void
```

```php
/**
 * Converts PSR-7 request to OperationParams or an array thereof.
 *
 * @throws RequestError
 *
 * @return OperationParams|array<OperationParams>
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
 * @throws \InvalidArgumentException
 * @throws \JsonException
 * @throws \RuntimeException
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

Structure representing parsed HTTP parameters for GraphQL operation.

The properties in this class are not strictly typed, as this class
is only meant to serve as an intermediary representation which is
not yet validated.

### GraphQL\Server\OperationParams Props

```php
/**
 * Id of the query (when using persisted queries).
 *
 * Valid aliases (case-insensitive):
 * - id
 * - queryId
 * - documentId
 *
 * @api
 *
 * @var mixed should be string|null
 */
public $queryId;

/**
 * A document containing GraphQL operations and fragments to execute.
 *
 * @api
 *
 * @var mixed should be string|null
 */
public $query;

/**
 * The name of the operation in the document to execute.
 *
 * @api
 *
 * @var mixed should be string|null
 */
public $operation;

/**
 * Values for any variables defined by the operation.
 *
 * @api
 *
 * @var mixed should be array<string, mixed>
 */
public $variables;

/**
 * Reserved for implementors to extend the protocol however they see fit.
 *
 * @api
 *
 * @var mixed should be array<string, mixed>
 */
public $extensions;

/**
 * Executed in read-only context (e.g. via HTTP GET request)?
 *
 * @api
 */
public $readOnly;

/**
 * The raw params used to construct this instance.
 *
 * @api
 *
 * @var array<string, mixed>
 */
public $originalInput;
```

### GraphQL\Server\OperationParams Methods

```php
/**
 * Creates an instance from given array.
 *
 * @param array<string, mixed> $params
 *
 * @api
 */
static function create(array $params, bool $readonly = false): GraphQL\Server\OperationParams
```

## GraphQL\Utils\BuildSchema

Build instance of @see \GraphQL\Type\Schema out of schema language definition (string or parsed AST).

See [schema definition language docs](schema-definition-language.md) for details.

@phpstan-import-type TypeConfigDecorator from ASTDefinitionBuilder

@phpstan-type BuildSchemaOptions array{
assumeValid?: bool,
assumeValidSDL?: bool
}

- assumeValid:
  When building a schema from a GraphQL service's introspection result, it
  might be safe to assume the schema is valid. Set to true to assume the
  produced schema is valid.

  Default: false

- assumeValidSDL:
  Set to true to assume the SDL is valid.

  Default: false

@see \GraphQL\Tests\Utils\BuildSchemaTest

### GraphQL\Utils\BuildSchema Methods

```php
/**
 * A helper function to build a GraphQLSchema directly from a source
 * document.
 *
 * @param DocumentNode|Source|string $source
 *
 * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
 *
 * @param array<string, bool> $options
 *
 * @phpstan-param BuildSchemaOptions $options
 *
 * @api
 *
 * @throws \Exception
 * @throws \ReflectionException
 * @throws Error
 * @throws InvariantViolation
 * @throws SyntaxError
 */
static function build($source, ?callable $typeConfigDecorator = null, array $options = []): GraphQL\Type\Schema
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
 * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
 *
 * @param array<string, bool> $options
 *
 * @phpstan-param BuildSchemaOptions $options
 *
 * @api
 *
 * @throws \Exception
 * @throws \ReflectionException
 * @throws Error
 * @throws InvariantViolation
 */
static function buildAST(
    GraphQL\Language\AST\DocumentNode $ast,
    ?callable $typeConfigDecorator = null,
    array $options = []
): GraphQL\Type\Schema
```

## GraphQL\Utils\AST

Various utilities dealing with AST.

### GraphQL\Utils\AST Methods

````php
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
 * @param array<string, mixed> $node
 *
 * @api
 *
 * @throws \JsonException
 * @throws InvariantViolation
 */
static function fromArray(array $node): GraphQL\Language\AST\Node
````

```php
/**
 * Convert AST node to serializable array.
 *
 * @return array<string, mixed>
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
 * @param mixed $value
 * @param InputType&Type $type
 *
 * @throws \JsonException
 * @throws InvariantViolation
 * @throws SerializationError
 *
 * @return (ValueNode&Node)|null
 *
 * @api
 */
static function astFromValue($value, GraphQL\Type\Definition\InputType $type): ?GraphQL\Language\AST\ValueNode
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
 * @param (ValueNode&Node)|null $valueNode
 * @param array<string, mixed>|null $variables
 *
 * @throws \Exception
 *
 * @return mixed
 *
 * @api
 */
static function valueFromAST(
    ?GraphQL\Language\AST\ValueNode $valueNode,
    GraphQL\Type\Definition\Type $type,
    ?array $variables = null
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
 * @param array<string, mixed>|null $variables
 *
 * @throws \Exception
 *
 * @return mixed
 *
 * @api
 */
static function valueFromASTUntyped(GraphQL\Language\AST\Node $valueNode, ?array $variables = null)
```

```php
/**
 * Returns type definition for given AST Type node.
 *
 * @param callable(string): ?Type $typeLoader
 * @param NamedTypeNode|ListTypeNode|NonNullTypeNode $inputTypeNode
 *
 * @throws \Exception
 *
 * @api
 */
static function typeFromAST(callable $typeLoader, GraphQL\Language\AST\Node $inputTypeNode): ?GraphQL\Type\Definition\Type
```

```php
/**
 * Returns the operation within a document by name.
 *
 * If a name is not provided, an operation is only returned if the document has exactly one.
 *
 * @api
 */
static function getOperationAST(GraphQL\Language\AST\DocumentNode $document, ?string $operationName = null): ?GraphQL\Language\AST\OperationDefinitionNode
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

All sorting options sort alphabetically. If not given or `false`, the original schema definition order will be used.

@phpstan-type Options array{
sortArguments?: bool,
sortEnumValues?: bool,
sortFields?: bool,
sortInputFields?: bool,
sortTypes?: bool,
}

@see \GraphQL\Tests\Utils\SchemaPrinterTest

### GraphQL\Utils\SchemaPrinter Methods

```php
/**
 * @param array<string, bool> $options
 *
 * @phpstan-param Options $options
 *
 * @api
 *
 * @throws \JsonException
 * @throws Error
 * @throws InvariantViolation
 * @throws SerializationError
 */
static function doPrint(GraphQL\Type\Schema $schema, array $options = []): string
```

```php
/**
 * @param array<string, bool> $options
 *
 * @phpstan-param Options $options
 *
 * @api
 *
 * @throws \JsonException
 * @throws Error
 * @throws InvariantViolation
 * @throws SerializationError
 */
static function printIntrospectionSchema(GraphQL\Type\Schema $schema, array $options = []): string
```
