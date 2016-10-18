<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryComplexity;

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
