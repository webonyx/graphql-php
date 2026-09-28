<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Type\Introspection;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\ValidationContext;

final class QueryComplexityTest extends QuerySecurityTestCase
{
    private static QueryComplexity $rule;

    public function testSimpleQueries(): void
    {
        $query = 'query MyQuery { human { firstName } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    /**
     * @throws \Exception
     * @throws \GraphQL\Error\SyntaxError
     */
    private function assertDocumentValidators(string $query, int $queryComplexity, int $startComplexity): void
    {
        for ($maxComplexity = $startComplexity; $maxComplexity >= 0; --$maxComplexity) {
            $positions = [];

            if ($maxComplexity < $queryComplexity && $maxComplexity !== QueryComplexity::DISABLED) {
                $positions = [self::createFormattedError($maxComplexity, $queryComplexity)];
            }

            $this->assertDocumentValidator($query, $maxComplexity, $positions);
        }
    }

    public function testInlineFragmentQueries(): void
    {
        $query = 'query MyQuery { human { ... on Human { firstName } } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testTypelessInlineFragmentQueries(): void
    {
        $query = 'query MyQuery { human { ... { firstName } } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testFragmentQueries(): void
    {
        $query = 'query MyQuery { human { ...F1 } } fragment F1 on Human { firstName}';

        $this->assertDocumentValidators($query, 2, 3);
    }

    /** @dataProvider fragmentQueriesOnRootProvider */
    public function testFragmentQueriesOnRoot(string $query): void
    {
        $this->assertDocumentValidators($query, 12, 13);
    }

    /** @return iterable<array<string>> */
    public function fragmentQueriesOnRootProvider(): iterable
    {
        yield ['fragment humanFragment on QueryRoot { human { dogs { name } } } query { ...humanFragment }'];
        yield ['query { ...humanFragment } fragment humanFragment on QueryRoot { human { dogs { name } } }'];
    }

    public function testAliasesQueries(): void
    {
        $query = 'query MyQuery { thomas: human(name: "Thomas") { firstName } jeremy: human(name: "Jeremy") { firstName } }';

        $this->assertDocumentValidators($query, 4, 5);
    }

    public function testCustomComplexityQueries(): void
    {
        $query = 'query MyQuery { human { dogs { name } } }';

        $this->assertDocumentValidators($query, 12, 13);
    }

    public function testCustomComplexityWithArgsQueries(): void
    {
        $query = 'query MyQuery { human { dogs(name: "Root") { name } } }';

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testCustomComplexityWithVariablesQueries(): void
    {
        $query = 'query MyQuery($dog: String!) { human { dogs(name: $dog) { name } } }';

        $this->getRule()->setRawVariableValues(['dog' => 'Roots']);

        $this->assertDocumentValidators($query, 3, 4);
    }

    /** @throws \InvalidArgumentException */
    protected function getRule(int $max = 0): QueryComplexity
    {
        self::$rule ??= new QueryComplexity($max);
        self::$rule->setMaxQueryComplexity($max);

        return self::$rule;
    }

    public function testQueryWithEnabledIncludeDirectives(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withDogs' => true]);

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testQueryWithDisabledIncludeDirectives(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withDogs' => false]);

        $this->assertDocumentValidators($query, 1, 2);
    }

    public function testQueryWithEnabledSkipDirectives(): void
    {
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => true]);

        $this->assertDocumentValidators($query, 1, 2);
    }

    public function testQueryWithDisabledSkipDirectives(): void
    {
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => false]);

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testQueryWithMultipleDirectives(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!, $withoutDogName: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name @skip(if:$withoutDogName) } } }';

        $this->getRule()->setRawVariableValues([
            'withDogs' => true,
            'withoutDogName' => true,
        ]);

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testQueryWithCustomDirective(): void
    {
        $query = 'query MyQuery { human { ... on Human { firstName @foo(bar: false) } } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testQueryWithCustomAndSkipDirective(): void
    {
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name @foo(bar: true) } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => true]);

        $this->assertDocumentValidators($query, 1, 2);
    }

    /**
     * Verifies that a non-excluding directive appearing before @skip on the same field
     * does not prevent the @skip from being evaluated, avoiding incorrect complexity.
     */
    public function testQueryWithNonExcludingDirectiveBeforeSkip(): void
    {
        // @foo appears before @skip on the same field; @skip(if:true) should still exclude dogs
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @foo(bar: true) @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => true]);

        // dogs is excluded by @skip, so complexity is 1 (only human)
        $this->assertDocumentValidators($query, 1, 2);
    }

    /**
     * Verifies that @include(if:true) followed by @skip(if:true) on the same field correctly excludes it.
     * Without evaluating all directives, returning early on @include(if:true) would yield the wrong result.
     */
    public function testQueryWithIncludeAndSkipDirectives(): void
    {
        // @include(if:true) alone would include the field, but @skip(if:true) should still exclude it
        $query = 'query MyQuery($withDogs: Boolean!, $withoutDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withDogs' => true, 'withoutDogs' => true]);

        // dogs is excluded by @skip(if:true), so complexity is 1 (only human)
        $this->assertDocumentValidators($query, 1, 2);
    }

    public function testComplexityIntrospectionQuery(): void
    {
        $query = Introspection::getIntrospectionQuery();

        $this->assertDocumentValidator($query, 0);
    }

    public function testMixedIntrospectionAndRegularFields(): void
    {
        $query = 'query MyQuery { __schema { queryType { name } } human { firstName } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testIntrospectionTypeMetaFieldQuery(): void
    {
        $this->assertIntrospectionTypeMetaFieldQuery(2);
    }

    public function testTypeNameMetaFieldQuery(): void
    {
        $this->assertTypeNameMetaFieldQuery(3);
    }

    public function testSkippedWhenThereAreOtherValidationErrors(): void
    {
        $query = 'query MyQuery { human(name: INVALID_VALUE) { dogs {name} } }';

        $reportedError = new Error('OtherValidatorError');
        $otherRule = new CustomValidationRule(
            'otherRule',
            static fn (ValidationContext $context): array => [
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => static function () use ($context, $reportedError): void {
                        $context->reportError($reportedError);
                    },
                ],
            ]
        );

        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [$otherRule, $this->getRule(1)]
        );

        self::assertCount(1, $errors);
        self::assertSame($reportedError, $errors[0]);
    }

    public function testMultipleOperations(): void
    {
        $query = <<<GRAPHQL
        query A { # complexity 2
          human { firstName }
        }
        query B { # complexity 12
          human { dogs { name } }
        }
        query C { # complexity 13
          human { firstName dogs { name } }
        }
        GRAPHQL;

        $schema = QuerySecuritySchema::buildSchema();
        $ast = Parser::parse($query);

        // When no operation exceeds the limit, `getQueryComplexity` returns complexity of
        // the last operation.
        DocumentValidator::validate($schema, $ast, [$this->getRule(100)]);
        self::assertSame(13, self::$rule->getQueryComplexity());

        // When any operation exceeds the limit, `getQueryComplexity` returns the complexity
        // of the first operation exceeding the limit.
        DocumentValidator::validate($schema, $ast, [$this->getRule(2)]);
        self::assertSame(12, self::$rule->getQueryComplexity());
    }

    protected static function getErrorMessage(int $max, int $count): string
    {
        return QueryComplexity::maxQueryComplexityErrorMessage($max, $count);
    }

    /**
     * Verifies that variable coercion is not triggered for fields without @include/@skip
     * directives. Previously, directiveExcludesField() called getVariableValues() for
     * every field unconditionally. Now it is lazy: coercion only happens when an
     * \@include or \@skip directive is actually encountered on the field.
     *
     * This test passes a PHP integer for a variable declared as `String!`. The `StringType`
     * rejects non-string values in `parseValue`, so coercing this variable would throw. With
     * the old code (unconditional coercion) this would propagate as an exception through
     * `directiveExcludesField` for every field. With lazy coercion it is never triggered,
     * because no field in the query uses @include or @skip.
     */
    public function testVariableCoercionIsLazyWhenNoIncludeOrSkipDirectives(): void
    {
        // $dog is declared but none of the fields use @include/@skip,
        // so variable coercion should never be triggered by directiveExcludesField.
        $query = 'query MyQuery($dog: String!) { human { firstName } }';

        // An integer is rejected by StringType::parseValue, so if coercion ran (as it did
        // before the lazy-coercion fix) it would throw. With the fix, coercion is never
        // triggered here because no field uses @include or @skip.
        $this->getRule()->setRawVariableValues(['dog' => 42]);

        // Should produce no errors from the complexity rule itself (complexity is within bounds)
        $this->assertDocumentValidators($query, 2, 3);
    }

    /**
     * Persisted operation manifests contain only the operation text, so there are no
     * variable values to validate them with.
     */
    public function testUnprovidedVariableValuesForConditionsFailByDefault(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Variable "$withDogs" of required type "Boolean!" was not provided.');

        DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [new QueryComplexity(100)]
        );
    }

    /** @dataProvider unprovidedVariableConditionProvider */
    public function testUnprovidedVariableConditionsCountAsIncluded(string $query): void
    {
        // dogs is counted as if it were included: human(1) + dogs(1, given the literal name) + name(1)
        self::assertSame(3, $this->complexityWithoutVariableValues($query));
    }

    /** @return iterable<array{string}> */
    public static function unprovidedVariableConditionProvider(): iterable
    {
        yield ['query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }'];
        yield ['query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name } } }'];
        yield ['query MyQuery($withDogs: Boolean!, $withoutDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) @skip(if:$withoutDogs) { name } } }'];
    }

    /** @dataProvider literalConditionProvider */
    public function testLiteralConditionsStillExcludeFields(string $query): void
    {
        // dogs is excluded, so only human(1) is counted
        self::assertSame(1, $this->complexityWithoutVariableValues($query));
    }

    /** @return iterable<array{string}> */
    public static function literalConditionProvider(): iterable
    {
        yield ['query MyQuery { human { dogs(name: "Root") @include(if:false) { name } } }'];
        yield ['query MyQuery { human { dogs(name: "Root") @skip(if:true) { name } } }'];
        yield ['query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) @skip(if:true) { name } } }'];
    }

    public function testVariableConditionsFallBackToTheirDefaultValue(): void
    {
        $query = 'query MyQuery($withDogs: Boolean = false) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        self::assertSame(1, $this->complexityWithoutVariableValues($query));
    }

    public function testProvidedVariableValuesAreStillUsedForConditions(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        self::assertSame(1, $this->complexityWithoutVariableValues($query, ['withDogs' => false]));
        self::assertSame(3, $this->complexityWithoutVariableValues($query, ['withDogs' => true]));
    }

    public function testInvalidProvidedVariableValuesStillFail(): void
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Variable "$withDogs" got invalid value "not a boolean"; Boolean cannot represent a non boolean value: "not a boolean"');

        $this->complexityWithoutVariableValues($query, ['withDogs' => 'not a boolean']);
    }

    /** @dataProvider unprovidedVariableArgumentProvider */
    public function testArgumentsDependingOnUnprovidedVariablesAreOmitted(string $query): void
    {
        // The complexity function of dogs falls back to 10 when it is not passed a name:
        // human(1) + dogs(10) + name(1)
        self::assertSame(12, $this->complexityWithoutVariableValues($query));
    }

    /** @return iterable<array{string}> */
    public static function unprovidedVariableArgumentProvider(): iterable
    {
        yield ['query MyQuery($name: String!) { human { dogs(name: $name) { name } } }'];
        yield ['query MyQuery($name: String!) { human { dogs(names: [$name]) { name } } }'];
        yield ['query MyQuery($name: String!) { human { dogs(filter: {name: $name}) { name } } }'];
    }

    public function testRequiredArgumentsDependingOnUnprovidedVariablesAreOmitted(): void
    {
        $query = 'query MyQuery($first: Int!) { human { pets(first: $first) { name } } }';

        // first is unknown, so the complexity function of pets falls back to 100, while
        // last still gets the default value of its definition:
        // human(1) + name(1) + first(100) + last(5)
        self::assertSame(107, $this->complexityWithoutVariableValues($query));
    }

    public function testArgumentDefaultValuesDoNotHideUnprovidedVariables(): void
    {
        $query = 'query MyQuery($last: Int) { human { pets(first: 2, last: $last) { name } } }';

        // last is unknown, so the default value of its definition must not be used,
        // which makes the complexity function of pets fall back to 1000:
        // human(1) + name(1) + first(2) + last(1000)
        self::assertSame(1004, $this->complexityWithoutVariableValues($query));
    }

    public function testProvidedVariableValuesAreStillUsedForArguments(): void
    {
        $query = 'query MyQuery($name: String!) { human { dogs(name: $name) { name } } }';

        // Passing a name makes the complexity function of dogs return 1: human(1) + dogs(1) + name(1)
        self::assertSame(3, $this->complexityWithoutVariableValues($query, ['name' => 'Root']));
    }

    /**
     * @param array<string, mixed> $rawVariableValues
     *
     * @throws \Exception
     * @throws \GraphQL\Error\SyntaxError
     */
    private function complexityWithoutVariableValues(string $query, array $rawVariableValues = []): int
    {
        $rule = new QueryComplexity(PHP_INT_MAX);
        $rule->assumeWorstCaseForUnprovidedVariables = true;
        $rule->setRawVariableValues($rawVariableValues);

        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [$rule]
        );
        self::assertSame([], $errors);

        return $rule->getQueryComplexity();
    }

    /**
     * Verifies that variable coercion is cached: when multiple fields each have @skip,
     * coercion is only performed once, not once per field.
     */
    public function testVariableCoercionIsCachedAcrossMultipleDirectives(): void
    {
        // Two fields each with @skip using the same variable.
        // Coercion should occur once and be cached.
        $query = 'query MyQuery($skipIt: Boolean!) { human { firstName @skip(if:$skipIt) dogs { name @skip(if:$skipIt) } } }';

        $this->getRule()->setRawVariableValues(['skipIt' => false]);

        // skipIt=false means nothing is skipped: complexity = human(1) + firstName(1) + dogs(10) + name(1) = 13
        $this->assertDocumentValidators($query, 13, 14);
    }
}
