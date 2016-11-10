<?php

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;

class PrinterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it does not alter ast
     */
    public function testDoesntAlterAST()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parser = new Parser(new Lexer());
        $ast = $parser->parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        $this->assertEquals($astCopy, $ast);

        $printer = new Printer();
        $printer->doPrint($ast);
        $this->assertEquals($astCopy, $ast);
    }

    /**
     * @it prints minimal ast
     */
    public function testPrintsMinimalAst()
    {
        $printer = new Printer();

        $ast = new Field(['name' => new Name('foo')]);
        $this->assertEquals('foo', $printer->doPrint($ast));
    }

    /**
     * @it produces helpful error messages
     */
    public function testProducesHelpfulErrorMessages()
    {
        $badAst1 = new \ArrayObject(array('random' => 'Data'));
        try {
            $printer = new Printer();
            $printer->doPrint($badAst1);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Invalid AST Node: {"random":"Data"}', $e->getMessage());
        }
    }

    /**
     * @it correctly prints non-query operations without name
     */
    public function testCorrectlyPrintsOpsWithoutName()
    {
        $parser = new Parser(new Lexer());
        $queryAstShorthanded = $parser->parse('query { id, name }');

        $printer = new Printer();

        $expected = '{
  id
  name
}
';
        $this->assertEquals($expected, $printer->doPrint($queryAstShorthanded));

        $mutationAst = $parser->parse('mutation { id, name }');
        $expected = 'mutation {
  id
  name
}
';
        $this->assertEquals($expected, $printer->doPrint($mutationAst));

        $queryAstWithArtifacts = $parser->parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'query ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, $printer->doPrint($queryAstWithArtifacts));

        $mutationAstWithArtifacts = $parser->parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'mutation ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, $printer->doPrint($mutationAstWithArtifacts));
    }

    /**
     * @it prints kitchen sink
     */
    public function testPrintsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parser = new Parser(new Lexer());
        $ast = $parser->parse($kitchenSink);

        $printer = new Printer();
        $printed = $printer->doPrint($ast);

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
  foo(size: $size, bar: $b, obj: {key: "value"})
}

{
  unnamed(truthy: true, falsey: false)
  query
}

EOT;
        $this->assertEquals($expected, $printed);
    }
}
