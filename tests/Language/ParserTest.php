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
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testAssertsThatASourceToParseIsNotNull()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('GraphQL query body is expected to be string, but got NULL');
        Parser::parse(null);
    }

    public function testAssertsThatASourceToParseIsNotArray()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('GraphQL query body is expected to be string, but got array');
        Parser::parse(['a' => 'b']);
    }

    public function testAssertsThatASourceToParseIsNotObject()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('GraphQL query body is expected to be string, but got stdClass');
        Parser::parse(new \stdClass());
    }

    public function parseProvidesUsefulErrors()
    {
        return [
            ['{', "Syntax Error: Expected Name, found <EOF>", "Syntax Error: Expected Name, found <EOF>\n\nGraphQL request (1:2)\n1: {\n    ^\n", [1], [new SourceLocation(1, 2)]],
            ['{ ...MissingOn }
fragment MissingOn Type
', "Syntax Error: Expected \"on\", found Name \"Type\"", "Syntax Error: Expected \"on\", found Name \"Type\"\n\nGraphQL request (2:20)\n1: { ...MissingOn }\n2: fragment MissingOn Type\n                      ^\n3: \n",],
            ['{ field: {} }', "Syntax Error: Expected Name, found {", "Syntax Error: Expected Name, found {\n\nGraphQL request (1:10)\n1: { field: {} }\n            ^\n"],
            ['notanoperation Foo { field }', "Syntax Error: Unexpected Name \"notanoperation\"", "Syntax Error: Unexpected Name \"notanoperation\"\n\nGraphQL request (1:1)\n1: notanoperation Foo { field }\n   ^\n"],
            ['...', "Syntax Error: Unexpected ...", "Syntax Error: Unexpected ...\n\nGraphQL request (1:1)\n1: ...\n   ^\n"],
        ];
    }

    /**
     * @dataProvider parseProvidesUsefulErrors
     * @see it('parse provides useful errors')
     */
    public function testParseProvidesUsefulErrors($str, $expectedMessage, $stringRepresentation, $expectedPositions = null, $expectedLocations = null)
    {
        try {
            Parser::parse($str);
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            $this->assertEquals($expectedMessage, $e->getMessage());
            $this->assertEquals($stringRepresentation, (string) $e);

            if ($expectedPositions) {
                $this->assertEquals($expectedPositions, $e->getPositions());
            }

            if ($expectedLocations) {
                $this->assertEquals($expectedLocations, $e->getLocations());
            }
        }
    }

    /**
     * @see it('parse provides useful error when using source')
     */
    public function testParseProvidesUsefulErrorWhenUsingSource()
    {
        try {
            Parser::parse(new Source('query', 'MyQuery.graphql'));
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            $this->assertEquals(
                "Syntax Error: Expected {, found <EOF>\n\nMyQuery.graphql (1:6)\n1: query\n        ^\n",
                (string) $error
            );
        }
    }

    /**
     * @see it('parses variable inline values')
     */
    public function testParsesVariableInlineValues()
    {
        $this->expectNotToPerformAssertions();
        // Following line should not throw:
        Parser::parse('{ field(complex: { a: { b: [ $var ] } }) }');
    }

    /**
     * @see it('parses constant default values')
     */
    public function testParsesConstantDefaultValues()
    {
        $this->expectSyntaxError(
            'query Foo($x: Complex = { a: { b: [ $var ] } }) { field }',
            'Unexpected $',
            $this->loc(1,37)
        );
    }

    /**
     * @see it('does not accept fragments spread of "on"')
     */
    public function testDoesNotAcceptFragmentsNamedOn()
    {
        $this->expectSyntaxError(
            'fragment on on on { on }',
            'Unexpected Name "on"',
            $this->loc(1,10)
        );
    }

    /**
     * @see it('does not accept fragments spread of "on"')
     */
    public function testDoesNotAcceptFragmentSpreadOfOn()
    {
        $this->expectSyntaxError(
            '{ ...on }',
            'Expected Name, found }',
            $this->loc(1,9)
        );
    }

    /**
     * @see it('parses multi-byte characters')
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
     * @see it('parses kitchen sink')
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
     * @see it('parses anonymous mutation operations')
     */
    public function testParsessAnonymousMutationOperations()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        Parser::parse('
          mutation {
            mutationField
          }
        ');
    }

    /**
     * @see it('parses anonymous subscription operations')
     */
    public function testParsesAnonymousSubscriptionOperations()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        Parser::parse('
          subscription {
            subscriptionField
          }
        ');
    }

    /**
     * @see it('parses named mutation operations')
     */
    public function testParsesNamedMutationOperations()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        Parser::parse('
          mutation Foo {
            mutationField
          }
        ');
    }

    /**
     * @see it('parses named subscription operations')
     */
    public function testParsesNamedSubscriptionOperations()
    {
        $this->expectNotToPerformAssertions();
        Parser::parse('
          subscription Foo {
            subscriptionField
          }
        ');
    }

    /**
     * @see it('creates ast')
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
                    'variableDefinitions' => [],
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
     * @see it('creates ast from nameless query without variables')
     */
    public function testParseCreatesAstFromNamelessQueryWithoutVariables()
    {
        $source = new Source('query {
  node {
    id
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
            'loc' => $loc(0, 30),
            'definitions' => [
                [
                    'kind' => NodeKind::OPERATION_DEFINITION,
                    'loc' => $loc(0, 29),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => [],
                    'directives' => [],
                    'selectionSet' => [
                        'kind' => NodeKind::SELECTION_SET,
                        'loc' => $loc(6, 29),
                        'selections' => [
                            [
                                'kind' => NodeKind::FIELD,
                                'loc' => $loc(10, 27),
                                'alias' => null,
                                'name' => [
                                    'kind' => NodeKind::NAME,
                                    'loc' => $loc(10, 14),
                                    'value' => 'node'
                                ],
                                'arguments' => [],
                                'directives' => [],
                                'selectionSet' => [
                                    'kind' => NodeKind::SELECTION_SET,
                                    'loc' => $loc(15, 27),
                                    'selections' => [
                                        [
                                            'kind' => NodeKind::FIELD,
                                            'loc' => $loc(21, 23),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeKind::NAME,
                                                'loc' => $loc(21, 23),
                                                'value' => 'id'
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
     * @see it('allows parsing without source location information')
     */
    public function testAllowsParsingWithoutSourceLocationInformation()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source, ['noLocation' => true]);

        $this->assertEquals(null, $result->loc);
    }

    /**
     * @see it('Experimental: allows parsing fragment defined variables')
     */
    public function testExperimentalAllowsParsingFragmentDefinedVariables()
    {
        $source = new Source('fragment a($v: Boolean = false) on t { f(v: $v) }');
        // not throw
        Parser::parse($source, ['experimentalFragmentVariables' => true]);

        $this->expectException(SyntaxError::class);
        Parser::parse($source);
    }

    /**
     * @see it('contains location information that only stringifys start/end')
     */
    public function testContainsLocationInformationThatOnlyStringifysStartEnd()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        $this->assertEquals(['start' => 0, 'end' => '6'], TestUtils::locationToArray($result->loc));
    }

    /**
     * @see it('contains references to source')
     */
    public function testContainsReferencesToSource()
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        $this->assertEquals($source, $result->loc->source);
    }

    /**
     * @see it('contains references to start and end tokens')
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
     * @see it('parses null value')
     */
    public function testParsesNullValues()
    {
        $this->assertEquals([
            'kind' => NodeKind::NULL,
            'loc' => ['start' => 0, 'end' => 4]
        ], $this->nodeToArray(Parser::parseValue('null')));
    }

    /**
     * @see it('parses list values')
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
                    'value' => 'abc',
                    'block' => false
                ]
            ]
        ], $this->nodeToArray(Parser::parseValue('[123 "abc"]')));
    }

    // Describe: parseType

    /**
     * @see it('parses well known types')
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
     * @see it('parses custom types')
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
     * @see it('parses list types')
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
     * @see it('parses non-null types')
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
     * @see it('parses nested types')
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

    private function loc($line, $column)
    {
        return new SourceLocation($line, $column);
    }

    private function expectSyntaxError($text, $message, $location)
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        try {
            Parser::parse($text);
        } catch (SyntaxError $error) {
            $this->assertEquals([$location], $error->getLocations());
            throw $error;
        }
    }
}
