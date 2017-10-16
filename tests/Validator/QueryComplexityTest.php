<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\ValidationContext;

class QueryComplexityTest extends AbstractQuerySecurityTest
{
    /** @var QueryComplexity  */
    private static $rule;

    /**
     * @param $max
     * @param $count
     *
     * @return string
     */
    protected function getErrorMessage($max, $count)
    {
        return QueryComplexity::maxQueryComplexityErrorMessage($max, $count);
    }

    /**
     * @param $maxDepth
     *
     * @return QueryComplexity
     */
    protected function getRule($maxDepth = null)
    {
        if (null === self::$rule) {
            self::$rule = new QueryComplexity($maxDepth);
        } elseif (null !== $maxDepth) {
            self::$rule->setMaxQueryComplexity($maxDepth);
        }

        return self::$rule;
    }

    public function testSimpleQueries()
    {
        $query = 'query MyQuery { human { firstName } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testInlineFragmentQueries()
    {
        $query = 'query MyQuery { human { ... on Human { firstName } } }';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testFragmentQueries()
    {
        $query = 'query MyQuery { human { ...F1 } } fragment F1 on Human { firstName}';

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testAliasesQueries()
    {
        $query = 'query MyQuery { thomas: human(name: "Thomas") { firstName } jeremy: human(name: "Jeremy") { firstName } }';

        $this->assertDocumentValidators($query, 4, 5);
    }

    public function testCustomComplexityQueries()
    {
        $query = 'query MyQuery { human { dogs { name } } }';

        $this->assertDocumentValidators($query, 12, 13);
    }

    public function testCustomComplexityWithArgsQueries()
    {
        $query = 'query MyQuery { human { dogs(name: "Root") { name } } }';

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testCustomComplexityWithVariablesQueries()
    {
        $query = 'query MyQuery($dog: String!) { human { dogs(name: $dog) { name } } }';

        $this->getRule()->setRawVariableValues(['dog' => 'Roots']);

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testQueryWithEnabledIncludeDirectives()
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withDogs' => true]);

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testQueryWithDisabledIncludeDirectives()
    {
        $query = 'query MyQuery($withDogs: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withDogs' => false]);

        $this->assertDocumentValidators($query, 1, 2);
    }

    public function testQueryWithEnabledSkipDirectives()
    {
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => true]);

        $this->assertDocumentValidators($query, 1, 2);
    }

    public function testQueryWithDisabledSkipDirectives()
    {
        $query = 'query MyQuery($withoutDogs: Boolean!) { human { dogs(name: "Root") @skip(if:$withoutDogs) { name } } }';

        $this->getRule()->setRawVariableValues(['withoutDogs' => false]);

        $this->assertDocumentValidators($query, 3, 4);
    }

    public function testQueryWithMultipleDirectives()
    {
        $query = 'query MyQuery($withDogs: Boolean!, $withoutDogName: Boolean!) { human { dogs(name: "Root") @include(if:$withDogs) { name @skip(if:$withoutDogName) } } }';

        $this->getRule()->setRawVariableValues([
            'withDogs' => true,
            'withoutDogName' => true
        ]);

        $this->assertDocumentValidators($query, 2, 3);
    }

    public function testComplexityIntrospectionQuery()
    {
        $this->assertIntrospectionQuery(181);
    }

    public function testIntrospectionTypeMetaFieldQuery()
    {
        $this->assertIntrospectionTypeMetaFieldQuery(2);
    }

    public function testTypeNameMetaFieldQuery()
    {
        $this->assertTypeNameMetaFieldQuery(3);
    }

    public function testSkippedWhenThereAreOtherValidationErrors()
    {
        $query = 'query MyQuery { human(name: INVALID_VALUE) { dogs {name} } }';

        $reportedError = new Error("OtherValidatorError");
        $otherRule = new CustomValidationRule('otherRule', function(ValidationContext $context) use ($reportedError) {
            return [
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function() use ($context, $reportedError) {
                        $context->reportError($reportedError);
                    }
                ]
            ];
        });

        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [$otherRule, $this->getRule(1)]
        );

        $this->assertEquals(1, count($errors));
        $this->assertSame($reportedError, $errors[0]);

        $this->setExpectedException('GraphQL\Error\Error');
        DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($query),
            [$this->getRule(1)]
        );
    }

    private function assertDocumentValidators($query, $queryComplexity, $startComplexity)
    {
        for ($maxComplexity = $startComplexity; $maxComplexity >= 0; --$maxComplexity) {
            $positions = [];

            if ($maxComplexity < $queryComplexity && $maxComplexity !== QueryComplexity::DISABLED) {
                $positions = [$this->createFormattedError($maxComplexity, $queryComplexity)];
            }

            $this->assertDocumentValidator($query, $maxComplexity, $positions);
        }
    }
}
