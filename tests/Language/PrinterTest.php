<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

use function file_get_contents;

class PrinterTest extends TestCase
{
    /**
     * @see it('does not alter ast')
     */
    public function testDoesntAlterAST(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast         = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        self::assertEquals($astCopy, $ast);

        Printer::doPrint($ast);
        self::assertEquals($astCopy, $ast);
    }

    /**
     * @see it('prints minimal ast')
     */
    public function testPrintsMinimalAst(): void
    {
        $ast = new FieldNode([
            'name' => new NameNode(['value' => 'foo']),
            'arguments' => new NodeList([]),
            'directives' => new NodeList([]),
        ]);
        self::assertEquals('foo', Printer::doPrint($ast));
    }

    /**
     * @see it('correctly prints non-query operations without name')
     */
    public function testCorrectlyPrintsOpsWithoutName(): void
    {
        $queryAstShorthanded = Parser::parse('query { id, name }');

        $expected = '{
  id
  name
}
';
        self::assertEquals($expected, Printer::doPrint($queryAstShorthanded));

        $mutationAst = Parser::parse('mutation { id, name }');
        $expected    = 'mutation {
  id
  name
}
';
        self::assertEquals($expected, Printer::doPrint($mutationAst));

        $queryAstWithArtifacts = Parser::parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        $expected              = 'query ($foo: TestType) @testDirective {
  id
  name
}
';
        self::assertEquals($expected, Printer::doPrint($queryAstWithArtifacts));

        $mutationAstWithArtifacts = Parser::parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        $expected                 = 'mutation ($foo: TestType) @testDirective {
  id
  name
}
';
        self::assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    public function testCorrectlyPrintsOpsWithNonNullableVariableArgument(): void
    {
        $queryWithNonNullVariable = Parser::parse(
            'query ($var: ID!) { a(arg: $var) { __typename } }
'
        );

        $expected = 'query ($var: ID!) {
  a(arg: $var) {
    __typename
  }
}
';
        self::assertEquals($expected, Printer::doPrint($queryWithNonNullVariable));
    }

    /**
     * @see it('prints query with variable directives')
     */
    public function testPrintsQueryWithVariableDirectives(): void
    {
        $queryAstWithVariableDirective = Parser::parse(
            'query ($foo: TestType = {a: 123} @testDirective(if: true) @test) { id }'
        );
        $expected                      = 'query ($foo: TestType = {a: 123} @testDirective(if: true) @test) {
  id
}
';
        self::assertEquals($expected, Printer::doPrint($queryAstWithVariableDirective));
    }

    /**
     * @see it('prints fragment with variable directives')
     */
    public function testPrintsFragmentWithVariableDirectives(): void
    {
        $queryAstWithVariableDirective = Parser::parse(
            'fragment Foo($foo: TestType @test) on TestType @testDirective { id }',
            ['experimentalFragmentVariables' => true]
        );
        $expected                      = 'fragment Foo($foo: TestType @test) on TestType @testDirective {
  id
}
';
        self::assertEquals($expected, Printer::doPrint($queryAstWithVariableDirective));
    }

    /**
     * @see it('Experimental: correctly prints fragment defined variables')
     */
    public function testExperimentalCorrectlyPrintsFragmentDefinedVariables(): void
    {
        $fragmentWithVariable = Parser::parse(
            '
          fragment Foo($a: ComplexType, $b: Boolean = false) on TestType {
            id
          }
          ',
            ['experimentalFragmentVariables' => true]
        );

        self::assertEquals(
            Printer::doPrint($fragmentWithVariable),
            'fragment Foo($a: ComplexType, $b: Boolean = false) on TestType {
  id
}
'
        );
    }

    /**
     * @see it('prints kitchen sink')
     */
    public function testPrintsKitchenSink(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast         = Parser::parse($kitchenSink);

        $printed = Printer::doPrint($ast);

        $expected = <<<'EOT'
query queryName($foo: ComplexType, $site: Site = MOBILE) {
  whoever123is: node(id: [123, 456]) {
    id
    ... on User @defer {
      field2 {
        id
        alias: field1(first: 10, after: $foo) @include(if: $foo) {
          id
          ...frag
        }
      }
    }
    ... @skip(unless: $foo) {
      id
    }
    ... {
      id
    }
  }
}

mutation likeStory {
  like(story: 123) @defer {
    story {
      id
    }
  }
}

subscription StoryLikeSubscription($input: StoryLikeSubscribeInput) {
  storyLikeSubscribe(input: $input) {
    story {
      likers {
        count
      }
      likeSentence {
        text
      }
    }
  }
}

fragment frag on Friend {
  foo(size: $size, bar: $b, obj: {key: "value", block: """
    block string uses \"""
  """})
}

{
  unnamed(truthy: true, falsey: false, nullish: null)
  query
}

EOT;
        self::assertEquals($expected, $printed);
    }

    public function testPrintPrimitives(): void
    {
        self::assertSame('3', Printer::doPrint(AST::astFromValue(3, Type::int())));
        self::assertSame('3.14', Printer::doPrint(AST::astFromValue(3.14, Type::float())));
    }
}
