<?php
namespace GraphQL\Tests\Language;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it asserts that a source to parse was provided
     */
    public function testAssertsThatASourceToParseWasProvided()
    {
        try {
            Parser::parse(null);
            $this->fail('Expected exception was not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals('GraphQL query body is expected to be string, but got NULL', $e->getMessage());
        }

        try {
            Parser::parse(['a' => 'b']);
            $this->fail('Expected exception was not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals('GraphQL query body is expected to be string, but got array', $e->getMessage());
        }

        try {
            Parser::parse(new \stdClass());
            $this->fail('Expected exception was not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals('GraphQL query body is expected to be string, but got stdClass', $e->getMessage());
        }
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
            Parser::parse(new Source('query', 'MyQuery.graphql'));
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
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:10) Unexpected Name "on"');
        Parser::parse('fragment on on on { on }');
    }

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentSpreadOfOn()
    {
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:9) Expected Name, found }');
        Parser::parse('{ ...on }');
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

        $expected = new SelectionSetNode([
            'selections' => new NodeList([
                new FieldNode([
                    'name' => new NameNode(['value' => 'field']),
                    'arguments' => new NodeList([
                        new ArgumentNode([
                            'name' => new NameNode(['value' => 'arg']),
                            'value' => new StringValueNode([
                                'value' => "Has a $char multi-byte character."
                            ])
                        ])
                    ]),
                    'directives' => new NodeList([])
                ])
            ])
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
        $result = Parser::parse($source);

        $loc = function($start, $end) use ($source) {
            return [
                'start' => $start,
                'end' => $end
            ];
        };

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'loc' => $loc(0, 41),
            'definitions' => [
                [
                    'kind' => NodeKind::OPERATION_DEFINITION,
                    'loc' => $loc(0, 40),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => null,
                    'directives' => [],
                    'selectionSet' => [
                        'kind' => NodeKind::SELECTION_SET,
                        'loc' => $loc(0, 40),
                        'selections' => [
                            [
                                'kind' => NodeKind::FIELD,
                                'loc' => $loc(4, 38),
                                'alias' => null,
                                'name' => [
                                    'kind' => NodeKind::NAME,
                                    'loc' => $loc(4, 8),
                                    'value' => 'node'
                                ],
                                'arguments' => [
                                    [
                                        'kind' => NodeKind::ARGUMENT,
                                        'name' => [
                                            'kind' => NodeKind::NAME,
                                            'loc' => $loc(9, 11),
                                            'value' => 'id'
                                        ],
                                        'value' => [
                                            'kind' => NodeKind::INT,
                                            'loc' => $loc(13, 14),
                                            'value' => '4'
                                        ],
                                        'loc' => $loc(9, 14, $source)
                                    ]
                                ],
                                'directives' => [],
                                'selectionSet' => [
                                    'kind' => NodeKind::SELECTION_SET,
                                    'loc' => $loc(16, 38),
                                    'selections' => [
                                        [
                                            'kind' => NodeKind::FIELD,
                                            'loc' => $loc(22, 24),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeKind::NAME,
                                                'loc' => $loc(22, 24),
                                                'value' => 'id'
                                            ],
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ],
                                        [
                                            'kind' => NodeKind::FIELD,
                                            'loc' => $loc(30, 34),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeKind::NAME,
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
        $result = Parser::parse($source, ['noLocation' => true]);

        $this->assertEquals(null, $result->loc);
    }

    /**
     * @it contains location information that only stringifys start/end
     */
    public function testConvertToArray()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        $this->assertEquals(['start' => 0, 'end' => '6'], TestUtils::locationToArray($result->loc));
    }

    /**
     * @it contains references to source
     */
    public function testContainsReferencesToSource()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        $this->assertEquals($source, $result->loc->source);
    }

    /**
     * @it contains references to start and end tokens
     */
    public function testContainsReferencesToStartAndEndTokens()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        $this->assertEquals('<SOF>', $result->loc->startToken->kind);
        $this->assertEquals('<EOF>', $result->loc->endToken->kind);
    }

    // Describe: parseValue

    /**
     * @it parses null value
     */
    public function testParsesNullValues()
    {
        $this->assertEquals([
            'kind' => NodeKind::NULL,
            'loc' => ['start' => 0, 'end' => 4]
        ], $this->nodeToArray(Parser::parseValue('null')));
    }

    /**
     * @it parses list values
     */
    public function testParsesListValues()
    {
        $this->assertEquals([
            'kind' => NodeKind::LST,
            'loc' => ['start' => 0, 'end' => 11],
            'values' => [
                [
                    'kind' => NodeKind::INT,
                    'loc' => ['start' => 1, 'end' => 4],
                    'value' => '123'
                ],
                [
                    'kind' => NodeKind::STRING,
                    'loc' => ['start' => 5, 'end' => 10],
                    'value' => 'abc'
                ]
            ]
        ], $this->nodeToArray(Parser::parseValue('[123 "abc"]')));
    }

    // Describe: parseType

    /**
     * @it parses well known types
     */
    public function testParsesWellKnownTypes()
    {
        $this->assertEquals([
            'kind' => NodeKind::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeKind::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'String'
            ]
        ], $this->nodeToArray(Parser::parseType('String')));
    }

    /**
     * @it parses custom types
     */
    public function testParsesCustomTypes()
    {
        $this->assertEquals([
            'kind' => NodeKind::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeKind::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'MyType'
            ]
        ], $this->nodeToArray(Parser::parseType('MyType')));
    }

    /**
     * @it parses list types
     */
    public function testParsesListTypes()
    {
        $this->assertEquals([
            'kind' => NodeKind::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 8],
            'type' => [
                'kind' => NodeKind::NAMED_TYPE,
                'loc' => ['start' => 1, 'end' => 7],
                'name' => [
                    'kind' => NodeKind::NAME,
                    'loc' => ['start' => 1, 'end' => 7],
                    'value' => 'MyType'
                ]
            ]
        ], $this->nodeToArray(Parser::parseType('[MyType]')));
    }

    /**
     * @it parses non-null types
     */
    public function testParsesNonNullTypes()
    {
        $this->assertEquals([
            'kind' => NodeKind::NON_NULL_TYPE,
            'loc' => ['start' => 0, 'end' => 7],
            'type' => [
                'kind' => NodeKind::NAMED_TYPE,
                'loc' => ['start' => 0, 'end' => 6],
                'name' => [
                    'kind' => NodeKind::NAME,
                    'loc' => ['start' => 0, 'end' => 6],
                    'value' => 'MyType'
                ]
            ]
        ], $this->nodeToArray(Parser::parseType('MyType!')));
    }

    /**
     * @it parses nested types
     */
    public function testParsesNestedTypes()
    {
        $this->assertEquals([
            'kind' => NodeKind::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 9],
            'type' => [
                'kind' => NodeKind::NON_NULL_TYPE,
                'loc' => ['start' => 1, 'end' => 8],
                'type' => [
                    'kind' => NodeKind::NAMED_TYPE,
                    'loc' => ['start' => 1, 'end' => 7],
                    'name' => [
                        'kind' => NodeKind::NAME,
                        'loc' => ['start' => 1, 'end' => 7],
                        'value' => 'MyType'
                    ]
                ]
            ]
        ], $this->nodeToArray(Parser::parseType('[MyType!]')));
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
