<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;

class PrinterTest extends \PHPUnit_Framework_TestCase
{
    public function testDoesntAlterAST()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        $this->assertEquals($astCopy, $ast);

        Printer::doPrint($ast);
        $this->assertEquals($astCopy, $ast);
    }

    public function testPrintsMinimalAst()
    {
        $ast = new Field(['name' => new Name(['value' => 'foo'])]);
        $this->assertEquals('foo', Printer::doPrint($ast));
    }

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

    public function testX()
    {
        $queryStr = <<<'EOT'
query queryName($foo: ComplexType, $site: Site = MOBILE) {
  whoever123is {
    id
  }
}

EOT;
;
        $ast = Parser::parse($queryStr, ['noLocation' => true]);
/*
        $expectedAst = new Document(array(
            'definitions' => [
                new OperationDefinition(array(
                    'operation' => 'query',
                    'name' => new Name([
                        'value' => 'queryName'
                    ]),
                    'variableDefinitions' => [
                        new VariableDefinition([
                            'variable' => new Variable([
                                'name' => new Name(['value' => 'foo'])
                            ]),
                            'type' => new Name(['value' => 'ComplexType'])
                        ]),
                        new VariableDefinition([
                            'variable' => new Variable([
                                'name' => new Name(['value' => 'site'])
                            ]),
                            'type' => new Name(['value' => 'Site']),
                            'defaultValue' => new EnumValue(['value' => 'MOBILE'])
                        ])
                    ],
                    'directives' => [],
                    'selectionSet' => new SelectionSet([
                        'selections' => [
                            new Field([
                                'name' => new Name(['value' => 'whoever123is']),
                                'arguments' => [],
                                'directives' => [],
                                'selectionSet' => new SelectionSet([
                                    'selections' => [
                                        new Field([
                                            'name' => new Name(['value' => 'id']),
                                            'arguments' => [],
                                            'directives' => []
                                        ])
                                    ]
                                ])
                            ])
                        ]
                    ])
                ))
            ]
        ));*/

        // $this->assertEquals($expectedAst, $ast);
        $this->assertEquals($queryStr, Printer::doPrint($ast));

    }

    public function testPrintsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $printed = Printer::doPrint($ast);

        $expected = <<<'EOT'
query queryName($foo: ComplexType, $site: Site = MOBILE) {
  whoever123is: node(id: [123, 456]) {
    id,
    ... on User @defer {
      field2 {
        id,
        alias: field1(first: 10, after: $foo) @include(if: $foo) {
          id,
          ...frag
        }
      }
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

fragment frag on Friend {
  foo(size: $size, bar: $b, obj: {key: "value"})
}

{
  unnamed(truthy: true, falsey: false),
  query
}

EOT;
        $this->assertEquals($expected, $printed);
    }
}
