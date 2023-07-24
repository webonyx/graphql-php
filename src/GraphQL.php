<?php declare(strict_types=1);

namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema as SchemaType;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * This is the primary facade for fulfilling GraphQL operations.
 * See [related documentation](executing-queries.md).
 *
 * @phpstan-import-type FieldResolver from Executor
 *
 * @see \GraphQL\Tests\GraphQLTest
 */
class GraphQL
{
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
    public static function executeQuery(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        array $variableValues = null,
        string $operationName = null,
        callable $fieldResolver = null,
        array $validationRules = null
    ): ExecutionResult {
        $promiseAdapter = new SyncPromiseAdapter();

        $promise = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver,
            $validationRules
        );

        return $promiseAdapter->wait($promise);
    }

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
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        SchemaType $schema,
        $source,
        $rootValue = null,
        $context = null,
        array $variableValues = null,
        string $operationName = null,
        callable $fieldResolver = null,
        array $validationRules = null
    ): Promise {
        try {
            $documentNode = $source instanceof DocumentNode
                ? $source
                : Parser::parse(new Source($source, 'GraphQL'));

            if ($validationRules === null) {
                $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);
                assert($queryComplexity instanceof QueryComplexity, 'should not register a different rule for QueryComplexity');

                $queryComplexity->setRawVariableValues($variableValues);
            } else {
                foreach ($validationRules as $rule) {
                    if ($rule instanceof QueryComplexity) {
                        $rule->setRawVariableValues($variableValues);
                    }
                }
            }

            $validationErrors = DocumentValidator::validate($schema, $documentNode, $validationRules);

            if ($validationErrors !== []) {
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, $validationErrors)
                );
            }

            return Executor::promiseToExecute(
                $promiseAdapter,
                $schema,
                $documentNode,
                $rootValue,
                $context,
                $variableValues,
                $operationName,
                $fieldResolver
            );
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }
    }

    /**
     * Returns directives defined in GraphQL spec.
     *
     * @throws InvariantViolation
     *
     * @return array<string, Directive>
     *
     * @api
     */
    public static function getStandardDirectives(): array
    {
        return Directive::getInternalDirectives();
    }

    /**
     * Returns types defined in GraphQL spec.
     *
     * @throws InvariantViolation
     *
     * @return array<string, ScalarType>
     *
     * @api
     */
    public static function getStandardTypes(): array
    {
        return Type::getStandardTypes();
    }

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
    public static function overrideStandardTypes(array $types): void
    {
        Type::overrideStandardTypes($types);
    }

    /**
     * Returns standard validation rules implementing GraphQL spec.
     *
     * @return array<class-string<ValidationRule>, ValidationRule>
     *
     * @api
     */
    public static function getStandardValidationRules(): array
    {
        return DocumentValidator::defaultRules();
    }

    /**
     * Set default resolver implementation.
     *
     * @phpstan-param FieldResolver $fn
     *
     * @api
     */
    public static function setDefaultFieldResolver(callable $fn): void
    {
        Executor::setDefaultFieldResolver($fn);
    }
}
