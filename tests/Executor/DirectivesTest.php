<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @see describe('Execute: handles directives'
 */
class DirectivesTest extends TestCase
{
    private static Schema $schema;

    private const DATA = [
        'a' => 'a',
        'b' => 'b',
    ];

    /**
     * @return array<string, mixed>
     */
    private function executeTestQuery(string $doc): array
    {
        return Executor::execute(self::getSchema(), Parser::parse($doc), self::DATA)
            ->toArray();
    }

    private static function getSchema(): Schema
    {
        return self::$schema ??= new Schema([
            'query' => new ObjectType([
                'name'   => 'TestType',
                'fields' => [
                    'a' => ['type' => Type::string()],
                    'b' => ['type' => Type::string()],
                ],
            ]),
        ]);
    }

    /**
     * @see describe('works without directives', () => {
     */
    public function testWorksWithoutDirectives(): void
    {
        // it('basic query works', () => {
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('{ a, b }')
        );
    }

    /**
     * @see describe('works on scalars', () => {
     */
    public function testWorksOnScalars(): void
    {
        // it('if true includes scalar', () => {
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('{ a, b @include(if: true) }')
        );

        // it('if false omits on scalar', () => {
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('{ a, b @include(if: false) }')
        );

        // it('unless false includes scalar', () => {
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('{ a, b @skip(if: false) }')
        );

        // it('unless true omits scalar', () => {
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('{ a, b @skip(if: true) }')
        );
    }

    /**
     * @see describe('works on fragment spreads', () => {
     */
    public function testWorksOnFragmentSpreads(): void
    {
        // it('if false omits fragment spread', () => {
        $q = '
        query {
          a
          ...Frag @include(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );

        // it('if true includes fragment spread', () => {
        $q = '
        query {
          a
          ...Frag @include(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless false includes fragment spread', () => {
        $q = '
        query {
          a
          ...Frag @skip(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless true omits fragment spread', () => {
        $q = '
        query {
          a
          ...Frag @skip(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );
    }

    /**
     * @see describe('works on inline fragment', () => {
     */
    public function testWorksOnInlineFragment(): void
    {
        // it('if false omits inline fragment', () => {
        $q = '
        query {
          a
          ... on TestType @include(if: false) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );

        // it('if true includes inline fragment', () => {
        $q = '
        query {
          a
          ... on TestType @include(if: true) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless false includes inline fragment', () => {
        $q = '
        query {
          a
          ... on TestType @skip(if: false) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless true includes inline fragment', () => {
        $q = '
        query {
          a
          ... on TestType @skip(if: true) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );
    }

    /**
     * @see describe('works on anonymous inline fragment', () => {
     */
    public function testWorksOnAnonymousInlineFragment(): void
    {
        // it('if false omits anonymous inline fragment', () => {
        $q = '
        query {
          a
          ... @include(if: false) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );

        // it('if true includes anonymous inline fragment', () => {
        $q = '
        query {
          a
          ... @include(if: true) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless false includes anonymous inline fragment', () => {
        $q = '
        query {
          a
          ... @skip(if: false) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery($q)
        );

        // it('unless true includes anonymous inline fragment', () => {
        $q = '
        query {
          a
          ... @skip(if: true) {
            b
          }
        }
        ';
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery($q)
        );
    }

    /**
     * @see describe('works with skip and include directives', () => {
     */
    public function testWorksWithSkipAndIncludeDirectives(): void
    {
        // it('include and no skip', () => {
        self::assertEquals(
            ['data' => ['a' => 'a', 'b' => 'b']],
            $this->executeTestQuery('
        {
          a
          b @include(if: true) @skip(if: false)
        }
            ')
        );

        // it('include and skip', () => {
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('
        {
          a
          b @include(if: true) @skip(if: true)
        }
            ')
        );

        // it('no include or skip', () => {
        self::assertEquals(
            ['data' => ['a' => 'a']],
            $this->executeTestQuery('
        {
          a
          b @include(if: false) @skip(if: false)
        }
            ')
        );
    }
}
