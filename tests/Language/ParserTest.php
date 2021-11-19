<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function sprintf;

class ParserTest extends TestCase
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3?: list<int>, 4?: list<SourceLocation>}>
     */
    public function parseProvidesUsefulErrors(): array
    {
        return [
            [
                '{',
                'Syntax Error: Expected Name, found <EOF>',
                "Syntax Error: Expected Name, found <EOF>\n\nGraphQL request (1:2)\n1: {\n    ^\n",
                [1],
                [
                    new SourceLocation(
                        1,
                        2
                    ),
                ],
            ],
            [
                '{ ...MissingOn }
fragment MissingOn Type
',
                'Syntax Error: Expected "on", found Name "Type"',
                "Syntax Error: Expected \"on\", found Name \"Type\"\n\nGraphQL request (2:20)\n1: { ...MissingOn }\n2: fragment MissingOn Type\n                      ^\n3: \n",
            ],
            [
                '{ field: {} }',
                'Syntax Error: Expected Name, found {',
                "Syntax Error: Expected Name, found {\n\nGraphQL request (1:10)\n1: { field: {} }\n            ^\n",
            ],
            [
                'notanoperation Foo { field }',
                'Syntax Error: Unexpected Name "notanoperation"',
                "Syntax Error: Unexpected Name \"notanoperation\"\n\nGraphQL request (1:1)\n1: notanoperation Foo { field }\n   ^\n",
            ],
            [
                '...',
                'Syntax Error: Unexpected ...',
                "Syntax Error: Unexpected ...\n\nGraphQL request (1:1)\n1: ...\n   ^\n",
            ],
        ];
    }

    /**
     * @see          it('parse provides useful errors')
     *
     * @dataProvider parseProvidesUsefulErrors
     */
    public function testParseProvidesUsefulErrors(
        $str,
        $expectedMessage,
        $stringRepresentation,
        $expectedPositions = null,
        $expectedLocations = null
    ): void {
        try {
            Parser::parse($str);
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            self::assertEquals($expectedMessage, $e->getMessage());
            self::assertEquals($stringRepresentation, (string) $e);

            if ($expectedPositions) {
                self::assertEquals($expectedPositions, $e->getPositions());
            }

            if ($expectedLocations) {
                self::assertEquals($expectedLocations, $e->getLocations());
            }
        }
    }

    /**
     * @see it('parse provides useful error when using source')
     */
    public function testParseProvidesUsefulErrorWhenUsingSource(): void
    {
        try {
            Parser::parse(new Source('query', 'MyQuery.graphql'));
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            self::assertEquals(
                "Syntax Error: Expected {, found <EOF>\n\nMyQuery.graphql (1:6)\n1: query\n        ^\n",
                (string) $error
            );
        }
    }

    /**
     * @see it('parses variable inline values')
     */
    public function testParsesVariableInlineValues(): void
    {
        $this->expectNotToPerformAssertions();
        // Following line should not throw:
        Parser::parse('{ field(complex: { a: { b: [ $var ] } }) }');
    }

    /**
     * @see it('parses constant default values')
     */
    public function testParsesConstantDefaultValues(): void
    {
        $this->expectSyntaxError(
            'query Foo($x: Complex = { a: { b: [ $var ] } }) { field }',
            'Unexpected $',
            $this->loc(1, 37)
        );
    }

    /**
     * @see it('parses variable definition directives')
     */
    public function testParsesVariableDefinitionDirectives(): void
    {
        $this->expectNotToPerformAssertions();
        Parser::parse('query Foo($x: Boolean = false @bar) { field }');
    }

    private function expectSyntaxError($text, $message, $location): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        try {
            Parser::parse($text);
        } catch (SyntaxError $error) {
            self::assertEquals([$location], $error->getLocations());

            throw $error;
        }
    }

    private function loc($line, $column)
    {
        return new SourceLocation($line, $column);
    }

    /**
     * @see it('does not accept fragments spread of "on"')
     */
    public function testDoesNotAcceptFragmentsNamedOn(): void
    {
        $this->expectSyntaxError(
            'fragment on on on { on }',
            'Unexpected Name "on"',
            $this->loc(1, 10)
        );
    }

    /**
     * @see it('does not accept fragments spread of "on"')
     */
    public function testDoesNotAcceptFragmentSpreadOfOn(): void
    {
        $this->expectSyntaxError(
            '{ ...on }',
            'Expected Name, found }',
            $this->loc(1, 9)
        );
    }

    /**
     * @see it('parses multi-byte characters')
     */
    public function testParsesMultiByteCharacters(): void
    {
        // Note: \u0A0A could be naively interpreted as two line-feed chars.

        $char  = Utils::chr(0x0A0A);
        $query = <<<HEREDOC
        # This comment has a $char multi-byte character.
        { field(arg: "Has a $char multi-byte character.") }
HEREDOC;

        $result = Parser::parse($query, ['noLocation' => true]);

        $expected = new SelectionSetNode([
            'selections' => new NodeList([
                new FieldNode([
                    'name'       => new NameNode(['value' => 'field']),
                    'arguments'  => new NodeList([
                        new ArgumentNode([
                            'name'  => new NameNode(['value' => 'arg']),
                            'value' => new StringValueNode(
                                ['value' => sprintf('Has a %s multi-byte character.', $char)]
                            ),
                        ]),
                    ]),
                    'directives' => new NodeList([]),
                ]),
            ]),
        ]);

        /** @var OperationDefinitionNode $operationDefinition */
        $operationDefinition = $result->definitions[0];
        self::assertEquals($expected, $operationDefinition->selectionSet);
    }

    /**
     * @see it('parses kitchen sink')
     */
    public function testParsesKitchenSink(): void
    {
        // Following should not throw:
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $result      = Parser::parse($kitchenSink);
        self::assertNotEmpty($result);
    }

    /**
     * allows non-keywords anywhere a Name is allowed
     */
    public function testAllowsNonKeywordsAnywhereANameIsAllowed(): void
    {
        $nonKeywords = [
            'on',
            'fragment',
            'query',
            'mutation',
            'subscription',
            'true',
            'false',
        ];
        foreach ($nonKeywords as $keyword) {
            $fragmentName = $keyword;
            if ($keyword === 'on') {
                $fragmentName = 'a';
            }

            // Expected not to throw:
            $result = Parser::parse(<<<GRAPHQL
query $keyword {
... $fragmentName
... on $keyword { field }
}
fragment $fragmentName on Type {
$keyword($keyword: \$$keyword) @$keyword($keyword: $keyword)
}
fragment $fragmentName on Type {
  $keyword($keyword: \$$keyword) @$keyword($keyword: $keyword)	
}
GRAPHQL
            );
            self::assertNotEmpty($result);
        }
    }

    /**
     * @see it('parses anonymous mutation operations')
     */
    public function testParsessAnonymousMutationOperations(): void
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
    public function testParsesAnonymousSubscriptionOperations(): void
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
    public function testParsesNamedMutationOperations(): void
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
    public function testParsesNamedSubscriptionOperations(): void
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
    public function testParseCreatesAst(): void
    {
        $source = new Source('{
  node(id: 4) {
    id,
    name
  }
}
');
        $result = Parser::parse($source);

        $loc = static function (int $start, int $end): array {
            return [
                'start' => $start,
                'end'   => $end,
            ];
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'loc'         => $loc(0, 41),
            'definitions' => [
                [
                    'kind'                => NodeKind::OPERATION_DEFINITION,
                    'loc'                 => $loc(0, 40),
                    'operation'           => 'query',
                    'name'                => null,
                    'variableDefinitions' => [],
                    'directives'          => [],
                    'selectionSet'        => [
                        'kind'       => NodeKind::SELECTION_SET,
                        'loc'        => $loc(0, 40),
                        'selections' => [
                            [
                                'kind'         => NodeKind::FIELD,
                                'loc'          => $loc(4, 38),
                                'alias'        => null,
                                'name'         => [
                                    'kind'  => NodeKind::NAME,
                                    'loc'   => $loc(4, 8),
                                    'value' => 'node',
                                ],
                                'arguments'    => [
                                    [
                                        'kind'  => NodeKind::ARGUMENT,
                                        'name'  => [
                                            'kind'  => NodeKind::NAME,
                                            'loc'   => $loc(9, 11),
                                            'value' => 'id',
                                        ],
                                        'value' => [
                                            'kind'  => NodeKind::INT,
                                            'loc'   => $loc(13, 14),
                                            'value' => '4',
                                        ],
                                        'loc'   => $loc(9, 14),
                                    ],
                                ],
                                'directives'   => [],
                                'selectionSet' => [
                                    'kind'       => NodeKind::SELECTION_SET,
                                    'loc'        => $loc(16, 38),
                                    'selections' => [
                                        [
                                            'kind'         => NodeKind::FIELD,
                                            'loc'          => $loc(22, 24),
                                            'alias'        => null,
                                            'name'         => [
                                                'kind'  => NodeKind::NAME,
                                                'loc'   => $loc(22, 24),
                                                'value' => 'id',
                                            ],
                                            'arguments'    => [],
                                            'directives'   => [],
                                            'selectionSet' => null,
                                        ],
                                        [
                                            'kind'         => NodeKind::FIELD,
                                            'loc'          => $loc(30, 34),
                                            'alias'        => null,
                                            'name'         => [
                                                'kind'  => NodeKind::NAME,
                                                'loc'   => $loc(30, 34),
                                                'value' => 'name',
                                            ],
                                            'arguments'    => [],
                                            'directives'   => [],
                                            'selectionSet' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, self::nodeToArray($result));
    }

    /**
     * @return mixed[]
     */
    public static function nodeToArray(Node $node): array
    {
        return TestUtils::nodeToArray($node);
    }

    /**
     * @see it('creates ast from nameless query without variables')
     */
    public function testParseCreatesAstFromNamelessQueryWithoutVariables(): void
    {
        $source = new Source('query {
  node {
    id
  }
}
');
        $result = Parser::parse($source);

        $loc = static function ($start, $end): array {
            return [
                'start' => $start,
                'end'   => $end,
            ];
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'loc'         => $loc(0, 30),
            'definitions' => [
                [
                    'kind'                => NodeKind::OPERATION_DEFINITION,
                    'loc'                 => $loc(0, 29),
                    'operation'           => 'query',
                    'name'                => null,
                    'variableDefinitions' => [],
                    'directives'          => [],
                    'selectionSet'        => [
                        'kind'       => NodeKind::SELECTION_SET,
                        'loc'        => $loc(6, 29),
                        'selections' => [
                            [
                                'kind'         => NodeKind::FIELD,
                                'loc'          => $loc(10, 27),
                                'alias'        => null,
                                'name'         => [
                                    'kind'  => NodeKind::NAME,
                                    'loc'   => $loc(10, 14),
                                    'value' => 'node',
                                ],
                                'arguments'    => [],
                                'directives'   => [],
                                'selectionSet' => [
                                    'kind'       => NodeKind::SELECTION_SET,
                                    'loc'        => $loc(15, 27),
                                    'selections' => [
                                        [
                                            'kind'         => NodeKind::FIELD,
                                            'loc'          => $loc(21, 23),
                                            'alias'        => null,
                                            'name'         => [
                                                'kind'  => NodeKind::NAME,
                                                'loc'   => $loc(21, 23),
                                                'value' => 'id',
                                            ],
                                            'arguments'    => [],
                                            'directives'   => [],
                                            'selectionSet' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, self::nodeToArray($result));
    }

    /**
     * @see it('allows parsing without source location information')
     */
    public function testAllowsParsingWithoutSourceLocationInformation(): void
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source, ['noLocation' => true]);

        self::assertEquals(null, $result->loc);
    }

    /**
     * @see it('Experimental: allows parsing fragment defined variables')
     */
    public function testExperimentalAllowsParsingFragmentDefinedVariables(): void
    {
        $source = new Source('fragment a($v: Boolean = false) on t { f(v: $v) }');
        // not throw
        Parser::parse($source, ['experimentalFragmentVariables' => true]);

        $this->expectException(SyntaxError::class);
        Parser::parse($source);
    }

    // Describe: parseValue

    /**
     * @see it('contains location information that only stringifys start/end')
     */
    public function testContainsLocationInformationThatOnlyStringifysStartEnd(): void
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        self::assertEquals(['start' => 0, 'end' => '6'], TestUtils::locationToArray($result->loc));
    }

    /**
     * @see it('contains references to source')
     */
    public function testContainsReferencesToSource(): void
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        self::assertEquals($source, $result->loc->source);
    }

    // Describe: parseType

    /**
     * @see it('contains references to start and end tokens')
     */
    public function testContainsReferencesToStartAndEndTokens(): void
    {
        $source = new Source('{ id }');
        $result = Parser::parse($source);
        self::assertEquals('<SOF>', $result->loc->startToken->kind);
        self::assertEquals('<EOF>', $result->loc->endToken->kind);
    }

    /**
     * @see it('parses null value')
     */
    public function testParsesNullValues(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::NULL,
                'loc'  => ['start' => 0, 'end' => 4],
            ],
            self::nodeToArray(Parser::parseValue('null'))
        );
    }

    /**
     * @see it('parses list values')
     */
    public function testParsesListValues(): void
    {
        self::assertEquals(
            [
                'kind'   => NodeKind::LST,
                'loc'    => ['start' => 0, 'end' => 11],
                'values' => [
                    [
                        'kind'  => NodeKind::INT,
                        'loc'   => ['start' => 1, 'end' => 4],
                        'value' => '123',
                    ],
                    [
                        'kind'  => NodeKind::STRING,
                        'loc'   => ['start' => 5, 'end' => 10],
                        'value' => 'abc',
                        'block' => false,
                    ],
                ],
            ],
            self::nodeToArray(Parser::parseValue('[123 "abc"]'))
        );
    }

    /**
     * @see it('parses well known types')
     */
    public function testParsesWellKnownTypes(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::NAMED_TYPE,
                'loc'  => ['start' => 0, 'end' => 6],
                'name' => [
                    'kind'  => NodeKind::NAME,
                    'loc'   => ['start' => 0, 'end' => 6],
                    'value' => 'String',
                ],
            ],
            self::nodeToArray(Parser::parseType('String'))
        );
    }

    /**
     * @see it('parses custom types')
     */
    public function testParsesCustomTypes(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::NAMED_TYPE,
                'loc'  => ['start' => 0, 'end' => 6],
                'name' => [
                    'kind'  => NodeKind::NAME,
                    'loc'   => ['start' => 0, 'end' => 6],
                    'value' => 'MyType',
                ],
            ],
            self::nodeToArray(Parser::parseType('MyType'))
        );
    }

    /**
     * @see it('parses list types')
     */
    public function testParsesListTypes(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::LIST_TYPE,
                'loc'  => ['start' => 0, 'end' => 8],
                'type' => [
                    'kind' => NodeKind::NAMED_TYPE,
                    'loc'  => ['start' => 1, 'end' => 7],
                    'name' => [
                        'kind'  => NodeKind::NAME,
                        'loc'   => ['start' => 1, 'end' => 7],
                        'value' => 'MyType',
                    ],
                ],
            ],
            self::nodeToArray(Parser::parseType('[MyType]'))
        );
    }

    /**
     * @see it('parses non-null types')
     */
    public function testParsesNonNullTypes(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::NON_NULL_TYPE,
                'loc'  => ['start' => 0, 'end' => 7],
                'type' => [
                    'kind' => NodeKind::NAMED_TYPE,
                    'loc'  => ['start' => 0, 'end' => 6],
                    'name' => [
                        'kind'  => NodeKind::NAME,
                        'loc'   => ['start' => 0, 'end' => 6],
                        'value' => 'MyType',
                    ],
                ],
            ],
            self::nodeToArray(Parser::parseType('MyType!'))
        );
    }

    /**
     * @see it('parses nested types')
     */
    public function testParsesNestedTypes(): void
    {
        self::assertEquals(
            [
                'kind' => NodeKind::LIST_TYPE,
                'loc'  => ['start' => 0, 'end' => 9],
                'type' => [
                    'kind' => NodeKind::NON_NULL_TYPE,
                    'loc'  => ['start' => 1, 'end' => 8],
                    'type' => [
                        'kind' => NodeKind::NAMED_TYPE,
                        'loc'  => ['start' => 1, 'end' => 7],
                        'name' => [
                            'kind'  => NodeKind::NAME,
                            'loc'   => ['start' => 1, 'end' => 7],
                            'value' => 'MyType',
                        ],
                    ],
                ],
            ],
            self::nodeToArray(Parser::parseType('[MyType!]'))
        );
    }

    public function testPartiallyParsesSource(): void
    {
        self::assertInstanceOf(
            NameNode::class,
            Parser::name('Foo')
        );

        self::assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            Parser::objectTypeDefinition('type Foo { name: String }')
        );

        self::assertInstanceOf(
            VariableNode::class,
            Parser::valueLiteral('$foo')
        );

        self::assertInstanceOf(
            NodeList::class,
            Parser::argumentsDefinition('(foo: Int!)')
        );

        self::assertInstanceOf(
            NodeList::class,
            Parser::directiveLocations('| INPUT_OBJECT | OBJECT')
        );

        self::assertInstanceOf(
            NodeList::class,
            Parser::implementsInterfaces('implements Foo & Bar')
        );

        self::assertInstanceOf(
            NodeList::class,
            Parser::unionMemberTypes('= | Foo | Bar')
        );

        $this->expectException(SyntaxError::class);
        Parser::constValueLiteral('$foo');
    }
}
