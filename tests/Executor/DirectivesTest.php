<?php
namespace GraphQL\Executor;

use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class DirectivesTest extends \PHPUnit_Framework_TestCase
{
    // Execute: handles directives
    // works without directives
    public function testWorksWithoutDirectives()
    {
        // basic query works
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b }'));
    }

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
        fragment Frag on TestType {
          b
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
        fragment Frag on TestType {
          b
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
        fragment Frag on TestType {
          b
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
        fragment Frag on TestType {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));
    }

    public function testWorksOnFragment()
    {
        // if false omits fragment
        $q = '
        query Q {
          a
          ...Frag
        }
        fragment Frag on TestType @include(if: false) {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));

        // if true includes fragment
        $q = '
        query Q {
          a
          ...Frag
        }
        fragment Frag on TestType @include(if: true) {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless false includes fragment
        $q = '
        query Q {
          a
          ...Frag
        }
        fragment Frag on TestType @skip(if: false) {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery($q));

        // unless true omits fragment
        $q = '
        query Q {
          a
          ...Frag
        }
        fragment Frag on TestType @skip(if: true) {
          b
        }
        ';
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery($q));
    }




    private static $schema;

    private static $data;

    private static function getSchema()
    {
        return self::$schema ?: (self::$schema = new Schema(new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'a' => ['type' => Type::string()],
                'b' => ['type' => Type::string()]
            ]
        ])));
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
