<?php

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumTypeDefinition;
use GraphQL\Language\AST\EnumValueDefinition;
use GraphQL\Language\AST\FieldDefinition;
use GraphQL\Language\AST\InputObjectTypeDefinition;
use GraphQL\Language\AST\InputValueDefinition;
use GraphQL\Language\AST\InterfaceTypeDefinition;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\NonNullType;
use GraphQL\Language\AST\ObjectTypeDefinition;
use GraphQL\Language\AST\ScalarTypeDefinition;
use GraphQL\Language\AST\TypeExtensionDefinition;
use GraphQL\Language\AST\UnionTypeDefinition;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;

class SchemaParserTest extends \PHPUnit_Framework_TestCase
{
    // Describe: Schema Parser

    /**
     * @it Simple type
     */
    public function testSimpleType()
    {
        $body = '
type Hello {
  world: String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(23, 29)),
                            $loc(16, 29)
                        )
                    ],
                    'loc' => $loc(1, 31)
                ]
            ],
            'loc' => $loc(0, 31)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple extension
     */
    public function testSimpleExtension()
    {
        $body = '
extend type Hello {
  world: String
}
';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {
            return TestUtils::locArray($start, $end);
        };
        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::TYPE_EXTENSION_DEFINITION,
                    'definition' => [
                        'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                        'name' => $this->nameNode('Hello', $loc(13, 18)),
                        'interfaces' => [],
                        'directives' => [],
                        'fields' => [
                            $this->fieldNode(
                                $this->nameNode('world', $loc(23, 28)),
                                $this->typeNode('String', $loc(30, 36)),
                                $loc(23, 36)
                            )
                        ],
                        'loc' => $loc(8, 38)
                    ],
                    'loc' => $loc(1, 38)
                ]
            ],
            'loc' => $loc(0, 39)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple non-null type
     */
    public function testSimpleNonNullType()
    {
        $body = '
type Hello {
  world: String!
}';
        $loc = function($start, $end) {
            return TestUtils::locArray($start, $end);
        };
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6,11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            [
                                'kind' => NodeType::NON_NULL_TYPE,
                                'type' => $this->typeNode('String', $loc(23, 29)),
                                'loc' => $loc(23, 30)
                            ],
                            $loc(16,30)
                        )
                    ],
                    'loc' => $loc(1,32)
                ]
            ],
            'loc' => $loc(0,32)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple type inheriting interface
     */
    public function testSimpleTypeInheritingInterface()
    {
        $body = 'type Hello implements World { }';
        $loc = function($start, $end) { return TestUtils::locArray($start, $end); };
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces' => [
                        $this->typeNode('World', $loc(22, 27))
                    ],
                    'directives' => [],
                    'fields' => [],
                    'loc' => $loc(0,31)
                ]
            ],
            'loc' => $loc(0,31)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple type inheriting multiple interfaces
     */
    public function testSimpleTypeInheritingMultipleInterfaces()
    {
        $body = 'type Hello implements Wo, rld { }';
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces' => [
                        $this->typeNode('Wo', $loc(22,24)),
                        $this->typeNode('rld', $loc(26,29))
                    ],
                    'directives' => [],
                    'fields' => [],
                    'loc' => $loc(0, 33)
                ]
            ],
            'loc' => $loc(0, 33)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Single value enum
     */
    public function testSingleValueEnum()
    {
        $body = 'enum Hello { WORLD }';
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::ENUM_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'directives' => [],
                    'values' => [$this->enumValueNode('WORLD', $loc(13, 18))],
                    'loc' => $loc(0, 20)
                ]
            ],
            'loc' => $loc(0, 20)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Double value enum
     */
    public function testDoubleValueEnum()
    {
        $body = 'enum Hello { WO, RLD }';
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::ENUM_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'directives' => [],
                    'values' => [
                        $this->enumValueNode('WO', $loc(13, 15)),
                        $this->enumValueNode('RLD', $loc(17, 20))
                    ],
                    'loc' => $loc(0, 22)
                ]
            ],
            'loc' => $loc(0, 22)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple interface
     */
    public function testSimpleInterface()
    {
        $body = '
interface Hello {
  world: String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::INTERFACE_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(11, 16)),
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(21, 26)),
                            $this->typeNode('String', $loc(28, 34)),
                            $loc(21, 34)
                        )
                    ],
                    'loc' => $loc(1, 36)
                ]
            ],
            'loc' => $loc(0,36)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple field with arg
     */
    public function testSimpleFieldWithArg()
    {
        $body = '
type Hello {
  world(flag: Boolean): String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(38, 44)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('flag', $loc(22, 26)),
                                    $this->typeNode('Boolean', $loc(28, 35)),
                                    null,
                                    $loc(22, 35)
                                )
                            ],
                            $loc(16, 44)
                        )
                    ],
                    'loc' => $loc(1, 46)
                ]
            ],
            'loc' => $loc(0, 46)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple field with arg with default value
     */
    public function testSimpleFieldWithArgWithDefaultValue()
    {
        $body = '
type Hello {
  world(flag: Boolean = true): String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(45, 51)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('flag', $loc(22, 26)),
                                    $this->typeNode('Boolean', $loc(28, 35)),
                                    ['kind' => NodeType::BOOLEAN, 'value' => true, 'loc' => $loc(38, 42)],
                                    $loc(22, 42)
                                )
                            ],
                            $loc(16, 51)
                        )
                    ],
                    'loc' => $loc(1, 53)
                ]
            ],
            'loc' => $loc(0, 53)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple field with list arg
     */
    public function testSimpleFieldWithListArg()
    {
        $body = '
type Hello {
  world(things: [String]): String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(41, 47)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('things', $loc(22,28)),
                                    ['kind' => NodeType::LIST_TYPE, 'type' => $this->typeNode('String', $loc(31, 37)), 'loc' => $loc(30, 38)],
                                    null,
                                    $loc(22, 38)
                                )
                            ],
                            $loc(16, 47)
                        )
                    ],
                    'loc' => $loc(1, 49)
                ]
            ],
            'loc' => $loc(0, 49)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple field with two args
     */
    public function testSimpleFieldWithTwoArgs()
    {
        $body = '
type Hello {
  world(argOne: Boolean, argTwo: Int): String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
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
                                )
                            ],
                            $loc(16, 59)
                        )
                    ],
                    'loc' => $loc(1, 61)
                ]
            ],
            'loc' => $loc(0, 61)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple union
     */
    public function testSimpleUnion()
    {
        $body = 'union Hello = World';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};
        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::UNION_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'directives' => [],
                    'types' => [$this->typeNode('World', $loc(14, 19))],
                    'loc' => $loc(0, 19)
                ]
            ],
            'loc' => $loc(0, 19)
        ];

        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Union with two types
     */
    public function testUnionWithTwoTypes()
    {
        $body = 'union Hello = Wo | Rld';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::UNION_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'directives' => [],
                    'types' => [
                        $this->typeNode('Wo', $loc(14, 16)),
                        $this->typeNode('Rld', $loc(19, 22))
                    ],
                    'loc' => $loc(0, 22)
                ]
            ],
            'loc' => $loc(0, 22)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Scalar
     */
    public function testScalar()
    {
        $body = 'scalar Hello';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};
        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::SCALAR_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(7, 12)),
                    'directives' => [],
                    'loc' => $loc(0, 12)
                ]
            ],
            'loc' => $loc(0, 12)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple input object
     */
    public function testSimpleInputObject()
    {
        $body = '
input Hello {
  world: String
}';
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
        $loc = function($start, $end) {return TestUtils::locArray($start, $end);};

        $expected = [
            'kind' => NodeType::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeType::INPUT_OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(7, 12)),
                    'directives' => [],
                    'fields' => [
                        $this->inputValueNode(
                            $this->nameNode('world', $loc(17, 22)),
                            $this->typeNode('String', $loc(24, 30)),
                            null,
                            $loc(17, 30)
                        )
                    ],
                    'loc' => $loc(1, 32)
                ]
            ],
            'loc' => $loc(0, 32)
        ];
        $this->assertEquals($expected, TestUtils::nodeToArray($doc));
    }

    /**
     * @it Simple input object with args should fail
     */
    public function testSimpleInputObjectWithArgsShouldFail()
    {
        $body = '
input Hello {
  world(foo: Int): String
}';
        $this->setExpectedException('GraphQL\Error\SyntaxError');
        $parser = new Parser(new Lexer());
        $doc = $parser->parse($body);
    }

    private function typeNode($name, $loc)
    {
        return [
            'kind' => NodeType::NAMED_TYPE,
            'name' => ['kind' => NodeType::NAME, 'value' => $name, 'loc' => $loc],
            'loc' => $loc
        ];
    }

    private function nameNode($name, $loc)
    {
        return [
            'kind' => NodeType::NAME,
            'value' => $name,
            'loc' => $loc
        ];
    }

    private function fieldNode($name, $type, $loc)
    {
        return $this->fieldNodeWithArgs($name, $type, [], $loc);
    }

    private function fieldNodeWithArgs($name, $type, $args, $loc)
    {
        return [
            'kind' => NodeType::FIELD_DEFINITION,
            'name' => $name,
            'arguments' => $args,
            'type' => $type,
            'directives' => [],
            'loc' => $loc
        ];
    }

    private function enumValueNode($name, $loc)
    {
        return [
            'kind' => NodeType::ENUM_VALUE_DEFINITION,
            'name' => $this->nameNode($name, $loc),
            'directives' => [],
            'loc' => $loc
        ];
    }

    private function inputValueNode($name, $type, $defaultValue, $loc)
    {
        return [
            'kind' => NodeType::INPUT_VALUE_DEFINITION,
            'name' => $name,
            'type' => $type,
            'defaultValue' => $defaultValue,
            'directives' => [],
            'loc' => $loc
        ];
    }
}
