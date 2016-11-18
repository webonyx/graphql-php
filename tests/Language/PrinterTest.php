<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
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
        $ast = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        $this->assertEquals($astCopy, $ast);

        Printer::doPrint($ast);
        $this->assertEquals($astCopy, $ast);
    }

    /**
     * @it prints minimal ast
     */
    public function testPrintsMinimalAst()
    {
        $ast = new FieldNode(['name' => new NameNode(['value' => 'foo'])]);
        $this->assertEquals('foo', Printer::doPrint($ast));
    }

    /**
     * @it produces helpful error messages
     */
    public function testProducesHelpfulErrorMessages()
    {
        $badAst1 = new \ArrayObject(array('random' => 'Data'));
        try {
            Printer::doPrint($badAst1);
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
        $queryAstShorthanded = Parser::parse('query { id, name }');

        $expected = '{
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($queryAstShorthanded));

        $mutationAst = Parser::parse('mutation { id, name }');
        $expected = 'mutation {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($mutationAst));

        $queryAstWithArtifacts = Parser::parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'query ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($queryAstWithArtifacts));

        $mutationAstWithArtifacts = Parser::parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'mutation ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @it prints kitchen sink
     */
    public function testPrintsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

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
  foo(size: $size, bar: $b, obj: {key: "value"})
}

{
  unnamed(truthy: true, falsey: false, nullish: null)
  query
}

EOT;
        $this->assertEquals($expected, $printed);
    }
}
