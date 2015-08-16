<?php
namespace GraphQL\Language;

use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\SyntaxError;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testAcceptsOptionToNotIncludeSource()
    {
        // accepts option to not include source
        $actual = Parser::parse('{ field }', ['noSource' => true]);

        $expected = new Document([
            'loc' => new Location(0, 9),
            'definitions' => [
                new OperationDefinition([
                    'loc' => new Location(0, 9),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => null,
                    'directives' => [],
                    'selectionSet' => new SelectionSet([
                        'loc' => new Location(0, 9),
                        'selections' => [
                            new Field([
                                'loc' => new Location(2, 7),
                                'alias' => null,
                                'name' => new Name([
                                    'loc' => new Location(2, 7),
                                    'value' => 'field'
                                ]),
                                'arguments' => [],
                                'directives' => [],
                                'selectionSet' => null
                            ])
                        ]
                    ])
                ])
            ]
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testParseProvidesUsefulErrors()
    {
        $run = function($num, $str, $expectedMessage, $expectedPositions = null, $expectedLocations = null) {
            try {
                Parser::parse($str);
                $this->fail('Expected exception not thrown in example: ' . $num);
            } catch (SyntaxError $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Test case $num failed");

                if ($expectedPositions) {
                    $this->assertEquals($expectedPositions, $e->getPositions());
                }
                if ($expectedLocations) {
                    $this->assertEquals($expectedLocations, $e->getLocations());
                }
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
        $run(5, '{', "Syntax Error GraphQL (1:2) Expected Name, found EOF\n\n1: {\n    ^\n", [1], [new SourceLocation(1,2)]);
    }

    public function testParseProvidesUsefulErrorWhenUsingSource()
    {
        try {
            Parser::parse(new Source('query', 'MyQuery.graphql'));
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
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
        } catch (SyntaxError $e) {
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
        } catch (SyntaxError $e) {
            $this->assertEquals(
                "Syntax Error GraphQL (1:22) Duplicate input object field a.\n\n1: { field(arg: { a: 1, a: 2 }) }\n                        ^\n",
                $e->getMessage()
            );
        }
    }

    public function testDoesNotAcceptFragmentsNamedOn()
    {
        // does not accept fragments named "on"
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:10) Unexpected Name "on"');
        Parser::parse('fragment on on on { on }');
    }

    public function testDoesNotAcceptFragmentSpreadOfOn()
    {
        // does not accept fragments spread of "on"
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:9) Expected Name, found }');
        Parser::parse('{ ...on }');
    }

    public function testDoesNotAllowNullAsValue()
    {
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:39) Unexpected Name "null"');
        Parser::parse('{ fieldWithNullableStringInput(input: null) }');
    }

    public function testParsesKitchenSink()
    {
        // Following should not throw:
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $result = Parser::parse($kitchenSink);
        $this->assertNotEmpty($result);
    }

    public function testAllowsNonKeywordsAnywhereANameIsAllowed()
    {
        // allows non-keywords anywhere a Name is allowed
        $nonKeywords = [
            'on',
            'fragment',
            'query',
            'mutation',
            'true',
            'false'
        ];
        foreach ($nonKeywords as $keyword) {
            $fragmentName = $keyword;
            if ($keyword === 'on') {
                $fragmentName = 'a';
            }

            // Expected not to throw:
            $result = Parser::parse("query $keyword {
  ... $fragmentName
  ... on $keyword { field }
}
fragment $fragmentName on Type {
  $keyword($keyword: \$$keyword) @$keyword($keyword: $keyword)
}
");
            $this->assertNotEmpty($result);
        }
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
