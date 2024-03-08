<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type LocationArray from Location
 */
final class SchemaParserTest extends TestCase
{
    use ArraySubsetAsserts;

    // Describe: Schema Parser

    /** @see it('Simple type') */
    public function testSimpleType(): void
    {
        $body = '
type Hello {
  world: String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(23, 29)),
                            $loc(16, 29)
                        ),
                    ],
                    'loc' => $loc(1, 31),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 31),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /**
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function nameNode(string $name, array $loc): array
    {
        return [
            'kind' => NodeKind::NAME,
            'value' => $name,
            'loc' => $loc,
        ];
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $type
     *
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function fieldNode(array $name, array $type, array $loc): array
    {
        return $this->fieldNodeWithArgs($name, $type, [], $loc);
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $type
     * @param array<int, mixed>    $args
     *
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function fieldNodeWithArgs(array $name, array $type, array $args, array $loc): array
    {
        return [
            'kind' => NodeKind::FIELD_DEFINITION,
            'name' => $name,
            'arguments' => $args,
            'type' => $type,
            'directives' => [],
            'loc' => $loc,
            // 'description' => undefined,
        ];
    }

    /**
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function typeNode(string $name, array $loc): array
    {
        return [
            'kind' => NodeKind::NAMED_TYPE,
            'name' => ['kind' => NodeKind::NAME, 'value' => $name, 'loc' => $loc],
            'loc' => $loc,
        ];
    }

    /** @see it('parses type with description string') */
    public function testParsesTypeWithDescriptionString(): void
    {
        $body = '
"Description"
type Hello {
  world: String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(20, 25)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(30, 35)),
                            $this->typeNode('String', $loc(37, 43)),
                            $loc(30, 43)
                        ),
                    ],
                    'loc' => $loc(1, 45),
                    'description' => [
                        'kind' => NodeKind::STRING,
                        'value' => 'Description',
                        'loc' => $loc(1, 14),
                        'block' => false,
                    ],
                ],
            ],
            'loc' => $loc(0, 45),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('parses type with description multi-linestring') */
    public function testParsesTypeWithDescriptionMultiLineString(): void
    {
        $body = '
"""
Description
"""
# Even with comments between them
type Hello {
  world: String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(60, 65)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(70, 75)),
                            $this->typeNode('String', $loc(77, 83)),
                            $loc(70, 83)
                        ),
                    ],
                    'loc' => $loc(1, 85),
                    'description' => [
                        'kind' => NodeKind::STRING,
                        'value' => 'Description',
                        'loc' => $loc(1, 20),
                        'block' => true,
                    ],
                ],
            ],
            'loc' => $loc(0, 85),
        ];
        self::assertEquals($expected, $doc->toArray());

        // ensure the lexer does not treat multi line comments as one line
        $tokenAfterMultiLineComment = $doc->loc?->startToken?->next?->next;
        self::assertEquals('Even with comments between them', trim($tokenAfterMultiLineComment?->value ?? ''));
        self::assertEquals(5, $tokenAfterMultiLineComment?->line);

        $typeToken = $tokenAfterMultiLineComment?->next;
        self::assertEquals('type', $typeToken?->value);
        self::assertEquals(6, $typeToken?->line);
    }

    /** @see it('Simple extension') */
    public function testSimpleExtension(): void
    {
        $body = '
extend type Hello {
  world: String
}
';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', $loc(13, 18)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(23, 28)),
                            $this->typeNode('String', $loc(30, 36)),
                            $loc(23, 36)
                        ),
                    ],
                    'loc' => $loc(1, 38),
                ],
            ],
            'loc' => $loc(0, 39),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Object extension without fields') */
    public function testObjectExtensionWithoutFields(): void
    {
        $body = 'extend type Hello implements Greeting';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', $loc(12, 17)),
                    'interfaces' => [
                        $this->typeNode('Greeting', $loc(29, 37)),
                    ],
                    'directives' => [],
                    'fields' => [],
                    'loc' => $loc(0, 37),
                ],
            ],
            'loc' => $loc(0, 37),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Interface extension without fields') */
    public function testInterfaceExtensionWithoutFields(): void
    {
        $body = 'extend interface Hello implements Greeting';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', $loc(17, 22)),
                    'interfaces' => [
                        $this->typeNode('Greeting', $loc(34, 42)),
                    ],
                    'directives' => [],
                    'fields' => [],
                    'loc' => $loc(0, 42),
                ],
            ],
            'loc' => $loc(0, 42),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Object extension without fields followed by extension') */
    public function testObjectExtensionWithoutFieldsFollowedByExtension(): void
    {
        $body = '
          extend type Hello implements Greeting
    
          extend type Hello implements SecondGreeting
        ';
        $doc = Parser::parse($body);
        $expected = [
            'kind' => 'Document',
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', ['start' => 23, 'end' => 28]),
                    'interfaces' => [$this->typeNode('Greeting', ['start' => 40, 'end' => 48])],
                    'directives' => [],
                    'fields' => [],
                    'loc' => ['start' => 11, 'end' => 48],
                ],
                [
                    'kind' => NodeKind::OBJECT_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', ['start' => 76, 'end' => 81]),
                    'interfaces' => [$this->typeNode('SecondGreeting', ['start' => 93, 'end' => 107])],
                    'directives' => [],
                    'fields' => [],
                    'loc' => ['start' => 64, 'end' => 107],
                ],
            ],
            'loc' => ['start' => 0, 'end' => 116],
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Interface extension without fields followed by extension') */
    public function testInterfaceExtensionWithoutFieldsFollowedByExtension(): void
    {
        $body = '
          extend interface Hello implements Greeting

          extend interface Hello implements SecondGreeting
        ';
        $doc = Parser::parse($body);
        $expected = [
            'kind' => 'Document',
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', ['start' => 28, 'end' => 33]),
                    'interfaces' => [$this->typeNode('Greeting', ['start' => 45, 'end' => 53])],
                    'directives' => [],
                    'fields' => [],
                    'loc' => ['start' => 11, 'end' => 53],
                ],
                [
                    'kind' => NodeKind::INTERFACE_TYPE_EXTENSION,
                    'name' => $this->nameNode('Hello', ['start' => 82, 'end' => 87]),
                    'interfaces' => [$this->typeNode('SecondGreeting', ['start' => 99, 'end' => 113])],
                    'directives' => [],
                    'fields' => [],
                    'loc' => ['start' => 65, 'end' => 113],
                ],
            ],
            'loc' => ['start' => 0, 'end' => 122],
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Object extension without anything throws') */
    public function testObjectExtensionWithoutAnythingThrows(): void
    {
        $this->expectSyntaxError(
            'extend type Hello',
            'Unexpected <EOF>',
            $this->loc(1, 18)
        );
    }

    /** @see it('Interface extension without anything throws') */
    public function testInterfaceExtensionWithoutAnythingThrows(): void
    {
        $this->expectSyntaxError(
            'extend interface Hello',
            'Unexpected <EOF>',
            $this->loc(1, 23)
        );
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private function expectSyntaxError(string $text, string $message, SourceLocation $location): void
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

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation($line, $column);
    }

    /** @see it('Object extension do not include descriptions') */
    public function testObjectExtensionDoNotIncludeDescriptions(): void
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

    /** @see it('Interface extension do not include descriptions') */
    public function testInterfaceExtensionDoNotIncludeDescriptions(): void
    {
        $body = '
      "Description"
      extend interface Hello {
        world: String
      }';
        $this->expectSyntaxError(
            $body,
            'Unexpected Name "extend"',
            $this->loc(3, 7)
        );
    }

    /** @see it('Object Extension do not include descriptions') */
    public function testObjectExtensionDoNotIncludeDescriptions2(): void
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

    /** @see it('Interface Extension do not include descriptions') */
    public function testInterfaceExtensionDoNotIncludeDescriptions2(): void
    {
        $body = '
      extend "Description" interface Hello {
        world: String
      }
}';
        $this->expectSyntaxError(
            $body,
            'Unexpected String "Description"',
            $this->loc(2, 14)
        );
    }

    /** @see it('Simple non-null type') */
    public function testSimpleNonNullType(): void
    {
        $body = '
type Hello {
  world: String!
}';
        $doc = Parser::parse($body);

        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(16, 21)),
                            [
                                'kind' => NodeKind::NON_NULL_TYPE,
                                'type' => $this->typeNode('String', $loc(23, 29)),
                                'loc' => $loc(23, 30),
                            ],
                            $loc(16, 30)
                        ),
                    ],
                    'loc' => $loc(1, 32),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 32),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple interface inheriting interface') */
    public function testSimpleInterfaceInheritingInterface(): void
    {
        $body = 'interface Hello implements World { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(10, 15)),
                    'interfaces' => [
                        $this->typeNode('World', $loc(27, 32)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(35, 40)),
                            $this->typeNode('String', $loc(42, 48)),
                            $loc(35, 48)
                        ),
                    ],
                    'loc' => $loc(0, 50),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 50),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple type inheriting interface') */
    public function testSimpleTypeInheritingInterface(): void
    {
        $body = 'type Hello implements World { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces' => [
                        $this->typeNode('World', $loc(22, 27)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(30, 35)),
                            $this->typeNode('String', $loc(37, 43)),
                            $loc(30, 43)
                        ),
                    ],
                    'loc' => $loc(0, 45),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 45),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple type inheriting multiple interfaces') */
    public function testSimpleTypeInheritingMultipleInterfaces(): void
    {
        $body = 'type Hello implements Wo & rld { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces' => [
                        $this->typeNode('Wo', $loc(22, 24)),
                        $this->typeNode('rld', $loc(27, 30)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(33, 38)),
                            $this->typeNode('String', $loc(40, 46)),
                            $loc(33, 46)
                        ),
                    ],
                    'loc' => $loc(0, 48),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 48),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple interface inheriting multiple interfaces') */
    public function testSimpleInterfaceInheritingMultipleInterfaces(): void
    {
        $body = 'interface Hello implements Wo & rld { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(10, 15)),
                    'interfaces' => [
                        $this->typeNode('Wo', $loc(27, 29)),
                        $this->typeNode('rld', $loc(32, 35)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(38, 43)),
                            $this->typeNode('String', $loc(45, 51)),
                            $loc(38, 51)
                        ),
                    ],
                    'loc' => $loc(0, 53),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 53),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple type inheriting multiple interfaces with leading ampersand') */
    public function testSimpleTypeInheritingMultipleInterfacesWithLeadingAmpersand(): void
    {
        $body = 'type Hello implements & Wo & rld { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => 'Document',
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'interfaces' => [
                        $this->typeNode('Wo', $loc(24, 26)),
                        $this->typeNode('rld', $loc(29, 32)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(35, 40)),
                            $this->typeNode('String', $loc(42, 48)),
                            $loc(35, 48)
                        ),
                    ],
                    'loc' => $loc(0, 50),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 50),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple interface inheriting multiple interfaces with leading ampersand') */
    public function testSimpleInterfaceInheritingMultipleInterfacesWithLeadingAmpersand(): void
    {
        $body = 'interface Hello implements & Wo & rld { field: String }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => 'Document',
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(10, 15)),
                    'interfaces' => [
                        $this->typeNode('Wo', $loc(29, 31)),
                        $this->typeNode('rld', $loc(34, 37)),
                    ],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('field', $loc(40, 45)),
                            $this->typeNode('String', $loc(47, 53)),
                            $loc(40, 53)
                        ),
                    ],
                    'loc' => $loc(0, 55),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 55),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Single value enum') */
    public function testSingleValueEnum(): void
    {
        $body = 'enum Hello { WORLD }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::ENUM_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'directives' => [],
                    'values' => [$this->enumValueNode('WORLD', $loc(13, 18))],
                    'loc' => $loc(0, 20),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 20),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /**
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function enumValueNode(string $name, array $loc): array
    {
        return [
            'kind' => NodeKind::ENUM_VALUE_DEFINITION,
            'name' => $this->nameNode($name, $loc),
            'directives' => [],
            'loc' => $loc,
            // 'description' => undefined,
        ];
    }

    /** @see it('Double value enum') */
    public function testDoubleValueEnum(): void
    {
        $body = 'enum Hello { WO, RLD }';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::ENUM_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(5, 10)),
                    'directives' => [],
                    'values' => [
                        $this->enumValueNode('WO', $loc(13, 15)),
                        $this->enumValueNode('RLD', $loc(17, 20)),
                    ],
                    'loc' => $loc(0, 22),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 22),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple interface') */
    public function testSimpleInterface(): void
    {
        $body = '
interface Hello {
  world: String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::INTERFACE_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(11, 16)),
                    'directives' => [],
                    'fields' => [
                        $this->fieldNode(
                            $this->nameNode('world', $loc(21, 26)),
                            $this->typeNode('String', $loc(28, 34)),
                            $loc(21, 34)
                        ),
                    ],
                    'interfaces' => [],
                    'loc' => $loc(1, 36),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 36),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple field with arg') */
    public function testSimpleFieldWithArg(): void
    {
        $body = '
type Hello {
  world(flag: Boolean): String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
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
                                    Utils::undefined(),
                                    $loc(22, 35)
                                ),
                            ],
                            $loc(16, 44)
                        ),
                    ],
                    'loc' => $loc(1, 46),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 46),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $type
     * @param mixed                $defaultValue
     *
     * @phpstan-param LocationArray $loc
     *
     * @return array<string, mixed>
     */
    private function inputValueNode(array $name, array $type, $defaultValue, array $loc): array
    {
        $node = [
            'kind' => NodeKind::INPUT_VALUE_DEFINITION,
            'name' => $name,
            'type' => $type,
            'directives' => [],
            'loc' => $loc,
            // 'description'  => undefined,
        ];

        if ($defaultValue !== Utils::undefined()) {
            $node['defaultValue'] = $defaultValue;
        }

        return $node;
    }

    /** @see it('Simple field with arg with default value') */
    public function testSimpleFieldWithArgWithDefaultValue(): void
    {
        $body = '
type Hello {
  world(flag: Boolean = true): String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
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
                                    ['kind' => NodeKind::BOOLEAN, 'value' => true, 'loc' => $loc(38, 42)],
                                    $loc(22, 42)
                                ),
                            ],
                            $loc(16, 51)
                        ),
                    ],
                    'loc' => $loc(1, 53),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 53),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple field with list arg') */
    public function testSimpleFieldWithListArg(): void
    {
        $body = '
type Hello {
  world(things: [String]): String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'interfaces' => [],
                    'directives' => [],
                    'fields' => [
                        $this->fieldNodeWithArgs(
                            $this->nameNode('world', $loc(16, 21)),
                            $this->typeNode('String', $loc(41, 47)),
                            [
                                $this->inputValueNode(
                                    $this->nameNode('things', $loc(22, 28)),
                                    [
                                        'kind' => NodeKind::LIST_TYPE,
                                        'type' => $this->typeNode(
                                            'String',
                                            $loc(31, 37)
                                        ),
                                        'loc' => $loc(30, 38),
                                    ],
                                    Utils::undefined(),
                                    $loc(22, 38)
                                ),
                            ],
                            $loc(16, 47)
                        ),
                    ],
                    'loc' => $loc(1, 49),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 49),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple field with two args') */
    public function testSimpleFieldWithTwoArgs(): void
    {
        $body = '
type Hello {
  world(argOne: Boolean, argTwo: Int): String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::OBJECT_TYPE_DEFINITION,
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
                                    Utils::undefined(),
                                    $loc(22, 37)
                                ),
                                $this->inputValueNode(
                                    $this->nameNode('argTwo', $loc(39, 45)),
                                    $this->typeNode('Int', $loc(47, 50)),
                                    Utils::undefined(),
                                    $loc(39, 50)
                                ),
                            ],
                            $loc(16, 59)
                        ),
                    ],
                    'loc' => $loc(1, 61),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 61),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple union') */
    public function testSimpleUnion(): void
    {
        $body = 'union Hello = World';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::UNION_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'directives' => [],
                    'types' => [$this->typeNode('World', $loc(14, 19))],
                    'loc' => $loc(0, 19),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 19),
        ];

        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Union with two types') */
    public function testUnionWithTwoTypes(): void
    {
        $body = 'union Hello = Wo | Rld';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::UNION_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(6, 11)),
                    'directives' => [],
                    'types' => [
                        $this->typeNode('Wo', $loc(14, 16)),
                        $this->typeNode('Rld', $loc(19, 22)),
                    ],
                    'loc' => $loc(0, 22),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 22),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Union with two types and leading pipe') */
    public function testUnionWithTwoTypesAndLeadingPipe(): void
    {
        $body = 'union Hello = | Wo | Rld';
        $doc = Parser::parse($body);
        $expected = [
            'kind' => 'Document',
            'definitions' => [
                [
                    'kind' => 'UnionTypeDefinition',
                    'name' => $this->nameNode('Hello', ['start' => 6, 'end' => 11]),
                    'directives' => [],
                    'types' => [
                        $this->typeNode('Wo', ['start' => 16, 'end' => 18]),
                        $this->typeNode('Rld', ['start' => 21, 'end' => 24]),
                    ],
                    'loc' => ['start' => 0, 'end' => 24],
                    // 'description' => undefined,
                ],
            ],
            'loc' => ['start' => 0, 'end' => 24],
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Union fails with no types') */
    public function testUnionFailsWithNoTypes(): void
    {
        $this->expectSyntaxError(
            'union Hello = |',
            'Expected Name, found <EOF>',
            $this->loc(1, 16)
        );
    }

    /** @see it('Union fails with leading douple pipe') */
    public function testUnionFailsWithLeadingDoublePipe(): void
    {
        $this->expectSyntaxError(
            'union Hello = || Wo | Rld',
            'Expected Name, found |',
            $this->loc(1, 16)
        );
    }

    /** @see it('Union fails with double pipe') */
    public function testUnionFailsWithDoublePipe(): void
    {
        $this->expectSyntaxError(
            'union Hello = Wo || Rld',
            'Expected Name, found |',
            $this->loc(1, 19)
        );
    }

    /** @see it('Union fails with trailing pipe') */
    public function testUnionFailsWithTrailingPipe(): void
    {
        $this->expectSyntaxError(
            'union Hello = | Wo | Rld |',
            'Expected Name, found <EOF>',
            $this->loc(1, 27)
        );
    }

    /** @see it('Scalar') */
    public function testScalar(): void
    {
        $body = 'scalar Hello';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::SCALAR_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(7, 12)),
                    'directives' => [],
                    'loc' => $loc(0, 12),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 12),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple input object') */
    public function testSimpleInputObject(): void
    {
        $body = '
input Hello {
  world: String
}';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::INPUT_OBJECT_TYPE_DEFINITION,
                    'name' => $this->nameNode('Hello', $loc(7, 12)),
                    'directives' => [],
                    'fields' => [
                        $this->inputValueNode(
                            $this->nameNode('world', $loc(17, 22)),
                            $this->typeNode('String', $loc(24, 30)),
                            Utils::undefined(),
                            $loc(17, 30)
                        ),
                    ],
                    'loc' => $loc(1, 32),
                    // 'description' => undefined,
                ],
            ],
            'loc' => $loc(0, 32),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Simple input object with args should fail') */
    public function testSimpleInputObjectWithArgsShouldFail(): void
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

    /** @see it('Directive definition', () => { */
    public function testDirectiveDefinition(): void
    {
        $body = 'directive @foo on OBJECT | INTERFACE';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::DIRECTIVE_DEFINITION,
                    'name' => $this->nameNode('foo', $loc(11, 14)),
                    // 'description' => undefined,
                    'arguments' => [],
                    'repeatable' => false,
                    'locations' => [
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
                    'loc' => $loc(0, 36),
                ],
            ],
            'loc' => $loc(0, 36),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Repeatable directive definition', () => { */
    public function testRepeatableDirectiveDefinition(): void
    {
        $body = 'directive @foo repeatable on OBJECT | INTERFACE';
        $doc = Parser::parse($body);
        $loc = static fn (int $start, int $end): array => Location::create($start, $end)->toArray();

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'definitions' => [
                [
                    'kind' => NodeKind::DIRECTIVE_DEFINITION,
                    'name' => $this->nameNode('foo', $loc(11, 14)),
                    // 'description' => undefined,
                    'arguments' => [],
                    'repeatable' => true,
                    'locations' => [
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
                    'loc' => $loc(0, 47),
                ],
            ],
            'loc' => $loc(0, 47),
        ];
        self::assertEquals($expected, $doc->toArray());
    }

    /** @see it('Directive with incorrect locations') */
    public function testDirectiveWithIncorrectLocationShouldFail(): void
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

    public function testDoesNotAllowEmptyFields(): void
    {
        $body = 'type Hello { }';
        $this->expectSyntaxError($body, 'Syntax Error: Expected Name, found }', new SourceLocation(1, 14));
    }

    /** @see it('Option: allowLegacySDLEmptyFields supports type with empty fields') */
    public function testAllowLegacySDLEmptyFieldsOption(): void
    {
        $body = 'type Hello { }';
        $doc = Parser::parse($body, ['allowLegacySDLEmptyFields' => true]);
        $expected = [
            'definitions' => [
                [
                    'fields' => [],
                ],
            ],
        ];
        self::assertArraySubset($expected, $doc->toArray());
    }

    public function testDoesntAllowLegacySDLImplementsInterfacesByDefault(): void
    {
        $body = 'type Hello implements Wo rld { field: String }';
        $this->expectSyntaxError($body, 'Syntax Error: Unexpected Name "rld"', new SourceLocation(1, 26));
    }

    /** @see it('Option: allowLegacySDLImplementsInterfaces') */
    public function testDefaultSDLImplementsInterfaces(): void
    {
        $body = 'type Hello implements Wo rld { field: String }';
        $doc = Parser::parse($body, ['allowLegacySDLImplementsInterfaces' => true]);
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
        self::assertArraySubset($expected, $doc->toArray());
    }
}
