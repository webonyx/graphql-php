# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

You can find and compare releases at the [GitHub release page](https://github.com/webonyx/graphql-php/releases).

## Unreleased

## v15.19.1

### Fixed

- Handle fields that return lists in `ResolveInfo::getFieldSelectionWithAliases` https://github.com/webonyx/graphql-php/pull/1664

## v15.19.0

### Added

- Add method `getFieldSelectionWithAliases` to class `ResolveInfo` https://github.com/webonyx/graphql-php/pull/1648

## v15.18.1

### Fixed

- Reset static caches of instances using standard types when overriding them https://github.com/webonyx/graphql-php/pull/1632

## v15.18.0

### Added

- Propagate error messages of client-safe exceptions during `parseValue` https://github.com/webonyx/graphql-php/pull/1624

## v15.17.0

### Added

- Allow customizing PhpEnumType https://github.com/webonyx/graphql-php/pull/1623

## v15.16.1

### Fixed

- Make field args mapper optional https://github.com/webonyx/graphql-php/pull/1622

## v15.16.0

### Added

- Create field args mapper and cache args resolution https://github.com/webonyx/graphql-php/pull/1587

## v15.15.0

### Added

- Add field config decorator when building schema from SDL https://github.com/webonyx/graphql-php/pull/1590

## v15.14.3

### Fixed

- Serialize `BackedEnum` instances by name, not value https://github.com/webonyx/graphql-php/pull/1618

## v15.14.2

### Changed

- Annotate return value of `Helper::validateOperationParams` as list https://github.com/webonyx/graphql-php/pull/1615

## v15.14.1

### Changed

- Narrow type of execution errors from `array` to `list` https://github.com/webonyx/graphql-php/pull/1597

## v15.14.0

### Added

- Allow native PHP enums as return values for SDL-based enums https://github.com/webonyx/graphql-php/pull/1608

## v15.13.0

### Added

- Serialize valid `\BackedEnum` values in `GraphQL\Type\Definition\PhpEnumType` https://github.com/webonyx/graphql-php/pull/1604

## v15.12.5

### Fixed

- Avoid infinite recursion in `QueryDepth` validator https://github.com/webonyx/graphql-php/pull/1581

## v15.12.4

### Fixed

- Ensure `unaliasedPath` does not grow for each list item https://github.com/webonyx/graphql-php/pull/1579

## v15.12.3

### Fixed

- Avoid crash by triggering side effect via `count` on `PHPUnit\Framework\Test` https://github.com/webonyx/graphql-php/pull/1577

## v15.12.2

### Changed

- Optimize and deduplicate ReferenceExecutor https://github.com/webonyx/graphql-php/pull/1572

## v15.12.1

### Fixed

- Remove redundant code in `ReferenceExecutor` https://github.com/webonyx/graphql-php/pull/1570

## v15.12.0

### Added

- Add property `unaliasedPath` to `ResolveInfo` https://github.com/webonyx/graphql-php/pull/1548

## v15.11.2

### Fixed

- Fix `ScopedContext` for mutations https://github.com/webonyx/graphql-php/pull/1549

## v15.11.1

### Fixed

- Count new lines when parsing multi-line comments https://github.com/webonyx/graphql-php/pull/1538

## v15.11.0

### Changed

- Allow scalars from `BuildClientSchema` to have any type https://github.com/webonyx/graphql-php/pull/1536

## v15.10.0

### Changed

- The standard server now produces distinct exception classes instead of generic `RequestError` https://github.com/webonyx/graphql-php/pull/1533

## v15.9.1

### Fixed

- Allow enum instances in `PhpEnumType::parseValue()` https://github.com/webonyx/graphql-php/pull/1519

## v15.9.0

### Added

- Support `react/promise` v3 https://github.com/webonyx/graphql-php/pull/1517

## v15.8.1

### Fixed

- Correctly print deprecated argument with default value https://github.com/webonyx/graphql-php/pull/1491

## v15.8.0

### Added

- Early return when complexity is disabled https://github.com/webonyx/graphql-php/pull/1475
- Allow access to calculated query complexity https://github.com/webonyx/graphql-php/pull/1475

### Fixed

- Fix PHPStan definition for `SchemaConfig` option `types` https://github.com/webonyx/graphql-php/pull/1479

## v15.7.0

### Added

- Control field visibility through option `visible` https://github.com/webonyx/graphql-php/pull/1434

## v15.6.3

### Fixed

- Fix printing of single line descriptions with Unicode https://github.com/webonyx/graphql-php/pull/1436

## v15.6.2

### Fixed

- Avoid instantiations when checking if types are built-in https://github.com/webonyx/graphql-php/pull/1423

## v15.6.1

### Fixed

- Allow lazy root type callables to return `null` https://github.com/webonyx/graphql-php/pull/1422

## v15.6.0

### Added

- Allow lazy root types `query`, `mutation`, `subscription` https://github.com/webonyx/graphql-php/pull/1418

## v15.5.3

### Fixed

- Also set `queryId` from Apollo-source `sha256Hash` if `query` is provided https://github.com/webonyx/graphql-php/pull/1412

## v15.5.2

### Fixed

- Do not set raw vars on query complexity rule if no validation rules are used https://github.com/webonyx/graphql-php/pull/1413

## v15.5.1

### Fixed

- Remove useless and broken `Parser::document()`, use `parse()`

## v15.5.0

### Added

- Add `ScopedContext` interface to control cloning of the context value

## v15.4.0

### Added

- Allow deprecating input fields and arguments https://github.com/webonyx/graphql-php/pull/1382

## v15.3.2

### Fixed

- Call static methods in `SchemaPrinter` with `static::` to enable overrides

## v15.3.1

### Fixed

- Allow sending both `query` and `queryId`, let `persistedQueryLoader` handle it https://github.com/webonyx/graphql-php/pull/1372

## v15.3.0

### Added

- Provide a custom name for the Enum generated by `PhpEnumType` https://github.com/webonyx/graphql-php/pull/1368

## v15.2.5

### Fixed

- Merge selected fields in `ResolveInfo::getFieldSelection()` https://github.com/webonyx/graphql-php/pull/1365

## v15.2.4

### Fixed

- Ensure `OperationDefinitionNode.variableDefinitions` is initialized

## v15.2.3

### Fixed

- Annotate `@throws` accurately

## v15.2.2

### Fixed

- Improve handling of JSON encoding errors

## v15.2.1

### Fixed

- Initialize `directives` and `arguments` in `Node` with empty `NodeList`

## v15.2.0

### Changed

- Improve validation messages for type definitions

## v15.1.0

### Added

- Add additional sorting options to `SchemaPrinter`

## v15.0.3

### Fixed

- Fix `QueryPlan` for `union` types

### Changed

- Improve `QueryPlan` performance

## v15.0.2

### Fixed

- Fix `QueryPlan` when querying `__typename` on a `union` type

## v15.0.1

### Fixed

- Fix printing of single line descriptions with backslashes

### Changed

- Print long argument lists on multiple lines
- Print space between object value brackets

## v15.0.0

### Changed

- PHP version required: 7.4+
- Propagate error message and stack trace for why leaf value serialization failed
- Do not throw client safe `Error` when failing to serialize an Enum type
- Use native PHP types for properties of `Type` and its subclasses
- Throw `SerializationError` over client safe `Error` when failing to serialize leaf types
- Move debug entries in errors under `extensions` key
- Use native PHP types wherever possible
- Always throw `RequestError` with useful message when clients provide an invalid JSON body
- Move class `BlockString` from namespace `GraphQL\Utils` to `GraphQL\Language`
- Return string-keyed arrays from `GraphQL::getStandardDirectives()`, `GraphQL::getStandardTypes()` and `GraphQL::getStandardValidationRules()`
- Move complexity related code from `FieldDefinition` to `QueryComplexity`
- Exclude unused standard types from the schema
- Require lazy type loader to return `Type` directly without an intermediary callable
- Allow lazy type loader to return `null`
- Rename `ServerConfig` option `persistentQueryLoader` to `persistedQueryLoader`
- Call previously unused methods `EnumType::parseValue()` and `EnumType::parseLiteral()`
- Strongly type `PromiseAdapter::createRejected()` to require `\Throwable`
- Move members specific to `NamedType` out of `Type`: `$name`, `$description`, `$config`, `isBuiltInType()`, `assertValid()`
- Always convert recursively when calling `Node::toArray()`
- Make `Directive::$config['args']` use the same definition style as `FieldDefinition::$config['args']`
- Rename `FieldArgument` to `Argument`
- Make errors when parsing scalar literals more precise
- Change expected `QueryPlan` options from `['group-implementor-fields']` to `['groupImplementorFields' => true]` in `ResolveInfo::lookAhead()`
- Always convert promises through `PromiseAdapter::convertThenable()` before calling `->then()` on them
- Use `JSON_THROW_ON_ERROR` in `json_encode()`
- Validate some internal invariants through `assert()`
- `PromiseAdapter::all()` accepts `iterable`
- Throw if `Introspection::fromSchema()` returns no data
- Reorganize abstract class `ASTValidationContext` to interface `ValidationContext`
- Reorganize AST interfaces related to schema and type extensions
- Align `Utils::suggestionList()` with the reference implementation (#1075)
- Order schema topologically and according to the user-defined order, affects introspection and printing
- `GraphQL\Utils\AST::typeFromAST()` now needs a type loader callable instead of the Schema
- Do not change HTTP status code in `StandardServer`
- Use `"` instead of `"""` for single line descriptions
- Make `Helper::emitResponse()` private, use `Helper::sendResponse()`
- Emit unescaped UTF-8 from `StandardServer`
- Sync input value coercion with `graphql-js` reference implementation
- Store rules exclusively by class name in `DocumentValidator`
- Reorder standard types as described in the GraphQL specification
- Improve runtime performance by moving checks for duplicate/mismatching type instances to `assert()` or schema validation
- Replace `HasSelectionSet::$selectionSet` with `HasSelectionSet::getSelectionSet()`
- Replace `TypeDefinitionNode::$name` with `TypeDefinitionNode::getName()`
- Replace `TypeExtensionNode::$name` with `TypeExtensionNode::getName()`

### Added

- Improve extendability of validator rules
- Add tests for errors that occur when undeclared fields are passed in input
- Warn about orphaned object types
- Expose structured enumeration of directive locations
- Add `AST::concatAST()` utility
- Allow lazy input object fields
- Add validation rule `UniqueEnumValueNames`
- Add SDL validation rule `UniqueOperationTypes` (#995)
- Add ability to remove custom validation rules after adding them via `DocumentValidator::removeRule()`
- Allow lazy enum values
- Make `Node` implement `JsonSerializable`
- Add SDL validation rule `UniqueTypeNames` (#998)
- Add support for SDL validation to `KnownTypeNames` rule (#999)
- Add SDL validation rule `UniqueArgumentDefinitionNames` (#1136)
- Add `parseValue` config option to InputObjectType to parse input value to custom value object
- Add option `sortTypes` to have `SchemaPrinter` order types alphabetically
- Allow constructing `EnumType` from PHP enum
- Add `TypeInfo::getParentTypeStack()` and `TypeInfo::getFieldDefStack()`
- Include path to faulty input in coercion errors
- Add ability to resolve abstract type of object via `__typename`

### Optimized

- Use recursive algorithm for printer and improve its performance
- Use `foreach` over slower functions `array_map()` and `Utils::map()`

### Fixed

- Avoid `QueryPlan` crash when multiple `$fieldNodes` are present
- Allow instantiating multiple `QueryPlan` with different options
- Clarify error when attempting to coerce anything but `array` or `stdClass` to an input object
- Allow directives on variable definitions
- Handle `null` parent of list in `ValuesOfCorrectType::getVisitor`
- Allow sending both `query` and `queryId`, ignore `queryId` in that case
- Preserve extended methods from class-based types in `SchemaExtender::extend()`
- Fix printing of empty types (#940)
- Clone `NodeList` in `Node::cloneDeep()`
- Calling `Schema::getType()` on a schema built from SDL returns `null` for unknown types (#1068)
- Avoid crash on typeless inline fragment when using `QueryComplexity` rule
- Avoid calling `FormattedError::addDebugEntries()` twice when using default error formatting
- Avoid calling defined functions named like lazily loaded types
- Show actual error in debug entries
- Deal with `iterable` in implementations of `PromiseAdapter::all()`

### Removed

- Remove `OperationParams` method `getOriginalInput()` in favor of public property `$originalInput`
- Remove `OperationParams` method `isReadOnly()` in favor of public property `$readOnly`
- Remove `Utils::withErrorHandling()`
- Remove `TypeComparators::doTypesOverlap()`
- Remove `DocumentValidator::isError()`
- Remove `DocumentValidator::append()`
- Remove `Utils::getVariableType()` in favor of `Utils::printSafe()`
- Remove warning for passing `isDeprecated` in field definition config
- Remove `WrappingType::getWrappedType()` argument `$recurse` in favor of `WrappingType::getInnermostType()`
- Remove `Type::assertType()`
- Remove `ListOfType::$ofType`, `ListOfType::getOfType()` and `NonNull::getOfType()`
- Remove option `commentDescriptions` from `BuildSchema::buildAST()`, `BuildSchema::build()` and `Printer::doPrint()`
- Remove parameter `$options` from `ASTDefinitionBuilder`
- Remove `FieldDefinition::create()` in favor of `new FieldDefinition()`
- Remove `GraphQL\Exception\InvalidArgument`
- Remove `Utils::find()`, `Utils::every()` and `Utils::invariant()`
- Remove argument `bool $exitWhenDone` from `StandardServer::send500Error()` and `StandardServer::handleRequest()`
- Remove `Schema::getAstNode()` in favor of `Schema::$astNode`
- Remove ability to override standard types through `Schema` option `types`, use `Type::overrideStandardTypes()`
- Remove `GraphQL\Utils\TypeInfo::typeFromAST()`, use `GraphQL\Utils\AST::typeFromAST()`
- Remove `StandardServer::send500Error()`, handle non-GraphQL errors yourself
- Remove `StandardServer::getHelper()`, use `new Helper`
- Remove error extension field `category`, use custom error formatting if you still need it
- Remove deprecated `Type::getInternalTypes()`
- Remove deprecated `GraphQL::execute()`
- Remove deprecated `GraphQL::executeAndReturnResult()`
- Remove deprecated experimental CoroutineExecutor
- Remove deprecated `FormattedError::create()` and `FormattedError::createFromPHPError()`
- Remove deprecated `GraphQL::setPromiseAdapter()`
- Remove deprecated `AST::getOperation()`
- Remove deprecated constants from `BreakingChangesFinder`
- Remove deprecated `DocumentValidator::isValidLiteralValue()`
- Remove deprecated `Error::formatError()` and `Error::toSerializableArray()`
- Remove deprecated `GraphQL::getInternalDirectives()`
- Remove deprecated `Schema::isPossibleType()`
- Remove deprecated methods from `TypeInfo`
- Remove deprecated `Values::valueFromAST()` and `Values::isValidPHPValue()`
- Remove deprecated public property access to `InputObjectField::$type`
- Remove deprecated public property access to `FieldDefinition::$type`
- Remove alias `GraphQL\Validator\Rules\AbstractQuerySecurity`, use `GraphQL\Validator\Rules\QuerySecurityRule`
- Remove alias `GraphQL\Validator\Rules\AbstractValidationRule`, use `GraphQL\Validator\Rules\ValidationRule`
- Remove alias `GraphQL\Utils\FindBreakingChanges`, use `GraphQL\Utils\BreakingChangesFinder`

## v14.11.10

### Fixed

- Fix PHP 8.2 deprecated string interpolation syntax
- Fix PHP 8.2 deprecated dynamic properties

## v14.11.9

### Fixed

- Accept AST where field arguments are not given

## v14.11.8

### Fixed

- Correct the broken 14.11.7 release - see https://github.com/webonyx/graphql-php/issues/1221

## v14.11.7

### Fixed

- Fix PHP 8.2 deprecation of "static" in callables

## v14.11.6

### Fixed

- Fix validation of modified sparse ASTs

## v14.11.5

### Fixed

- Fix `extend()` to preserve `repeatable` (#931)

## v14.11.4

### Fixed

- Fix repeatable directive validation for AST

## v14.11.3

### Fixed

- Fix compatibility of more methods with native return type in PHP 8.1

## v14.11.2

### Fixed

- Support non-JSON `ServerRequestInterface`

## v14.11.1

### Fixed

- Fix compatibility of methods with native return type in PHP 8.1

## v14.11.0

### Added

- Allow field definitions to be defined as any `iterable`, not just `array`

## v14.10.0

### Added

- Make `IntType` constants `MAX_INT` and `MIN_INT` public

## v14.9.0

### Added

- Add support for type config decorator in `SchemaExtender`

## v14.8.0

### Added

- Implement `GraphQL\Utils\AST::getOperationAST()`

## v14.7.0

### Added

- Allow providing field definitions as a callable and resolve them lazily

## v14.6.4

### Fixed

- Avoid crashing in `QueryPlan` when `__typename` is used in the query

## v14.6.3

Refactoring:

- Improve performance of subtype checks

## v14.6.2

### Fixed

- Fix overly eager validation of repeatable directive usage

## v14.6.1

### Fixed

- Add fallback for `directive.isRepeatable` in `BuildClientSchema`

## v14.6.0

### Added

- Open ReferenceExecutor for extending

### Fixed

- Ensure properties annotated to hold NodeList are not null
- Validate that directive argument names do not use reserved or duplicate names

## v14.5.1

### Fixed

- Fix Input Object field shortcut definition with callable (#773)

## v14.5.0

### Added

- Implement support for interfaces implementing interfaces (#740), huge kudos to @Kingdutch

Deprecates:

- Constant `BreakingChangeFinder::BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT`.
  Use `BreakingChangeFinder::BREAKING_CHANGE_IMPLEMENTED_INTERFACE_REMOVED` instead.
  Constant value also changed from `INTERFACE_REMOVED_FROM_OBJECT` to `IMPLEMENTED_INTERFACE_REMOVED`.

- Constant `BreakingChangeFinder::DANGEROUS_CHANGE_INTERFACE_ADDED_TO_OBJECT`
  Use `DANGEROUS_CHANGE_IMPLEMENTED_INTERFACE_ADDED` instead.
  Constant value also changed from `INTERFACE_ADDED_TO_OBJECT` to `IMPLEMENTED_INTERFACE_ADDED`.

Refactoring:

- Reify AST node types and remove unneeded nullability (#751)

## v14.4.1

### Fixed

- Allow pushing nodes to `NodeList` via `[]=` (#767)
- Fix signature of `Error\FormattedError::prepareFormatter()` to address PHP8 deprecation (#742)
- Do not add errors key to result when errors discarded by custom error handler (#766)

## v14.4.0

### Fixed

- Fixed `SchemaPrinter` so that it uses late static bindings when extended
- Parse `DirectiveDefinitionNode->locations` as `NodeList<NamedNode>` (fixes AST::fromArray conversion) (#723)
- Parse `Parser::implementsInterfaces` as `NodeList<NamedTypeNode>` (fixes AST::fromArray conversion)
- Fix signature of `Parser::unionMemberTypes` to match actual `NodeList<NamedTypeNode>`

## v14.3.0

### Added

- Allow `typeLoader` to return a type thunk (#687)

### Fixed

- Read getParsedBody() instead of getBody() when Request is ServerRequest (#715)
- Fix default get/set behavior on InputObjectField and FieldDefinition (#716)

## v14.2.0

Deprecates:

- Public access to `FieldDefinition::$type` property (#702)

Fixes:

- Fix validation for input field definition directives (#714)

## v14.1.1

## v14.1.1

### Fixed

- Handle nullable `DirectiveNode#astNode` in `SchemaValidationContext` (#708)

## v14.1.0

### Added

- Add partial parse functions for const variants (#693)

### Fixed

- Differentiate between client-safe and non-client-safe errors in scalar validation (#706)
- Proper type hints for `IntValueNode` (#691)
- Fix "only booleans are allowed" errors (#659)
- Ensure NamedTypeNode::$name is always a NameNode (#695)

### Optimized

- Visitor: simplify getVisitFn (#694)
- Replace function calls with type casts (#692)

## v14.0.2

### Optimized

- Optimize lazy types (#684)

## v14.0.1

### Fixed

- Fix for: Argument defaults with integer/float values crashes introspection query (#679)
- Fix for "Invalid AST Node: false" error (#685)
- Fix double Error wrapping when parsing variables (#688)

### Optimized

- Do not use call_user_func or call_user_func_array (#676)
- Codestyle and static analysis improvements (#648, #690)

## v14.0.0

This release brings several breaking changes. Please refer to [UPGRADE](UPGRADE.md) document for details.

- **BREAKING/BUGFIX:** Strict coercion of scalar types (#278)
- **BREAKING/BUGFIX:** Spec-compliance: Fixed ambiguity with null variable values and default values (#274)
- **BREAKING:** Removed deprecated directive introspection fields (onOperation, onFragment, onField)
- **BREAKING:** `GraphQL\Deferred` now extends `GraphQL\Executor\Promise\Adapter\SyncPromise`
- **BREAKING:** renamed several types of dangerous/breaking changes (returned by `BreakingChangesFinder`)
- **BREAKING:** Renamed `GraphQL\Error\Debug` to `GraphQL\Error\DebugFlag`.
- **BREAKING:** Debug flags in `GraphQL\Executor\ExecutionResult`, `GraphQL\Error\FormattedError` and `GraphQL\Server\ServerConfig` do not accept `boolean` value anymore but `int` only.
- **BREAKING:** `$positions` in `GraphQL\Error\Error` constructor are not nullable anymore. Same can be expressed by passing an empty array.

### Added

- Support repeatable directives (#643)
- Support SDL Validation and other schema validation improvements (e.g. #492)
- Added promise adapter for [Amp](https://amphp.org/) (#551)
- Query plan utility improvements (#513, #632)
- Allow retrieving query complexity once query has been completed (#316)
- Allow input types to be passed in from variables using \stdClass instead of associative arrays (#535)
- Support UTF-16 surrogate pairs within string literals (#554, #556)

### Changed

- Compliant with the GraphQL specification [June 2018 Edition](https://spec.graphql.org/June2018/)
- Having an empty string in `deprecationReason` will now print the `@deprecated` directive (only a `null` `deprecationReason` won't print the `@deprecated` directive).

### Optimized

- Perf: support lazy type definitions (#557)
- Simplified Deferred implementation (now allows chaining like promises, #573)

### Deprecated

- Deprecated Experimental executor (#397)

### Fixed

- Some bugs
- Improve accuracy of type hints with [PHPStan](https://github.com/phpstan/phpstan)

Special thanks to @simPod, @spawnia and @shmax for their major contributions!

## v0.13.9

- Fix double Error wrapping when parsing variables (#689)

## v0.13.8

- Don't call global field resolver on introspection fields (#481)

## v0.13.7

- Added retrieving query complexity once query has been completed (#316)
- Allow input types to be passed in from variables using \stdClass instead of associative arrays (#535)

## v0.13.6

- QueryPlan can now be used on interfaces not only objects. (#495)
- Array in variables in place of object shouldn't cause fatal error (fixes #467)
- Scalar type ResolverInfo::getFieldSelection support (#529)

## v0.13.5

- Fix coroutine executor when using with promise (#486)

## v0.13.4

- Force int when setting max query depth (#477)

## v0.13.3

- Reverted minor possible breaking change (#476)

## v0.13.2

- Added QueryPlan support (#436)
- Fixed an issue with NodeList iteration over missing keys (#475)

## v0.13.1

- Better validation of field/directive arguments
- Support for apollo client/server persisted queries
- Minor tweaks and fixes

## v0.13.0

This release brings several breaking changes. Please refer to [UPGRADE](UPGRADE.md) document for details.

New features and notable changes:

- PHP version required: 7.1+
- Spec compliance: error `category` and extensions are displayed under `extensions` key when using default formatting (#389)
- New experimental executor with improved performance (#314).<br>
  It is a one-line switch: `GraphQL::useExperimentalExecutor()`.<br>
  <br>
  **Please try it and post your feedback at https://github.com/webonyx/graphql-php/issues/397**
  (as it may become the default one in future)
  <br>
  <br>
- Ported `extendSchema` from the reference implementation under `GraphQL\Utils\SchemaExtender` (#362)
- Added ability to override standard types via `GraphQL::overrideStandardTypes(array $types)` (#401)
- Added flag `Debug::RETHROW_UNSAFE_EXCEPTIONS` which would only rethrow app-specific exceptions (#337)
- Several classes were renamed (see [UPGRADE.md](UPGRADE.md))
- Schema Validation improvements

## v0.12.6

- Bugfix: Call to a member function getLocation() on null (#336)
- Fixed several errors discovered by static analysis (#329)

## v0.12.5

- Execution performance optimization for lists

## v0.12.4

- Allow stringeable objects to be serialized by StringType (#303)

## v0.12.3

- StandardServer: add support for the multipart/form-data content type (#300)

## v0.12.2

- SchemaPrinter: Use multi-line block for trailing quote (#294)

## v0.12.1

- Fixed bug in validation rule OverlappingFieldsCanBeMerged (#292)
- Added one more breaking change note in UPGRADE.md (#291)
- Spec compliance: remove `data` entry from response on top-level error (#281)

## v0.12.0

- RFC: Block String (multi-line strings via triple-quote """string""")
- GraphQL Schema SDL: Descriptions as strings (including multi-line)
- Changed minimum required PHP version to 5.6

Improvements:

- Allow extending GraphQL errors with additional properties
- Fixed parsing of default values in Schema SDL
- Handling several more cases in findBreakingChanges
- StandardServer: expect `operationName` (instead of `operation`) in input

## v0.11.5

- Allow objects with \_\_toString in IDType

## v0.11.4

- findBreakingChanges utility (see #199)

## v0.11.3

- StandardServer: Support non pre-parsed PSR-7 request body (see #202)

## v0.11.2

- Bugfix: provide descriptions to custom scalars (see #181)

## v0.11.1

- Ability to override internal types via `types` option of the schema (see #174).

## v0.11.0

This release brings little changes but there are two reasons why it is released as major version:

1. To follow reference implementation versions (it matches 0.11.x series of graphql-js)
2. It may break existing applications because scalar input coercion rules are stricter now:<br>
   In previous versions sloppy client input could leak through with unexpected results.
   For example string `"false"` accidentally sent in variables was converted to boolean `true`
   and passed to field arguments. In the new version, such input will produce an error
   (which is a spec-compliant behavior).

Improvements:

- Stricter input coercion (see #171)
- Types built with `BuildSchema` now have reference to AST node with corresponding AST definition (in $astNode property)
- Account for query offset for error locations (e.g. when query is stored in `.graphql` file)

## v0.10.2

- StandardServer improvement: do not raise an error when variables are passed as empty string (see #156)

## v0.10.1

- Fixed infinite loop in the server (see #153)

## v0.10.0

This release brings several breaking changes. Please refer to [UPGRADE](UPGRADE.md) document for details.

New features and notable changes:

- Changed minimum PHP version from 5.4 to 5.5
- Lazy loading of types without separate build step (see #69, see [docs](https://webonyx.github.io/graphql-php/type-system/schema/#lazy-loading-of-types))
- PSR-7 compliant Standard Server (see [docs](https://webonyx.github.io/graphql-php/executing-queries/#using-server))
- New default error formatting, which does not expose sensitive data (see [docs](https://webonyx.github.io/graphql-php/error-handling/))
- Ability to define custom error handler to filter/log/re-throw exceptions after execution (see [docs](https://webonyx.github.io/graphql-php/error-handling/#custom-error-handling-and-formatting))
- Allow defining schema configuration using objects with fluent setters vs array (see [docs](https://webonyx.github.io/graphql-php/type-system/schema/#using-config-class))
- Allow serializing AST to array and re-creating AST from array lazily (see [docs](https://webonyx.github.io/graphql-php/reference/#graphqlutilsast))
- [Apollo-style](https://dev-blog.apollodata.com/query-batching-in-apollo-63acfd859862) query batching support via server (see [docs](https://webonyx.github.io/graphql-php/executing-queries/#query-batching))
- Schema validation, including validation of interface implementations (see [docs](https://webonyx.github.io/graphql-php/type-system/schema/#schema-validation))
- Ability to pass custom config formatter when defining schema using [GraphQL type language](http://graphql.org/learn/schema/#type-language) (see [docs](https://webonyx.github.io/graphql-php/type-system/type-language/))

Improvements:

- Significantly improved parser performance (see #137 and #128)
- Support for PHP7 exceptions everywhere (see #127)
- Improved [documentation](https://webonyx.github.io/graphql-php/) and docblock comments

Deprecations and breaking changes - see [UPGRADE](UPGRADE.md) document.

## v0.9.14

- Minor change to assist DataLoader project in fixing #150

## v0.9.13

- Fixed PHP notice and invalid conversion when non-scalar value is passed as ID or String type (see #121)

## v0.9.12

- Fixed bug occurring when enum `value` is bool, null or float (see #141)

## v0.9.11

- Ability to disable introspection (see #131)

## v0.9.10

- Fixed issue with query complexity throwing on invalid queries (see #125)
- Fixed "Out of memory" error when `resolveType` returns unexpected result (see #119)

## v0.9.9

- Bugfix: throw UserError vs InvariantViolationError for errors caused by client (see #123)

## v0.9.8

- Bugfix: use directives when calculating query complexity (see #113)
- Bugfix: `AST\Node::__toString()` will convert node to array recursively to encode to json without errors

## v0.9.7

- Bugfix: `ResolveInfo::getFieldSelection()` now correctly merges fragment selections (see #98)

## v0.9.6

- Bugfix: `ResolveInfo::getFieldSelection()` now respects inline fragments

## v0.9.5

- Fixed SyncPromiseAdapter::all() to not change the order of arrays (see #92)

## v0.9.4

- Tools to help building schema out of Schema definition language as well as printing existing
  schema in Schema definition language (see #91)

## v0.9.3

- Fixed Utils::assign() bug related to detecting missing required keys (see #89)

## v0.9.2

- Schema Definition Language: element descriptions can be set through comments (see #88)

## v0.9.1

- Fixed: `GraphQL\Server` now properly sets promise adapter before executing query

## v0.9.0

- Deferred resolvers (see #66, see [docs](docs/data-fetching.md#solving-n1-problem))
- New Facade class with fluid interface: `GraphQL\Server` (see #82)
- Experimental: ability to load types in Schema lazily via custom `TypeResolutionStrategy` (see #69)

## v0.8.0

This release brings several minor breaking changes. Please refer to [UPGRADE](UPGRADE.md) document for details.

New features:

- Support for `null` value (as required by latest GraphQL spec)
- Shorthand definitions for field and argument types (see #47)
- `path` entry in errors produced by resolvers for better debugging
- `resolveType` for interface/union is now allowed to return string name of type
- Ability to omit name when extending type class (vs defining inline)

Improvements:

- Spec compliance improvements
- New docs and examples

## Older versions

Look at [GitHub Releases Page](https://github.com/webonyx/graphql-php/releases).
