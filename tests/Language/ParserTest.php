<?php

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it parse provides useful errors
     */
    public function testParseProvidesUsefulErrors()
    {
        $run = function($num, $str, $expectedMessage, $expectedPositions = null, $expectedLocations = null) {
            try {
                $parser = new Parser(new Lexer());

                $parser->parse($str);
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

        $run(0, '{', "Syntax Error GraphQL (1:2) Expected Name, found <EOF>\n\n1: {\n    ^\n", [1], [new SourceLocation(1,2)]);
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
            $parser = new Parser(new Lexer());

            $parser->parse(new Source('query', 'MyQuery.graphql'));
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            $this->assertEquals("Syntax Error MyQuery.graphql (1:6) Expected {, found <EOF>\n\n1: query\n        ^\n", $e->getMessage());
        }
    }

    /**
     * @it parses variable inline values
     */
    public function testParsesVariableInlineValues()
    {
        // Following line should not throw:
        $parser = new Parser(new Lexer());

        $parser->parse('{ field(complex: { a: { b: [ $var ] } }) }');
    }

    /**
     * @it parses constant default values
     */
    public function testParsesConstantDefaultValues()
    {
        try {
            $parser = new Parser(new Lexer());
            $parser->parse('query Foo($x: Complex = { a: { b: [ $var ] } }) { field }');
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
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:10) Unexpected Name "on"');
        $parser = new Parser(new Lexer());
        $parser->parse('fragment on on on { on }');
    }

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentSpreadOfOn()
    {
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:9) Expected Name, found }');
        $parser = new Parser(new Lexer());
        $parser->parse('{ ...on }');
    }

    /**
     * @it does not allow null as value
     */
    public function testDoesNotAllowNullAsValue()
    {
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:39) Unexpected Name "null"');
        $parser = new Parser(new Lexer());
        $parser->parse('{ fieldWithNullableStringInput(input: null) }');
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

        $parser = new Parser(new Lexer(), ['noLocation' => true]);
        $result = $parser->parse($query);

        $expected = new SelectionSet(
            [
                new Field(
                    new Name('field'),
                    null,
                    [
                        new Argument(
                            new Name('arg'),
                            new StringValue(
                                "Has a $char multi-byte character."
                            )
                        )
                    ]
                )
            ]
        );

        $this->assertEquals($expected, $result->getDefinitions()[0]->getSelectionSet());
    }

    /**
     * @it parses kitchen sink
     */
    public function testParsesKitchenSink()
    {
        // Following should not throw:
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parser = new Parser(new Lexer());
        $result = $parser->parse($kitchenSink);
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
            $parser = new Parser(new Lexer());
            $result = $parser->parse("query $keyword {
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
        $parser = new Parser(new Lexer());
        $parser->parse('
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
        $parser = new Parser(new Lexer());
        $parser->parse('
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
        $parser = new Parser(new Lexer());
        $parser->parse('
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
        $parser = new Parser(new Lexer());
        $parser->parse('
          subscription Foo {
            subscriptionField
          }
        ');
    }

    /**
     * @it creates ast
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
        $parser = new Parser(new Lexer());
        $result = $parser->parse($source);

        $loc = function($start, $end) use ($source) {
            return [
                'start' => $start,
                'end' => $end
            ];
        };

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'loc' => $loc(0, 41),
            'definitions' => [
                [
                    'kind' => NodeType::OPERATION_DEFINITION,
                    'loc' => $loc(0, 40),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => null,
                    'directives' => [],
                    'selectionSet' => [
                        'kind' => NodeType::SELECTION_SET,
                        'loc' => $loc(0, 40),
                        'selections' => [
                            [
                                'kind' => NodeType::FIELD,
                                'loc' => $loc(4, 38),
                                'alias' => null,
                                'name' => [
                                    'kind' => NodeType::NAME,
                                    'loc' => $loc(4, 8),
                                    'value' => 'node'
                                ],
                                'arguments' => [
                                    [
                                        'kind' => NodeType::ARGUMENT,
                                        'name' => [
                                            'kind' => NodeType::NAME,
                                            'loc' => $loc(9, 11),
                                            'value' => 'id'
                                        ],
                                        'value' => [
                                            'kind' => NodeType::INT,
                                            'loc' => $loc(13, 14),
                                            'value' => '4'
                                        ],
                                        'loc' => $loc(9, 14, $source)
                                    ]
                                ],
                                'directives' => [],
                                'selectionSet' => [
                                    'kind' => NodeType::SELECTION_SET,
                                    'loc' => $loc(16, 38),
                                    'selections' => [
                                        [
                                            'kind' => NodeType::FIELD,
                                            'loc' => $loc(22, 24),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeType::NAME,
                                                'loc' => $loc(22, 24),
                                                'value' => 'id'
                                            ],
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ],
                                        [
                                            'kind' => NodeType::FIELD,
                                            'loc' => $loc(30, 34),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeType::NAME,
                                                'loc' => $loc(30, 34),
                                                'value' => 'name'
                                            ],
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $this->nodeToArray($result));
    }

    /**
     * @it allows parsing without source location information
     */
    public function testAllowsParsingWithoutSourceLocationInformation()
    {
        $source = new Source('{ id }');
        $parser = new Parser(new Lexer(), ['noLocation' => true]);
        $result = $parser->parse($source);

        $this->assertEquals(null, $result->getLoc());
    }

    /**
     * @it contains location information that only stringifys start/end
     */
    public function testConvertToArray()
    {
        $source = new Source('{ id }');
        $parser = new Parser(new Lexer());
        $result = $parser->parse($source);
        $this->assertEquals(['start' => 0, 'end' => '6'], TestUtils::locationToArray($result->getLoc()));
    }

    /**
     * @it contains references to source
     */
    public function testContainsReferencesToSource()
    {
        $source = new Source('{ id }');
        $parser = new Parser(new Lexer());
        $result = $parser->parse($source);
        $this->assertEquals($source, $result->getLoc()->source);
    }

    /**
     * @it contains references to start and end tokens
     */
    public function testContainsReferencesToStartAndEndTokens()
    {
        $source = new Source('{ id }');
        $parser = new Parser(new Lexer());
        $result = $parser->parse($source);
        $this->assertEquals('<SOF>', $result->getLoc()->startToken->getKind());
        $this->assertEquals('<EOF>', $result->getLoc()->endToken->getKind());
    }

    // Describe: parseValue

    /**
     * @it parses list values
     */
    public function testParsesListValues()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::LST,
            'loc' => ['start' => 0, 'end' => 11],
            'values' => [
                [
                    'kind' => NodeType::INT,
                    'loc' => ['start' => 1, 'end' => 4],
                    'value' => '123'
                ],
                [
                    'kind' => NodeType::STRING,
                    'loc' => ['start' => 5, 'end' => 10],
                    'value' => 'abc'
                ]
            ]
        ], $this->nodeToArray($parser->parseValue('[123 "abc"]')));
    }

    // Describe: parseType

    /**
     * @it parses well known types
     */
    public function testParsesWellKnownTypes()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeType::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'String'
            ]
        ], $this->nodeToArray($parser->parseType('String')));
    }

    /**
     * @it parses custom types
     */
    public function testParsesCustomTypes()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeType::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'MyType'
            ]
        ], $this->nodeToArray($parser->parseType('MyType')));
    }

    /**
     * @it parses list types
     */
    public function testParsesListTypes()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 8],
            'type' => [
                'kind' => NodeType::NAMED_TYPE,
                'loc' => ['start' => 1, 'end' => 7],
                'name' => [
                    'kind' => NodeType::NAME,
                    'loc' => ['start' => 1, 'end' => 7],
                    'value' => 'MyType'
                ]
            ]
        ], $this->nodeToArray($parser->parseType('[MyType]')));
    }

    /**
     * @it parses non-null types
     */
    public function testParsesNonNullTypes()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::NON_NULL_TYPE,
            'loc' => ['start' => 0, 'end' => 7],
            'type' => [
                'kind' => NodeType::NAMED_TYPE,
                'loc' => ['start' => 0, 'end' => 6],
                'name' => [
                    'kind' => NodeType::NAME,
                    'loc' => ['start' => 0, 'end' => 6],
                    'value' => 'MyType'
                ]
            ]
        ], $this->nodeToArray($parser->parseType('MyType!')));
    }

    /**
     * @it parses nested types
     */
    public function testParsesNestedTypes()
    {
        $parser = new Parser(new Lexer());

        $this->assertEquals([
            'kind' => NodeType::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 9],
            'type' => [
                'kind' => NodeType::NON_NULL_TYPE,
                'loc' => ['start' => 1, 'end' => 8],
                'type' => [
                    'kind' => NodeType::NAMED_TYPE,
                    'loc' => ['start' => 1, 'end' => 7],
                    'name' => [
                        'kind' => NodeType::NAME,
                        'loc' => ['start' => 1, 'end' => 7],
                        'value' => 'MyType'
                    ]
                ]
            ]
        ], $this->nodeToArray($parser->parseType('[MyType!]')));
    }

    /**
     * @param Node $node
     * @return array
     */
    public static function nodeToArray(Node $node)
    {
        return TestUtils::nodeToArray($node);
    }
}
