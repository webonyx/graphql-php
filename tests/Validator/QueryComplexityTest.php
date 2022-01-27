<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\ValidationContext;

class QueryComplexityTest extends QuerySecurityTestCase
{
    private static QueryComplexity $rule;

    public function testSimpleQueries(): void
    {
        $query = 'query MyQuery { human { firstName } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    private function assertDocumentValidators(string $query, int $queryComplexity, int $startComplexity): void
    {
        for ($maxComplexity = $startComplexity; $maxComplexity >= 0; --$maxComplexity) {
            $positions = [];

            if ($maxComplexity < $queryComplexity && QueryComplexity::DISABLED !== $maxComplexity) {
                $positions = [$this->createFormattedError($maxComplexity, $queryComplexity)];
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
        $this->assertIntrospectionQuery(181);
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
            static function (ValidationContext $context) use ($reportedError): array {
                return [
                    NodeKind::OPERATION_DEFINITION => [
                        'leave' => static function () use ($context, $reportedError): void {
                            $context->reportError($reportedError);
                        },
                    ],
                ];
            }
        );

        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [$otherRule, $this->getRule(1)]
        );

        self::assertEquals(1, count($errors));
        self::assertSame($reportedError, $errors[0]);
    }

    protected function getErrorMessage(int $max, int $count): string
    {
        return QueryComplexity::maxQueryComplexityErrorMessage($max, $count);
    }
}
