<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\SyntaxError;
use GraphQL\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it accepts option to not include source
     */
    public function testAcceptsOptionToNotIncludeSource()
    {
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

    /**
     * @it parse provides useful errors
     */
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

        $run(0, '{', "Syntax Error GraphQL (1:2) Expected Name, found EOF\n\n1: {\n    ^\n", [1], [new SourceLocation(1,2)]);
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

    /**
     * @it parse provides useful error when using source
     */
    public function testParseProvidesUsefulErrorWhenUsingSource()
    {
        try {
            Parser::parse(new Source('query', 'MyQuery.graphql'));
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            $this->assertEquals("Syntax Error MyQuery.graphql (1:6) Expected {, found EOF\n\n1: query\n        ^\n", $e->getMessage());
        }
    }

    /**
     * @it parses variable inline values
     */
    public function testParsesVariableInlineValues()
    {
        // Following line should not throw:
        Parser::parse('{ field(complex: { a: { b: [ $var ] } }) }');
    }

    /**
     * @it parses constant default values
     */
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

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentsNamedOn()
    {
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:10) Unexpected Name "on"');
        Parser::parse('fragment on on on { on }');
    }

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentSpreadOfOn()
    {
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:9) Expected Name, found }');
        Parser::parse('{ ...on }');
    }

    /**
     * @it does not allow null as value
     */
    public function testDoesNotAllowNullAsValue()
    {
        $this->setExpectedException('GraphQL\SyntaxError', 'Syntax Error GraphQL (1:39) Unexpected Name "null"');
        Parser::parse('{ fieldWithNullableStringInput(input: null) }');
    }

    /**
     * @it parses multi-byte characters
     */
    public function testParsesMultiByteCharacters()
    {
        // Note: \u0A0A could be naively interpretted as two line-feed chars.

        $char = Utils::chr(0x0A0A);
        $query = <<<HEREDOC
        # This comment has a $char multi-byte character.
        { field(arg: "Has a $char multi-byte character.") }
HEREDOC;

        $result = Parser::parse($query, ['noLocation' => true]);

        $expected = new SelectionSet([
            'selections' => [
                new Field([
                    'name' => new Name(['value' => 'field']),
                    'arguments' => [
                        new Argument([
                            'name' => new Name(['value' => 'arg']),
                            'value' => new StringValue([
                                'value' => "Has a $char multi-byte character."
                            ])
                        ])
                    ],
                    'directives' => []
                ])
            ]
        ]);

        $this->assertEquals($expected, $result->definitions[0]->selectionSet);
    }

    /**
     * @it parses kitchen sink
     */
    public function testParsesKitchenSink()
    {
        // Following should not throw:
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $result = Parser::parse($kitchenSink);
        $this->assertNotEmpty($result);
    }

    /**
     * allows non-keywords anywhere a Name is allowed
     */
    public function testAllowsNonKeywordsAnywhereANameIsAllowed()
    {
        $nonKeywords = [
            'on',
            'fragment',
            'query',
            'mutation',
            'subscription',
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

    /**
     * @it parses anonymous mutation operations
     */
    public function testParsessAnonymousMutationOperations()
    {
        // Should not throw:
        Parser::parse('
          mutation {
            mutationField
          }
        ');
    }

    /**
     * @it parses anonymous subscription operations
     */
    public function testParsesAnonymousSubscriptionOperations()
    {
        // Should not throw:
        Parser::parse('
          subscription {
            subscriptionField
          }
        ');
    }

    /**
     * @it parses named mutation operations
     */
    public function testParsesNamedMutationOperations()
    {
        // Should not throw:
        Parser::parse('
          mutation Foo {
            mutationField
          }
        ');
    }

    /**
     * @it parses named subscription operations
     */
    public function testParsesNamedSubscriptionOperations()
    {
        Parser::parse('
          subscription Foo {
            subscriptionField
          }
        ');
    }

    /**
     * @it parse creates ast
     */
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
