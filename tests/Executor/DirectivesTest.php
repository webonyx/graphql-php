<?php
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class DirectivesTest extends \PHPUnit_Framework_TestCase
{
    // Describe: Execute: handles directives

    /**
     * @describe works without directives
     * @it basic query works
     */
    public function testWorksWithoutDirectives()
    {
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b }'));
    }

    /**
     * @describe works on scalars
     */
    public function testWorksOnScalars()
    {
        // if true includes scalar
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b @include(if: true) }'));

        // if false omits on scalar
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery('{ a, b @include(if: false) }'));

        // unless false includes scalar
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b @skip(if: false) }'));

        // unless true omits scalar
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery('{ a, b @skip(if: true) }'));
    }

    /**
     * @describe works on fragment spreads
     */
    public function testWorksOnFragmentSpreads()
    {
        // if false omits fragment spread
        $q = '
        query Q {
          a
          ...Frag @include(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));

        // if true includes fragment spread
        $q = '
        query Q {
          a
          ...Frag @include(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless false includes fragment spread
        $q = '
        query Q {
          a
          ...Frag @skip(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless true omits fragment spread
        $q = '
        query Q {
          a
          ...Frag @skip(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));
    }

    /**
     * @describe works on inline fragment
     */
    public function testWorksOnInlineFragment()
    {
        // if false omits inline fragment
        $q = '
        query Q {
          a
          ... on TestType @include(if: false) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));

        // if true includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @include(if: true) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless false includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @skip(if: false) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless true includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @skip(if: true) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));
    }

    /**
     * @describe works on anonymous inline fragment
     */
    public function testWorksOnAnonymousInlineFragment()
    {
        // if false omits anonymous inline fragment
        $q = '
        query Q {
          a
          ... @include(if: false) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));

        // if true includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @include(if: true) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless false includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @skip(if: false) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless true includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @skip(if: true) {
            b
          }
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));
    }

    /**
     * @describe works with skip and include directives
     */
    public function testWorksWithSkipAndIncludeDirectives()
    {
        // include and no skip
        $this->assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('{ a, b @include(if: true) @skip(if: false) }')
        );

        // include and skip
        $this->assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('{ a, b @include(if: true) @skip(if: true) }')
        );

        // no include or skip
        $this->assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('{ a, b @include(if: false) @skip(if: false) }')
        );
    }




    private static $schema;

    private static $data;

    private static function getSchema()
    {
        if (!self::$schema) {
            self::$schema = new Schema([
                'query' => new ObjectType([
                    'name' => 'TestType',
                    'fields' => [
                        'a' => ['type' => Type::string()],
                        'b' => ['type' => Type::string()]
                    ]
                ])
            ]);
        }
        return self::$schema;
    }

    private static function getData()
    {
        return self::$data ?: (self::$data = [
            'a' => 'a',
            'b' => 'b'
        ]);
    }

    private function executeTestQuery($doc)
    {
        return Executor::execute(self::getSchema(), Parser::parse($doc), self::getData())->toArray();
    }
}
