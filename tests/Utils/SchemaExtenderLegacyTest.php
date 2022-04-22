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
                assert($FooType instanceof ObjectType);

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
            'fields' => static fn (): array => [
                'some' => ['type' => $SomeInterfaceType],
                'foo' => ['type' => $FooType],
            ],
        ]);

        $BizType = new ObjectType([
            'name' => 'Biz',
            'fields' => static fn (): array => [
                'fizz' => [
                    'type' => Type::string(),
                ],
            ],
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
            'fields' => static fn (): array => [
                'fooArg' => [
                    'type' => Type::string(),
                ],
            ],
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
                'fields' => static fn (): array => [
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
                ],
            ]),
            'types' => [$FooType, $BarType],
            'directives' => array_merge(GraphQL::getStandardDirectives(), [$FooDirective]),
        ]);

        $testSchemaAst = Parser::parse(SchemaPrinter::doPrint($this->testSchema));

        $this->testSchemaDefinitions = array_map(
            static fn ($node): string => Printer::doPrint($node),
            iterator_to_array($testSchemaAst->definitions->getIterator())
        );
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

        $extendedSchema->assertValid();

        return $extendedSchema;
    }

    /**
     * @see it('does not allow replacing an existing field')
     */
    public function testDoesNotAllowReplacingAnExistingField(): void
    {
        $existingFieldError = static fn (string $type, string $field): string => 'Field "' . $type . '.' . $field . '" already exists in the schema. It cannot also be defined in this type extension.';

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
}
