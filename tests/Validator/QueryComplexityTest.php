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
}
