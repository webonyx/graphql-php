# Changelog

#### Unreleased

#### 14.11.6

Fixed:
- Fix validation of modified sparse ASTs

#### 14.11.5

Fixed:
- Fix `extend()` to preserve `repeatable` (#931)

#### 14.11.4

Fixed:
- Fix repeatable directive validation for AST

#### 14.11.3

Fixed:
- Fix compatibility of more methods with native return type in PHP 8.1

#### 14.11.2

Fixed:
- Support non-JSON `ServerRequestInterface`

#### 14.11.1

Fixed:
- Fix compatibility of methods with native return type in PHP 8.1

#### 14.11.0

Feat:
- Allow field definitions to be defined as any `iterable`, not just `array`

#### 14.10.0

Feat:
- Make `IntType` constants `MAX_INT` and `MIN_INT` public

#### 14.9.0

Feat:
- Add support for type config decorator in `SchemaExtender`

#### 14.8.0

Feat:
- Implement `GraphQL\Utils\AST::getOperationAST()`

#### 14.7.0

Feat:
- Allow providing field definitions as a callable and resolve them lazily

#### 14.6.4

Fix:
- Avoid crashing in `QueryPlan` when `__typename` is used in the query

#### 14.6.3

Refactoring:
- Improve performance of subtype checks

#### 14.6.2

Fix:
- Fix overly eager validation of repeatable directive usage

#### 14.6.1

Fix:
- Add fallback for `directive.isRepeatable` in `BuildClientSchema`

#### 14.6.0

Feat:
- Open ReferenceExecutor for extending

#### 14.5.1

Fix:
- Fix Input Object field shortcut definition with callable (#773)

#### 14.5.0

Feat:
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

#### 14.4.1

Fix:
- Allow pushing nodes to `NodeList` via `[]=` (#767)
- Fix signature of `Error\FormattedError::prepareFormatter()` to address PHP8 deprecation (#742)
- Do not add errors key to result when errors discarded by custom error handler (#766)

#### 14.4.0

Fix:
- Fixed `SchemaPrinter` so that it uses late static bindings when extended
- Parse `DirectiveDefinitionNode->locations` as `NodeList<NamedNode>` (fixes AST::fromArray conversion) (#723)
- Parse `Parser::implementsInterfaces` as `NodeList<NamedTypeNode>` (fixes AST::fromArray conversion)
- Fix signature of `Parser::unionMemberTypes` to match actual `NodeList<NamedTypeNode>`

#### v14.3.0

Feat:
- Allow `typeLoader` to return a type thunk (#687)

Fix:
- Read getParsedBody() instead of getBody() when Request is ServerRequest (#715)
- Fix default get/set behavior on InputObjectField and FieldDefinition (#716)

#### v14.2.0

Deprecates:
- Public access to `FieldDefinition::$type` property (#702)

Fixes:
- Fix validation for input field definition directives (#714) 

#### v14.1.1

Fixes:
- Handle nullable `DirectiveNode#astNode` in `SchemaValidationContext` (#708)

#### v14.1.0

New:
- Add partial parse functions for const variants (#693)

Fixes:
- Differentiate between client-safe and non-client-safe errors in scalar validation (#706)
- Proper type hints for `IntValueNode` (#691)

Refactoring:
- Ensure NamedTypeNode::$name is always a NameNode (#695)
- Visitor: simplify getVisitFn (#694)
- Replace function calls with type casts (#692)
- Fix "only booleans are allowed" errors (#659)


#### v14.0.2

- Optimize lazy types (#684)

#### v14.0.1

Bug fixes:
- Fix for: Argument defaults with integer/float values crashes introspection query (#679)
- Fix for "Invalid AST Node: false" error (#685)
- Fix double Error wrapping when parsing variables (#688)

Refactoring:
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

Notable features and improvements:

- Compliant with the GraphQL specification [June 2018 Edition](https://spec.graphql.org/June2018/)
- Support repeatable directives (#643)
- Perf: support lazy type definitions (#557)
- Simplified Deferred implementation (now allows chaining like promises, #573)
- Support SDL Validation and other schema validation improvements (e.g. #492)
- Added promise adapter for [Amp](https://amphp.org/) (#551)
- Query plan utility improvements (#513, #632)

Other noteworthy changes:
- Allow retrieving query complexity once query has been completed (#316) 
- Allow input types to be passed in from variables using \stdClass instead of associative arrays (#535)
- Support UTF-16 surrogate pairs within string literals (#554, #556)
- Having an empty string in `deprecationReason` will now print the `@deprecated` directive (only a `null` `deprecationReason` won't print the `@deprecated` directive).
- Deprecated Experimental executor (#397)

Also some bugs fixed, heavily invested in [PHPStan](https://github.com/phpstan/phpstan) for static analysis.

Special thanks to @simPod, @spawnia and @shmax for their major contributions!

#### v0.13.9
- Fix double Error wrapping when parsing variables (#689)

#### v0.13.8
- Don't call global field resolver on introspection fields (#481)

#### v0.13.7
- Added retrieving query complexity once query has been completed (#316)
- Allow input types to be passed in from variables using \stdClass instead of associative arrays (#535) 

#### v0.13.6
- QueryPlan can now be used on interfaces not only objects. (#495)
- Array in variables in place of object shouldn't cause fatal error (fixes #467)
- Scalar type ResolverInfo::getFieldSelection support (#529)

#### v0.13.5
- Fix coroutine executor when using with promise (#486) 

#### v0.13.4
- Force int when setting max query depth (#477)

#### v0.13.3
- Reverted minor possible breaking change (#476)

#### v0.13.2
- Added QueryPlan support (#436)
- Fixed an issue with NodeList iteration over missing keys (#475)

#### v0.13.1
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

#### v0.12.6
- Bugfix: Call to a member function getLocation() on null (#336)
- Fixed several errors discovered by static analysis (#329)

#### v0.12.5
- Execution performance optimization for lists

#### v0.12.4
- Allow stringeable objects to be serialized by StringType (#303)

#### v0.12.3
- StandardServer: add support for the multipart/form-data content type (#300)

#### v0.12.2
- SchemaPrinter: Use multi-line block for trailing quote (#294)

#### v0.12.1
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


#### v0.11.5
- Allow objects with __toString in IDType

#### v0.11.4
- findBreakingChanges utility (see #199)

#### v0.11.3
- StandardServer: Support non pre-parsed PSR-7 request body (see #202)

#### v0.11.2
- Bugfix: provide descriptions to custom scalars (see #181)

#### v0.11.1
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

#### v0.10.2
- StandardServer improvement: do not raise an error when variables are passed as empty string (see #156)

#### v0.10.1
- Fixed infinite loop in the server (see #153)

## v0.10.0
This release brings several breaking changes. Please refer to [UPGRADE](UPGRADE.md) document for details.

New features and notable changes:
- Changed minimum PHP version from 5.4 to 5.5
- Lazy loading of types without separate build step (see #69, see [docs](http://webonyx.github.io/graphql-php/type-system/schema/#lazy-loading-of-types))
- PSR-7 compliant Standard Server (see [docs](http://webonyx.github.io/graphql-php/executing-queries/#using-server))
- New default error formatting, which does not expose sensitive data (see [docs](http://webonyx.github.io/graphql-php/error-handling/))
- Ability to define custom error handler to filter/log/re-throw exceptions after execution (see [docs](http://webonyx.github.io/graphql-php/error-handling/#custom-error-handling-and-formatting))
- Allow defining schema configuration using objects with fluent setters vs array (see [docs](http://webonyx.github.io/graphql-php/type-system/schema/#using-config-class))
- Allow serializing AST to array and re-creating AST from array lazily (see [docs](http://webonyx.github.io/graphql-php/reference/#graphqlutilsast))
- [Apollo-style](https://dev-blog.apollodata.com/query-batching-in-apollo-63acfd859862) query batching support via server (see [docs](http://webonyx.github.io/graphql-php/executing-queries/#query-batching))
- Schema validation, including validation of interface implementations (see [docs](http://webonyx.github.io/graphql-php/type-system/schema/#schema-validation))
- Ability to pass custom config formatter when defining schema using [GraphQL type language](http://graphql.org/learn/schema/#type-language) (see [docs](http://webonyx.github.io/graphql-php/type-system/type-language/))

Improvements:
- Significantly improved parser performance (see #137 and #128)
- Support for PHP7 exceptions everywhere (see #127)
- Improved [documentation](http://webonyx.github.io/graphql-php/) and docblock comments

Deprecations and breaking changes - see [UPGRADE](UPGRADE.md) document.

#### v0.9.14
- Minor change to assist DataLoader project in fixing #150 

#### v0.9.13
- Fixed PHP notice and invalid conversion when non-scalar value is passed as ID or String type (see #121) 

#### v0.9.12
- Fixed bug occurring when enum `value` is bool, null or float (see #141)

#### v0.9.11
- Ability to disable introspection (see #131)

#### v0.9.10
- Fixed issue with query complexity throwing on invalid queries (see #125)
- Fixed "Out of memory" error when `resolveType` returns unexpected result (see #119)

#### v0.9.9
- Bugfix: throw UserError vs InvariantViolationError for errors caused by client (see #123)

#### v0.9.8
- Bugfix: use directives when calculating query complexity (see #113)
- Bugfix: `AST\Node::__toString()` will convert node to array recursively to encode to json without errors

#### v0.9.7
- Bugfix: `ResolveInfo::getFieldSelection()` now correctly merges fragment selections (see #98)

#### v0.9.6
- Bugfix: `ResolveInfo::getFieldSelection()` now respects inline fragments

#### v0.9.5
- Fixed SyncPromiseAdapter::all() to not change the order of arrays (see #92)

#### v0.9.4
- Tools to help building schema out of Schema definition language as well as printing existing 
schema in Schema definition language (see #91)

#### v0.9.3
- Fixed Utils::assign() bug related to detecting missing required keys (see #89)

#### v0.9.2
- Schema Definition Language: element descriptions can be set through comments (see #88)

#### v0.9.1
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
