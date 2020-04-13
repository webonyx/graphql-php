<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\PHPUnit\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class SchemaParserTest extends TestCase
{
    use ArraySubsetAsserts;

    // Describe: Schema Parser

    /**
     * @see it('Simple type')
     */
    public function testSimpleType() : void
    {
        $body = '
type Hello {
  world: String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(23, 29)),
                            $loc(16, 29)
                        ),
                    ],
                    'loc'         => $loc(1, 31),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 31),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    private function nameNode($name, $loc)
    {
        return [
            'kind'  => NodeKind::NAME,
            'value' => $name,
            'loc'   => $loc,
        ];
    }

    private function fieldNode($name, $type, $loc)
    {
        return $this->fieldNodeWithArgs($name, $type, [], $loc);
    }

    private function fieldNodeWithArgs($name, $type, $args, $loc)
    {
        return [
            'kind'        => NodeKind::FIELD_DEFINITION,
            'name'        => $name,
            'arguments'   => $args,
            'type'        => $type,
            'directives'  => [],
            'loc'         => $loc,
            'description' => null,
        ];
    }

    private function typeNode($name, $loc)
    {
        return [
            'kind' => NodeKind::NAMED_TYPE,
            'name' => ['kind' => NodeKind::NAME, 'value' => $name, 'loc' => $loc],
            'loc'  => $loc,
        ];
    }

    /**
     * @see it('parses type with description string')
     */
    public function testParsesTypeWithDescriptionString() : void
    {
        $body = '
"Description"
type Hello {
  world: String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(20, 25)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(30, 35)),
                            $this->typeNode('String', $loc(37, 43)),
                            $loc(30, 43)
                        ),
                    ],
                    'loc'         => $loc(1, 45),
                    'description' => [
                        'kind'  => NodeKind::STRING,
                        'value' => 'Description',
                        'loc'   => $loc(1, 14),
                        'block' => false,
                    ],
                ],
            ],
            'loc'         => $loc(0, 45),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('parses type with description multi-linestring')
     */
    public function testParsesTypeWithDescriptionMultiLineString() : void
    {
        $body = '
"""
Description
"""
# Even with comments between them
type Hello {
  world: String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(60, 65)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(70, 75)),
                            $this->typeNode('String', $loc(77, 83)),
                            $loc(70, 83)
                        ),
                    ],
                    'loc'         => $loc(1, 85),
                    'description' => [
                        'kind'  => NodeKind::STRING,
                        'value' => 'Description',
                        'loc'   => $loc(1, 20),
                        'block' => true,
                    ],
                ],
            ],
            'loc'         => $loc(0, 85),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple extension')
     */
    public function testSimpleExtension() : void
    {
        $body = '
extend type Hello {
  world: String
}
';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'       => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name'       => $this->nameNode('Hello', $loc(13, 18)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields'     => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(23, 28)),
                            $this->typeNode('String', $loc(30, 36)),
                            $loc(23, 36)
                        ),
                    ],
                    'loc'        => $loc(1, 38),
                ],
            ],
            'loc'         => $loc(0, 39),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Extension without fields')
     */
    public function testExtensionWithoutFields() : void
    {
        $body = 'extend type Hello implements Greeting';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'       => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name'       => $this->nameNode('Hello', $loc(12, 17)),
                    'interfaces' => [
                        $this->typeNode('Greeting', $loc(29, 37)),
                    ],
                    'directives' => [],
                    'fields'     => [],
                    'loc'        => $loc(0, 37),
                ],
            ],
            'loc'         => $loc(0, 37),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Extension without fields followed by extension')
     */
    public function testExtensionWithoutFieldsFollowedByExtension() : void
    {
        $body     = '
          extend type Hello implements Greeting
    
          extend type Hello implements SecondGreeting
        ';
        $doc      = Parser::parse($body);
        $expected = [
            'kind'        => 'Document',
            'definitions' => [
                [
                    'kind'       => 'ObjectTypeExtension',
                    'name'       => $this->nameNode('Hello', ['start' => 23, 'end' => 28]),
                    'interfaces' => [$this->typeNode('Greeting', ['start' => 40, 'end' => 48])],
                    'directives' => [],
                    'fields'     => [],
                    'loc'        => ['start' => 11, 'end' => 48],
                ],
                [
                    'kind'       => 'ObjectTypeExtension',
                    'name'       => $this->nameNode('Hello', ['start' => 76, 'end' => 81]),
                    'interfaces' => [$this->typeNode('SecondGreeting', ['start' => 93, 'end' => 107])],
                    'directives' => [],
                    'fields'     => [],
                    'loc'        => ['start' => 64, 'end' => 107],
                ],
            ],
            'loc'         => ['start' => 0, 'end' => 116],
        ];
        self::assertEquals($expected, $doc->toArray(true));
    }

    /**
     * @see it('Extension without anything throws')
     */
    public function testExtensionWithoutAnythingThrows() : void
    {
        $this->expectSyntaxError(
            'extend type Hello',
            'Unexpected <EOF>',
            $this->loc(1, 18)
        );
    }

    private function expectSyntaxError($text, $message, $location)
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
     * @see it('Extension do not include descriptions')
     */
    public function testExtensionDoNotIncludeDescriptions() : void
    {
        $body = '
      "Description"
      extend type Hello {
        world: String
      }';
        $this->expectSyntaxError(
            $body,
            'Unexpected Name "extend"',
            $this->loc(3, 7)
        );
    }

    /**
     * @see it('Extension do not include descriptions')
     */
    public function testExtensionDoNotIncludeDescriptions2() : void
    {
        $body = '
      extend "Description" type Hello {
        world: String
      }
}';
        $this->expectSyntaxError(
            $body,
            'Unexpected String "Description"',
            $this->loc(2, 14)
        );
    }

    /**
     * @see it('Simple non-null type')
     */
    public function testSimpleNonNullType() : void
    {
        $body = '
type Hello {
  world: String!
}';
        $doc  = Parser::parse($body);

        $loc = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            [
                                'kind' => NodeKind::NON_NULL_TYPE,
                                'type' => $this->typeNode('String', $loc(23, 29)),
                                'loc'  => $loc(23, 30),
                            ],
                            $loc(16, 30)
                        ),
                    ],
                    'loc'         => $loc(1, 32),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 32),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple type inheriting interface')
     */
    public function testSimpleTypeInheritingInterface() : void
    {
        $body = 'type Hello implements World { field: String }';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces'  => [
                        $this->typeNode('World', $loc(22, 27)),
                    ],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(30, 35)),
                            $this->typeNode('String', $loc(37, 43)),
                            $loc(30, 43)
                        ),
                    ],
                    'loc'         => $loc(0, 45),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 45),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple type inheriting multiple interfaces')
     */
    public function testSimpleTypeInheritingMultipleInterfaces() : void
    {
        $body = 'type Hello implements Wo & rld { field: String }';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces'  => [
                        $this->typeNode('Wo', $loc(22, 24)),
                        $this->typeNode('rld', $loc(27, 30)),
                    ],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(33, 38)),
                            $this->typeNode('String', $loc(40, 46)),
                            $loc(33, 46)
                        ),
                    ],
                    'loc'         => $loc(0, 48),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 48),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple type inheriting multiple interfaces with leading ampersand')
     */
    public function testSimpleTypeInheritingMultipleInterfacesWithLeadingAmpersand() : void
    {
        $body = 'type Hello implements & Wo & rld { field: String }';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => 'Document',
            'definitions' => [
                [
                    'kind'        => 'ObjectTypeDefinition',
                    'name'        => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces'  => [
                        $this->typeNode('Wo', $loc(24, 26)),
                        $this->typeNode('rld', $loc(29, 32)),
                    ],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(35, 40)),
                            $this->typeNode('String', $loc(42, 48)),
                            $loc(35, 48)
                        ),
                    ],
                    'loc'         => $loc(0, 50),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 50),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Single value enum')
     */
    public function testSingleValueEnum() : void
    {
        $body = 'enum Hello { WORLD }';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::ENUM_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(5, 10)),
                    'directives'  => [],
                    'values'      => [$this->enumValueNode('WORLD', $loc(13, 18))],
                    'loc'         => $loc(0, 20),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 20),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    private function enumValueNode($name, $loc)
    {
        return [
            'kind'        => NodeKind::ENUM_VALUE_DEFINITION,
            'name'        => $this->nameNode($name, $loc),
            'directives'  => [],
            'loc'         => $loc,
            'description' => null,
        ];
    }

    /**
     * @see it('Double value enum')
     */
    public function testDoubleValueEnum() : void
    {
        $body = 'enum Hello { WO, RLD }';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::ENUM_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(5, 10)),
                    'directives'  => [],
                    'values'      => [
                        $this->enumValueNode('WO', $loc(13, 15)),
                        $this->enumValueNode('RLD', $loc(17, 20)),
                    ],
                    'loc'         => $loc(0, 22),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 22),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple interface')
     */
    public function testSimpleInterface() : void
    {
        $body = '
interface Hello {
  world: String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::INTERFACE_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(11, 16)),
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(21, 26)),
                            $this->typeNode('String', $loc(28, 34)),
                            $loc(21, 34)
                        ),
                    ],
                    'loc'         => $loc(1, 36),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 36),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple field with arg')
     */
    public function testSimpleFieldWithArg() : void
    {
        $body = '
type Hello {
  world(flag: Boolean): String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(38, 44)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('flag', $loc(22, 26)),
                                    $this->typeNode('Boolean', $loc(28, 35)),
                                    null,
                                    $loc(22, 35)
                                ),
                            ],
                            $loc(16, 44)
                        ),
                    ],
                    'loc'         => $loc(1, 46),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 46),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    private function inputValueNode($name, $type, $defaultValue, $loc)
    {
        return [
            'kind'         => NodeKind::INPUT_VALUE_DEFINITION,
            'name'         => $name,
            'type'         => $type,
            'defaultValue' => $defaultValue,
            'directives'   => [],
            'loc'          => $loc,
            'description'  => null,
        ];
    }

    /**
     * @see it('Simple field with arg with default value')
     */
    public function testSimpleFieldWithArgWithDefaultValue() : void
    {
        $body = '
type Hello {
  world(flag: Boolean = true): String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(45, 51)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('flag', $loc(22, 26)),
                                    $this->typeNode('Boolean', $loc(28, 35)),
                                    ['kind' => NodeKind::BOOLEAN, 'value' => true, 'loc' => $loc(38, 42)],
                                    $loc(22, 42)
                                ),
                            ],
                            $loc(16, 51)
                        ),
                    ],
                    'loc'         => $loc(1, 53),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 53),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple field with list arg')
     */
    public function testSimpleFieldWithListArg() : void
    {
        $body = '
type Hello {
  world(things: [String]): String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(41, 47)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('things', $loc(22, 28)),
                                    [
                                        'kind'   => NodeKind::LIST_TYPE,
                                        'type'   => $this->typeNode(
                                            'String',
                                            $loc(31, 37)
                                        ), 'loc' => $loc(30, 38),
                                    ],
                                    null,
                                    $loc(22, 38)
                                ),
                            ],
                            $loc(16, 47)
                        ),
                    ],
                    'loc'         => $loc(1, 49),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 49),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple field with two args')
     */
    public function testSimpleFieldWithTwoArgs() : void
    {
        $body = '
type Hello {
  world(argOne: Boolean, argTwo: Int): String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces'  => [],
                    'directives'  => [],
                    'fields'      => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(53, 59)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('argOne', $loc(22, 28)),
                                    $this->typeNode('Boolean', $loc(30, 37)),
                                    null,
                                    $loc(22, 37)
                                ),
                                $this->inputValueNode(
                                    $this->nameNode('argTwo', $loc(39, 45)),
                                    $this->typeNode('Int', $loc(47, 50)),
                                    null,
                                    $loc(39, 50)
                                ),
                            ],
                            $loc(16, 59)
                        ),
                    ],
                    'loc'         => $loc(1, 61),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 61),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple union')
     */
    public function testSimpleUnion() : void
    {
        $body = 'union Hello = World';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::UNION_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'directives'  => [],
                    'types'       => [$this->typeNode('World', $loc(14, 19))],
                    'loc'         => $loc(0, 19),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 19),
        ];

        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Union with two types')
     */
    public function testUnionWithTwoTypes() : void
    {
        $body = 'union Hello = Wo | Rld';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::UNION_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(6, 11)),
                    'directives'  => [],
                    'types'       => [
                        $this->typeNode('Wo', $loc(14, 16)),
                        $this->typeNode('Rld', $loc(19, 22)),
                    ],
                    'loc'         => $loc(0, 22),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 22),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Union with two types and leading pipe')
     */
    public function testUnionWithTwoTypesAndLeadingPipe() : void
    {
        $body     = 'union Hello = | Wo | Rld';
        $doc      = Parser::parse($body);
        $expected = [
            'kind'        => 'Document',
            'definitions' => [
                [
                    'kind'        => 'UnionTypeDefinition',
                    'name'        => $this->nameNode('Hello', ['start' => 6, 'end' => 11]),
                    'directives'  => [],
                    'types'       => [
                        $this->typeNode('Wo', ['start' => 16, 'end' => 18]),
                        $this->typeNode('Rld', ['start' => 21, 'end' => 24]),
                    ],
                    'loc'         => ['start' => 0, 'end' => 24],
                    'description' => null,
                ],
            ],
            'loc'         => ['start' => 0, 'end' => 24],
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Union fails with no types')
     */
    public function testUnionFailsWithNoTypes() : void
    {
        $this->expectSyntaxError(
            'union Hello = |',
            'Expected Name, found <EOF>',
            $this->loc(1, 16)
        );
    }

    /**
     * @see it('Union fails with leading douple pipe')
     */
    public function testUnionFailsWithLeadingDoublePipe() : void
    {
        $this->expectSyntaxError(
            'union Hello = || Wo | Rld',
            'Expected Name, found |',
            $this->loc(1, 16)
        );
    }

    /**
     * @see it('Union fails with double pipe')
     */
    public function testUnionFailsWithDoublePipe() : void
    {
        $this->expectSyntaxError(
            'union Hello = Wo || Rld',
            'Expected Name, found |',
            $this->loc(1, 19)
        );
    }

    /**
     * @see it('Union fails with trailing pipe')
     */
    public function testUnionFailsWithTrailingPipe() : void
    {
        $this->expectSyntaxError(
            'union Hello = | Wo | Rld |',
            'Expected Name, found <EOF>',
            $this->loc(1, 27)
        );
    }

    /**
     * @see it('Scalar')
     */
    public function testScalar() : void
    {
        $body = 'scalar Hello';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::SCALAR_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(7, 12)),
                    'directives'  => [],
                    'loc'         => $loc(0, 12),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 12),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple input object')
     */
    public function testSimpleInputObject() : void
    {
        $body = '
input Hello {
  world: String
}';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::INPUT_OBJECT_TYPE_DEFINITION,
                    'name'        => $this->nameNode('Hello', $loc(7, 12)),
                    'directives'  => [],
                    'fields'      => [
                        $this->inputValueNode(
                            $this->nameNode('world', $loc(17, 22)),
                            $this->typeNode('String', $loc(24, 30)),
                            null,
                            $loc(17, 30)
                        ),
                    ],
                    'loc'         => $loc(1, 32),
                    'description' => null,
                ],
            ],
            'loc'         => $loc(0, 32),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Simple input object with args should fail')
     */
    public function testSimpleInputObjectWithArgsShouldFail() : void
    {
        $body = '
      input Hello {
        world(foo: Int): String
      }';
        $this->expectSyntaxError(
            $body,
            'Expected :, found (',
            $this->loc(3, 14)
        );
    }

    /**
     * @see it('Directive definition', () => {
     */
    public function testDirectiveDefinition() : void
    {
        $body = 'directive @foo on OBJECT | INTERFACE';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) : array {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::DIRECTIVE_DEFINITION,
                    'description' => null,
                    'name'        => $this->nameNode('foo', $loc(11, 14)),
                    'arguments'  => [],
                    'repeatable' => false,
                    'locations'      => [
                        [
                            'kind' => NodeKind::NAME,
                            'value' => DirectiveLocation::OBJECT,
                            'loc' => $loc(18, 24),
                        ],
                        [
                            'kind' => NodeKind::NAME,
                            'value' => DirectiveLocation::IFACE,
                            'loc' => $loc(27, 36),
                        ],
                    ],
                    'loc'         => $loc(0, 36),
                ],
            ],
            'loc'         => $loc(0, 36),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Repeatable directive definition', () => {
     */
    public function testRepeatableDirectiveDefinition() : void
    {
        $body = 'directive @foo repeatable on OBJECT | INTERFACE';
        $doc  = Parser::parse($body);
        $loc  = static function ($start, $end) : array {
            return TestUtils::locArray($start, $end);
        };

        $expected = [
            'kind'        => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind'        => NodeKind::DIRECTIVE_DEFINITION,
                    'description' => null,
                    'name'        => $this->nameNode('foo', $loc(11, 14)),
                    'arguments'  => [],
                    'repeatable' => true,
                    'locations'      => [
                        [
                            'kind' => NodeKind::NAME,
                            'value' => DirectiveLocation::OBJECT,
                            'loc' => $loc(29, 35),
                        ],
                        [
                            'kind' => NodeKind::NAME,
                            'value' => DirectiveLocation::IFACE,
                            'loc' => $loc(38, 47),
                        ],
                    ],
                    'loc'         => $loc(0, 47),
                ],
            ],
            'loc'         => $loc(0, 47),
        ];
        self::assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @see it('Directive with incorrect locations')
     */
    public function testDirectiveWithIncorrectLocationShouldFail() : void
    {
        $body = '
      directive @foo on FIELD | INCORRECT_LOCATION
';
        $this->expectSyntaxError(
            $body,
            'Unexpected Name "INCORRECT_LOCATION"',
            $this->loc(2, 33)
        );
    }

    public function testDoesNotAllowEmptyFields() : void
    {
        $body = 'type Hello { }';
        $this->expectSyntaxError($body, 'Syntax Error: Expected Name, found }', new SourceLocation(1, 14));
    }

    /**
     * @see it('Option: allowLegacySDLEmptyFields supports type with empty fields')
     */
    public function testAllowLegacySDLEmptyFieldsOption() : void
    {
        $body     = 'type Hello { }';
        $doc      = Parser::parse($body, ['allowLegacySDLEmptyFields' => true]);
        $expected = [
            'definitions' => [
                [
                    'fields' => [],
                ],
            ],
        ];
        self::assertArraySubset($expected, $doc->toArray(true));
    }

    public function testDoesntAllowLegacySDLImplementsInterfacesByDefault() : void
    {
        $body = 'type Hello implements Wo rld { field: String }';
        $this->expectSyntaxError($body, 'Syntax Error: Unexpected Name "rld"', new SourceLocation(1, 26));
    }

    /**
     * @see it('Option: allowLegacySDLImplementsInterfaces')
     */
    public function testDefaultSDLImplementsInterfaces() : void
    {
        $body     = 'type Hello implements Wo rld { field: String }';
        $doc      = Parser::parse($body, ['allowLegacySDLImplementsInterfaces' => true]);
        $expected = [
            'definitions' => [
                [
                    'interfaces' => [
                        $this->typeNode('Wo', ['start' => 22, 'end' => 24]),
                        $this->typeNode('rld', ['start' => 25, 'end' => 28]),
                    ],
                ],
            ],
        ];
        self::assertArraySubset($expected, $doc->toArray(true));
    }
}
