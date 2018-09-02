<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Describe: Execute: handles directives
 */
class DirectivesTest extends TestCase
{
    /** @var Schema */
    private static $schema;

    /** @var string[] */
    private static $data;

    /**
     * @see it('basic query works')
     */
    public function testWorksWithoutDirectives() : void
    {
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b }'));
    }

    /**
     * @param Source|string $doc
     * @return mixed[]
     */
    private function executeTestQuery($doc) : array
    {
        return Executor::execute(self::getSchema(), Parser::parse($doc), self::getData())->toArray();
    }

    private static function getSchema() : Schema
    {
        if (! self::$schema) {
            self::$schema = new Schema([
                'query' => new ObjectType([
                    'name'   => 'TestType',
                    'fields' => [
                        'a' => ['type' => Type::string()],
                        'b' => ['type' => Type::string()],
                    ],
                ]),
            ]);
        }

        return self::$schema;
    }

    /**
     * @return string[]
     */
    private static function getData() : array
    {
        return self::$data ?: (self::$data = [
            'a' => 'a',
            'b' => 'b',
        ]);
    }

    public function testWorksOnScalars() : void
    {
        // if true includes scalar
        $this->assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('{ a, b @include(if: true) }')
        );

        // if false omits on scalar
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery('{ a, b @include(if: false) }'));

        // unless false includes scalar
        $this->assertEquals(['data' => ['a' => 'a', 'b' => 'b']], $this->executeTestQuery('{ a, b @skip(if: false) }'));

        // unless true omits scalar
        $this->assertEquals(['data' => ['a' => 'a']], $this->executeTestQuery('{ a, b @skip(if: true) }'));
    }

    public function testWorksOnFragmentSpreads() : void
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

    public function testWorksOnInlineFragment() : void
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

    public function testWorksOnAnonymousInlineFragment() : void
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

    public function testWorksWithSkipAndIncludeDirectives() : void
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
}
