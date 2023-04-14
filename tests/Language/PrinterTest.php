<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;

use function Safe\file_get_contents;

/**
 * @see describe('Printer: Query document', () => {
 */
final class PrinterTest extends TestCaseBase
{
    public function testDoesntAlterAST(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        self::assertEquals($astCopy, $ast);

        Printer::doPrint($ast);
        self::assertEquals($astCopy, $ast);
    }

    /** @see it('prints minimal ast', () => { */
    public function testPrintsMinimalAst(): void
    {
        $ast = new FieldNode([
            'name' => new NameNode(['value' => 'foo']),
            'arguments' => new NodeList([]),
            'directives' => new NodeList([]),
        ]);
        self::assertSame('foo', Printer::doPrint($ast));
    }

    /** @see it('produces helpful error messages', () => { */
    public function testProducesHelpfulErrorMessages(): void
    {
        self::markTestSkipped('Not necessary because our class based AST makes it impossible to pass bad data.');
    }

    /** @see it('correctly prints non-query operations without name', () => { */
    public function testCorrectlyPrintsNonQueryOperationsWithoutName(): void
    {
        $queryAstShorthanded = Parser::parse('query { id, name }');

        self::assertSame(
            <<<'GRAPHQL'
      {
        id
        name
      }

      GRAPHQL,
            Printer::doPrint($queryAstShorthanded)
        );

        $mutationAst = Parser::parse('mutation { id, name }');
        self::assertSame(
            <<<'GRAPHQL'
      mutation {
        id
        name
      }

      GRAPHQL,
            Printer::doPrint($mutationAst)
        );

        $queryAstWithArtifacts = Parser::parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        self::assertSame(
            <<<'GRAPHQL'
      query ($foo: TestType) @testDirective {
        id
        name
      }

      GRAPHQL,
            Printer::doPrint($queryAstWithArtifacts)
        );

        $mutationAstWithArtifacts = Parser::parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        self::assertSame(
            <<<'GRAPHQL'
      mutation ($foo: TestType) @testDirective {
        id
        name
      }

      GRAPHQL,
            Printer::doPrint($mutationAstWithArtifacts)
        );
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
        self::assertSame($expected, Printer::doPrint($queryWithNonNullVariable));
    }

    /** @see it('prints query with variable directives', () => { */
    public function testPrintsQueryWithVariableDirectives(): void
    {
        $queryAstWithVariableDirective = Parser::parse(
            'query ($foo: TestType = {a: 123} @testDirective(if: true) @test) { id }'
        );
        self::assertSame(
            <<<'GRAPHQL'
      query ($foo: TestType = { a: 123 } @testDirective(if: true) @test) {
        id
      }

      GRAPHQL,
            Printer::doPrint($queryAstWithVariableDirective)
        );
    }

    /** @see it('keeps arguments on one line if line is short (<= 80 chars)', () => { */
    public function testKeepsArgumentsOnOneLineIfLineIsShortBelowOrEquals80Chars(): void
    {
        $queryASTWithMultipleArguments = Parser::parse(
            '{trip(wheelchair:false arriveBy:false){dateTime}}',
        );
        self::assertSame(
            <<<'GRAPHQL'
      {
        trip(wheelchair: false, arriveBy: false) {
          dateTime
        }
      }

      GRAPHQL,
            Printer::doPrint($queryASTWithMultipleArguments)
        );
    }

    /** @see it('puts arguments on multiple lines if line is long (> 80 chars)', () => { */
    public function testPutsArgumentsOnMultipleLinesIfLineIsLongMoreThan80Chars(): void
    {
        $queryASTWithMultipleArguments = Parser::parse(
            '{trip(wheelchair:false arriveBy:false includePlannedCancellations:true transitDistanceReluctance:2000){dateTime}}',
        );
        self::assertSame(
            <<<'GRAPHQL'
      {
        trip(
          wheelchair: false
          arriveBy: false
          includePlannedCancellations: true
          transitDistanceReluctance: 2000
        ) {
          dateTime
        }
      }

      GRAPHQL,
            Printer::doPrint($queryASTWithMultipleArguments)
        );
    }

    /** @see it('prints fragment with variable directives') */
    public function testPrintsFragmentWithVariableDirectives(): void
    {
        $queryAstWithVariableDirective = Parser::parse(
            'fragment Foo($foo: TestType @test) on TestType @testDirective { id }',
            ['experimentalFragmentVariables' => true]
        );
        $expected = <<<'GRAPHQL'
fragment Foo($foo: TestType @test) on TestType @testDirective {
  id
}

GRAPHQL;
        self::assertSame($expected, Printer::doPrint($queryAstWithVariableDirective));
    }

    /** @see it('Experimental: correctly prints fragment defined variables') */
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

        self::assertSame(
            Printer::doPrint($fragmentWithVariable),
            <<<'GRAPHQL'
fragment Foo($a: ComplexType, $b: Boolean = false) on TestType {
  id
}

GRAPHQL
        );
    }

    /** @see it('prints kitchen sink without altering ast', () => { */
    public function testPrintsKitchenSinkWithoutAlteringAST(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        self::assertSame(<<<'GRAPHQL'
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
  foo(
    size: $size
    bar: $b
    obj: { key: "value", block: """
    block string uses \"""
    """ }
  )
}

{
  unnamed(truthy: true, falsey: false, nullish: null)
  query
}

GRAPHQL, Printer::doPrint($ast));
    }

    public function testPrintPrimitives(): void
    {
        self::assertASTMatches('3', AST::astFromValue(3, Type::int()));
        self::assertASTMatches('3.14', AST::astFromValue(3.14, Type::float()));
    }
}
