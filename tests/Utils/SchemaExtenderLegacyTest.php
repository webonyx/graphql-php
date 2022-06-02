<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use function array_map;
use function array_merge;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use function iterator_to_array;
use PHPUnit\Framework\TestCase;

/**
 * Contains tests originating from `graphql-js` that previously were in SchemaExtenderTest.
 * Their counterparts have been removed from `extendSchema-test.js` and moved elsewhere,
 * but these changes to `graphql-js` haven't been reflected in `graphql-php` yet.
 * TODO align with:
 *   - https://github.com/graphql/graphql-js/commit/c1745228b2ae5ec89b8de36ea766d544607e21ea
 *   - https://github.com/graphql/graphql-js/commit/257797a0ebdddd3da6e75b7c237fdc12a1a7c75a
 *   - https://github.com/graphql/graphql-js/commit/3b9ea61f2348215dee755f779caef83df749d2bb
 *   - https://github.com/graphql/graphql-js/commit/e6a3f08cc92594f68a6e61d3d4b46a6d279f845e.
 */
class SchemaExtenderLegacyTest extends TestCase
{
    protected Schema $testSchema;

    /** @var array<string> */
    protected array $testSchemaDefinitions;

    public function setUp(): void
    {
        parent::setUp();

        $SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => static fn ($x) => $x,
        ]);

        $SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => static function () use (&$SomeInterfaceType): array {
                return [
                    'some' => ['type' => $SomeInterfaceType],
                ];
            },
        ]);

        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static function () use (&$AnotherInterfaceType): array {
                return [
                    'name' => ['type' => Type::string()],
                    'some' => ['type' => $AnotherInterfaceType],
                ];
            },
        ]);

        $FooType = new ObjectType([
            'name' => 'Foo',
            'interfaces' => [$AnotherInterfaceType, $SomeInterfaceType],
            'fields' => static function () use ($AnotherInterfaceType, &$FooType): array {
                return [
                    'name' => ['type' => Type::string()],
                    'some' => ['type' => $AnotherInterfaceType],
                    'tree' => ['type' => Type::nonNull(Type::listOf($FooType))],
                ];
            },
        ]);

        $BarType = new ObjectType([
            'name' => 'Bar',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static function () use ($SomeInterfaceType, $FooType): array {
                return [
                    'some' => ['type' => $SomeInterfaceType],
                    'foo' => ['type' => $FooType],
                ];
            },
        ]);

        $BizType = new ObjectType([
            'name' => 'Biz',
            'fields' => static function (): array {
                return [
                    'fizz' => ['type' => Type::string()],
                ];
            },
        ]);

        $SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'types' => [$FooType, $BizType],
        ]);

        $SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONE' => ['value' => 1],
                'TWO' => ['value' => 2],
            ],
        ]);

        $SomeInputType = new InputObjectType([
            'name' => 'SomeInput',
            'fields' => static function (): array {
                return [
                    'fooArg' => ['type' => Type::string()],
                ];
            },
        ]);

        $FooDirective = new Directive([
            'name' => 'foo',
            'args' => ['input' => $SomeInputType],
            'locations' => [
                DirectiveLocation::SCHEMA,
                DirectiveLocation::SCALAR,
                DirectiveLocation::OBJECT,
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::ARGUMENT_DEFINITION,
                DirectiveLocation::IFACE,
                DirectiveLocation::UNION,
                DirectiveLocation::ENUM,
                DirectiveLocation::ENUM_VALUE,
                DirectiveLocation::INPUT_OBJECT,
                DirectiveLocation::INPUT_FIELD_DEFINITION,
            ],
        ]);

        $this->testSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => static function () use ($FooType, $SomeScalarType, $SomeUnionType, $SomeEnumType, $SomeInterfaceType, $SomeInputType): array {
                    return [
                        'foo' => ['type' => $FooType],
                        'someScalar' => ['type' => $SomeScalarType],
                        'someUnion' => ['type' => $SomeUnionType],
                        'someEnum' => ['type' => $SomeEnumType],
                        'someInterface' => [
                            'args' => [
                                'id' => [
                                    'type' => Type::nonNull(Type::id()),
                                ],
                            ],
                            'type' => $SomeInterfaceType,
                        ],
                        'someInput' => [
                            'args' => ['input' => ['type' => $SomeInputType]],
                            'type' => Type::string(),
                        ],
                    ];
                },
            ]),
            'types' => [$FooType, $BarType],
            'directives' => array_merge(GraphQL::getStandardDirectives(), [$FooDirective]),
        ]);

        $testSchemaAst = Parser::parse(SchemaPrinter::doPrint($this->testSchema));

        $this->testSchemaDefinitions = array_map(static function ($node): string {
            return Printer::doPrint($node);
        }, iterator_to_array($testSchemaAst->definitions->getIterator()));
    }

    /**
     * @param array<string, bool> $options
     */
    protected function extendTestSchema(string $sdl, array $options = []): Schema
    {
        $originalPrint = SchemaPrinter::doPrint($this->testSchema);
        $ast = Parser::parse($sdl);
        $extendedSchema = SchemaExtender::extend($this->testSchema, $ast, $options);

        self::assertEquals(SchemaPrinter::doPrint($this->testSchema), $originalPrint);

        return $extendedSchema;
    }

    // Extract check for unique directive names into separate rule

    /**
     * @see it('does not allow replacing a custom directive')
     */
    public function testDoesNotAllowReplacingACustomDirective(): void
    {
        $extendedSchema = $this->extendTestSchema('
          directive @meow(if: Boolean!) on FIELD | FRAGMENT_SPREAD
        ');

        $replacementAST = Parser::parse('
            directive @meow(if: Boolean!) on FIELD | QUERY
        ');

        try {
            SchemaExtender::extend($extendedSchema, $replacementAST);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Directive "meow" already exists in the schema. It cannot be redefined.', $error->getMessage());
        }
    }

    /**
     * @see it('does not allow replacing an existing field')
     */
    public function testDoesNotAllowReplacingAnExistingField(): void
    {
        $existingFieldError = static function (string $type, string $field): string {
            return 'Field "' . $type . '.' . $field . '" already exists in the schema. It cannot also be defined in this type extension.';
        };

        $typeSDL = '
          extend type Bar {
            foo: Foo
          }
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingFieldError('Bar', 'foo'), $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            some: Foo
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error  $error) {
            self::assertEquals($existingFieldError('SomeInterface', 'some'), $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            fooArg: String
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingFieldError('SomeInput', 'fooArg'), $error->getMessage());
        }
    }

    // Extract check for unique type names into separate rule

    /**
     * @see it('does not allow replacing an existing type')
     */
    public function testDoesNotAllowReplacingAnExistingType(): void
    {
        $existingTypeError = static function ($type): string {
            return 'Type "' . $type . '" already exists in the schema. It cannot also be defined in this type definition.';
        };

        $typeSDL = '
            type Bar
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('Bar'), $error->getMessage());
        }

        $scalarSDL = '
          scalar SomeScalar
        ';

        try {
            $this->extendTestSchema($scalarSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeScalar'), $error->getMessage());
        }

        $interfaceSDL = '
          interface SomeInterface
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeInterface'), $error->getMessage());
        }

        $enumSDL = '
          enum SomeEnum
        ';

        try {
            $this->extendTestSchema($enumSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeEnum'), $error->getMessage());
        }

        $unionSDL = '
          union SomeUnion
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeUnion'), $error->getMessage());
        }

        $inputSDL = '
          input SomeInput
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeInput'), $error->getMessage());
        }
    }

    // Validation: add support of SDL to KnownTypeNames

    /**
     * @see it('does not allow referencing an unknown type')
     */
    public function testDoesNotAllowReferencingAnUnknownType(): void
    {
        $unknownTypeError = 'Unknown type: "Quix". Ensure that this type exists either in the original schema, or is added in a type definition.';

        $typeSDL = '
          extend type Bar {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $unionSDL = '
          extend union SomeUnion = Quix
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }
    }

    // Extract check for possible extensions into a separate rule (#1643)

    /**
     * @see it('does not allow extending an unknown type')
     */
    public function testDoesNotAllowExtendingAnUnknownType(): void
    {
        $sdls = [
            'extend scalar UnknownType @foo',
            'extend type UnknownType @foo',
            'extend interface UnknownType @foo',
            'extend enum UnknownType @foo',
            'extend union UnknownType @foo',
            'extend input UnknownType @foo',
        ];

        foreach ($sdls as $sdl) {
            try {
                $this->extendTestSchema($sdl);
                self::fail();
            } catch (Error $error) {
                self::assertEquals('Cannot extend type "UnknownType" because it does not exist in the existing schema.', $error->getMessage());
            }
        }
    }

    /**
     * @see it('does not allow extending a mismatch type')
     */
    public function testDoesNotAllowExtendingAMismatchType(): void
    {
        $typeSDL = '
          extend type SomeInterface @foo
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-object type "SomeInterface".', $error->getMessage());
        }

        $interfaceSDL = '
          extend interface Foo @foo
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-interface type "Foo".', $error->getMessage());
        }

        $enumSDL = '
          extend enum Foo @foo
        ';

        try {
            $this->extendTestSchema($enumSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-enum type "Foo".', $error->getMessage());
        }

        $unionSDL = '
          extend union Foo @foo
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-union type "Foo".', $error->getMessage());
        }

        $inputSDL = '
          extend input Foo @foo
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-input object type "Foo".', $error->getMessage());
        }
    }
}
