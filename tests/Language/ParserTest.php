<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParseProvidesUsefulErrors()
    {
        $run = function($num, $str, $expectedMessage) {
            try {
                Parser::parse($str);
                $this->fail('Expected exception not thrown in example: ' . $num);
            } catch (Exception $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Test case $num failed");
            }
        };

        $run(1,
'{ ...MissingOn }
fragment MissingOn Type
',
"Syntax Error GraphQL (2:20) Expected \"on\", found Name \"Type\"\n\n1: { ...MissingOn }\n2: fragment MissingOn Type\n                      ^\n3: \n"
);

        $run(2, '{ field: {} }', "Syntax Error GraphQL (1:10) Expected Name, found {\n\n1: { field: {} }\n            ^\n");
        $run(3, 'notanoperation Foo { field }', "Syntax Error GraphQL (1:1) Unexpected Name \"notanoperation\"\n\n1: notanoperation Foo { field }\n   ^\n");
        $run(4, '...', "Syntax Error GraphQL (1:1) Unexpected ...\n\n1: ...\n   ^\n");
    }

    public function testParseProvidesUsefulErrorWhenUsingSource()
    {
        try {
            $this->assertEquals(Parser::parse(new Source('query', 'MyQuery.graphql')));
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertEquals("Syntax Error MyQuery.graphql (1:6) Expected Name, found EOF\n\n1: query\n        ^\n", $e->getMessage());
        }
    }

    public function testParsesVariableInlineValues()
    {
        // Following line should not throw:
        Parser::parse('{ field(complex: { a: { b: [ $var ] } }) }');
    }

    public function testParsesConstantDefaultValues()
    {
        try {
            Parser::parse('query Foo($x: Complex = { a: { b: [ $var ] } }) { field }');
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertEquals(
                "Syntax Error GraphQL (1:37) Unexpected $\n\n" . '1: query Foo($x: Complex = { a: { b: [ $var ] } }) { field }' . "\n                                       ^\n",
                $e->getMessage()
            );
        }
    }

    public function testDuplicateKeysInInputObjectIsSyntaxError()
    {
        try {
            Parser::parse('{ field(arg: { a: 1, a: 2 }) }');
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertEquals(
                "Syntax Error GraphQL (1:22) Duplicate input object field a.\n\n1: { field(arg: { a: 1, a: 2 }) }\n                        ^\n",
                $e->getMessage()
            );
        }
    }

    public function testParsesKitchenSink()
    {
        // Following should not throw:
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        Parser::parse($kitchenSink);
    }

    public function testParseCreatesAst()
    {
        $source = new Source('{
  node(id: 4) {
    id,
    name
  }
}
');
        $result = Parser::parse($source);

        $expected = new Document(array(
            'loc' => new Location(0, 41, $source),
            'definitions' => array(
                new OperationDefinition(array(
                    'loc' => new Location(0, 40, $source),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => null,
                    'directives' => array(),
                    'selectionSet' => new SelectionSet(array(
                        'loc' => new Location(0, 40, $source),
                        'selections' => array(
                            new Field(array(
                                'loc' => new Location(4, 38, $source),
                                'alias' => null,
                                'name' => new Name(array(
                                    'loc' => new Location(4, 8, $source),
                                    'value' => 'node'
                                )),
                                'arguments' => array(
                                    new Argument(array(
                                        'name' => new Name(array(
                                            'loc' => new Location(9, 11, $source),
                                            'value' => 'id'
                                        )),
                                        'value' => new IntValue(array(
                                            'loc' => new Location(13, 14, $source),
                                            'value' => '4'
                                        )),
                                        'loc' => new Location(9, 14, $source)
                                    ))
                                ),
                                'directives' => [],
                                'selectionSet' => new SelectionSet(array(
                                    'loc' => new Location(16, 38, $source),
                                    'selections' => array(
                                        new Field(array(
                                            'loc' => new Location(22, 24, $source),
                                            'alias' => null,
                                            'name' => new Name(array(
                                                'loc' => new Location(22, 24, $source),
                                                'value' => 'id'
                                            )),
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        )),
                                        new Field(array(
                                            'loc' => new Location(30, 34, $source),
                                            'alias' => null,
                                            'name' => new Name(array(
                                                'loc' => new Location(30, 34, $source),
                                                'value' => 'name'
                                            )),
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ))
                                    )
                                ))
                            ))
                        )
                    ))
                ))
            )
        ));

        $this->assertEquals($expected, $result);
    }
}
