<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Type\TestClasses\MyCustomType;
use GraphQL\Tests\Type\TestClasses\OtherCustom;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

use function Safe\json_encode;

final class DefinitionTest extends TestCaseBase
{
    use ArraySubsetAsserts;

    public ObjectType $blogImage;

    public ObjectType $blogArticle;

    public ObjectType $blogAuthor;

    public ObjectType $blogMutation;

    public ObjectType $blogQuery;

    public ObjectType $blogSubscription;

    public ObjectType $objectType;

    public ObjectType $objectWithIsTypeOf;

    public InterfaceType $interfaceType;

    public UnionType $unionType;

    public EnumType $enumType;

    public InputObjectType $inputObjectType;

    public CustomScalarType $scalarType;

    public function setUp(): void
    {
        $this->objectType = new ObjectType(['name' => 'Object', 'fields' => ['tmp' => Type::string()]]);
        $this->interfaceType = new InterfaceType(['name' => 'Interface', 'fields' => ['irrelevant' => Type::int()]]);
        $this->unionType = new UnionType(['name' => 'Union', 'types' => [$this->objectType]]);
        $this->enumType = new EnumType(['name' => 'Enum', 'values' => ['IRRELEVANT']]);
        $this->inputObjectType = new InputObjectType(['name' => 'InputObject', 'fields' => []]);

        $this->objectWithIsTypeOf = new ObjectType([
            'name' => 'ObjectWithIsTypeOf',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->scalarType = new CustomScalarType([
            'name' => 'Scalar',
            'serialize' => static function (): void {},
            'parseValue' => static function (): void {},
            'parseLiteral' => static function (): void {},
        ]);

        $this->blogImage = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $this->blogAuthor = new ObjectType([
            'name' => 'Author',
            'fields' => fn (): array => [
                'id' => ['type' => Type::string()],
                'name' => ['type' => Type::string()],
                'pic' => [
                    'type' => $this->blogImage,
                    'args' => [
                        'width' => ['type' => Type::int()],
                        'height' => ['type' => Type::int()],
                    ],
                ],
                'recentArticle' => $this->blogArticle,
            ],
        ]);

        $this->blogArticle = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author' => ['type' => $this->blogAuthor],
                'title' => ['type' => Type::string()],
                'body' => ['type' => Type::string()],
            ],
        ]);

        $this->blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $this->blogArticle,
                    'args' => [
                        'id' => ['type' => Type::string()],
                    ],
                ],
                'feed' => ['type' => new ListOfType($this->blogArticle)],
            ],
        ]);

        $this->blogMutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'writeArticle' => ['type' => $this->blogArticle],
            ],
        ]);

        $this->blogSubscription = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'articleSubscribe' => [
                    'args' => ['id' => ['type' => Type::string()]],
                    'type' => $this->blogArticle,
                ],
            ],
        ]);
    }

    // Type System: Example

    /** @see it('defines a query only schema') */
    public function testDefinesAQueryOnlySchema(): void
    {
        $blogSchema = new Schema([
            'query' => $this->blogQuery,
        ]);

        self::assertSame($blogSchema->getQueryType(), $this->blogQuery);

        $articleField = $this->blogQuery->getField('article');
        self::assertSame('article', $articleField->name);

        $articleFieldType = $articleField->getType();
        self::assertInstanceOf(ObjectType::class, $articleFieldType);
        self::assertSame($articleFieldType, $this->blogArticle);
        self::assertSame('Article', $articleFieldType->name);

        $titleField = $articleFieldType->getField('title');
        self::assertSame('title', $titleField->name);
        self::assertSame(Type::string(), $titleField->getType());

        $authorField = $articleFieldType->getField('author');

        $authorFieldType = $authorField->getType();
        self::assertInstanceOf(ObjectType::class, $authorFieldType);
        self::assertSame($this->blogAuthor, $authorFieldType);

        $recentArticleField = $authorFieldType->getField('recentArticle');
        self::assertSame($this->blogArticle, $recentArticleField->getType());

        $feedField = $this->blogQuery->getField('feed');

        $feedFieldType = $feedField->getType();
        self::assertInstanceOf(ListOfType::class, $feedFieldType);
        self::assertSame($this->blogArticle, $feedFieldType->getWrappedType());
    }

    /** @see it('defines a mutation schema') */
    public function testDefinesAMutationSchema(): void
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $this->blogMutation,
        ]);

        self::assertSame($this->blogMutation, $schema->getMutationType());

        $writeMutation = $this->blogMutation->getField('writeArticle');
        self::assertSame('writeArticle', $writeMutation->name);

        $writeMutationType = $writeMutation->getType();
        self::assertSame($this->blogArticle, $writeMutationType);
        self::assertSame('Article', $writeMutationType->name);
    }

    /** @see it('defines a subscription schema') */
    public function testDefinesSubscriptionSchema(): void
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'subscription' => $this->blogSubscription,
        ]);

        self::assertEquals($this->blogSubscription, $schema->getSubscriptionType());

        $sub = $this->blogSubscription->getField('articleSubscribe');
        $subType = $sub->getType();
        self::assertInstanceOf(ObjectType::class, $subType);
        self::assertEquals($subType, $this->blogArticle);
        self::assertSame('Article', $subType->name);
        self::assertSame('articleSubscribe', $sub->name);
    }

    /**
     * @see describe('Type System: Enums', () => {
     * @see it('defines an enum type with deprecated value')
     */
    public function testDefinesEnumTypeWithDeprecatedValue(): void
    {
        $enumTypeWithDeprecatedValue = new EnumType([
            'name' => 'EnumWithDeprecatedValue',
            'values' => [
                'foo' => ['deprecationReason' => 'Just because'],
            ],
        ]);

        $value = $enumTypeWithDeprecatedValue->getValues()[0];

        self::assertArraySubset(
            [
                'name' => 'foo',
                'description' => null,
                'deprecationReason' => 'Just because',
                'value' => 'foo',
                'astNode' => null,
            ],
            (array) $value
        );

        self::assertEquals(true, $value->isDeprecated());
    }

    /** @see it('defines an enum type with a value of `null` and `undefined`') */
    public function testDefinesAnEnumTypeWithAValueOfNullAndUndefined(): void
    {
        $EnumTypeWithNullishValue = new EnumType([
            'name' => 'EnumWithNullishValue',
            'values' => [
                'NULL' => ['value' => null],
                'UNDEFINED' => ['value' => null],
            ],
        ]);

        $expected = [
            [
                'name' => 'NULL',
                'description' => null,
                'deprecationReason' => null,
                'value' => null,
                'astNode' => null,
            ],
            [
                'name' => 'UNDEFINED',
                'description' => null,
                'deprecationReason' => null,
                'value' => null,
                'astNode' => null,
            ],
        ];

        $actual = $EnumTypeWithNullishValue->getValues();

        self::assertCount(count($expected), $actual);
        self::assertArraySubset($expected[0], (array) $actual[0]);
        self::assertArraySubset($expected[1], (array) $actual[1]);
    }

    /** @see it('accepts a well defined Enum type with empty value definition') */
    public function testAcceptsAWellDefinedEnumTypeWithEmptyValueDefinition(): void
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => [],
                'BAR' => [],
            ],
        ]);

        $foo = $enumType->getValue('FOO');
        self::assertInstanceOf(EnumValueDefinition::class, $foo);
        self::assertSame('FOO', $foo->value);

        $bar = $enumType->getValue('BAR');
        self::assertInstanceOf(EnumValueDefinition::class, $bar);
        self::assertSame('BAR', $bar->value);
    }

    /** @see it('rejects an Enum type with invalid name', () => { */
    public function testRejectsAnEnumTypeWithInvalidName(): void
    {
        $enumType = new EnumType([
            'name' => 'bad-name',
            'values' => [],
        ]);

        self::expectExceptionObject(new Error(
            'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name" does not.'
        ));
        $enumType->assertValid();
    }

    /** @see it('accepts a well defined Enum type with internal value definition') */
    public function testAcceptsAWellDefinedEnumTypeWithInternalValueDefinition(): void
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => ['value' => 10],
                'BAR' => ['value' => 20],
            ],
        ]);

        $foo = $enumType->getValue('FOO');
        self::assertInstanceOf(EnumValueDefinition::class, $foo);
        self::assertSame(10, $foo->value);

        $bar = $enumType->getValue('BAR');
        self::assertInstanceOf(EnumValueDefinition::class, $bar);
        self::assertSame(20, $bar->value);
    }

    /** @see it('rejects an Enum type with incorrectly typed value definition') */
    public function testRejectsAnEnumTypeWithIncorrectlyTypedValues(): void
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [['FOO' => 10]],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'SomeEnum values must be an array with value names as keys or values.'
        ));
        $enumType->assertValid();
    }

    public function testAcceptsACallableAsEnumValues(): void
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => static function (): iterable {
                yield 'FOO' => ['value' => 10];
                yield 'BAR' => ['value' => 20];
            },
        ]);

        $foo = $enumType->getValue('FOO');
        self::assertInstanceOf(EnumValueDefinition::class, $foo);
        self::assertSame(10, $foo->value);

        $bar = $enumType->getValue('BAR');
        self::assertInstanceOf(EnumValueDefinition::class, $bar);
        self::assertSame(20, $bar->value);
    }

    /** @see it('defines an object type with deprecated field') */
    public function testDefinesAnObjectTypeWithDeprecatedField(): void
    {
        $TypeWithDeprecatedField = new ObjectType([
            'name' => 'foo',
            'fields' => [
                'bar' => [
                    'type' => Type::string(),
                    'deprecationReason' => 'A terrible reason',
                ],
            ],
        ]);

        $field = $TypeWithDeprecatedField->getField('bar');

        self::assertEquals(Type::string(), $field->getType());
        self::assertEquals(true, $field->isDeprecated());
        self::assertSame('A terrible reason', $field->deprecationReason);
        self::assertSame('bar', $field->name);
        self::assertSame([], $field->args);
    }

    /** @see it('includes nested input objects in the map') */
    public function testIncludesNestedInputObjectInTheMap(): void
    {
        $nestedInputObject = new InputObjectType([
            'name' => 'NestedInputObject',
            'fields' => ['value' => ['type' => Type::string()]],
        ]);
        $someInputObject = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => ['nested' => ['type' => $nestedInputObject]],
        ]);
        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $someInputObject]],
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation,
        ]);
        self::assertSame($nestedInputObject, $schema->getType('NestedInputObject'));
    }

    /** @see it('includes interface possible types in the type map') */
    public function testIncludesInterfaceSubtypesInTheTypeMap(): void
    {
        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
        ]);

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
            'interfaces' => [$someInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface],
                ],
            ]),
            'types' => [$someSubtype],
        ]);
        self::assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /** @see it('includes interfaces' thunk subtypes in the type map') */
    public function testIncludesInterfacesThunkSubtypesInTheTypeMap(): void
    {
        $someInterface = null;

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
            'interfaces' => static function () use (&$someInterface): array {
                return [$someInterface];
            },
        ]);

        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface],
                ],
            ]),
            'types' => [$someSubtype],
        ]);

        self::assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /** @see it('stringifies simple types') */
    public function testStringifiesSimpleTypes(): void
    {
        self::assertSame('Int', (string) Type::int());
        self::assertSame('Article', (string) $this->blogArticle);

        self::assertSame('Interface', (string) $this->interfaceType);
        self::assertSame('Union', (string) $this->unionType);
        self::assertSame('Enum', (string) $this->enumType);
        self::assertSame('InputObject', (string) $this->inputObjectType);
        self::assertSame('Object', (string) $this->objectType);

        self::assertSame('Int!', (string) new NonNull(Type::int()));
        self::assertSame('[Int]', (string) new ListOfType(Type::int()));
        self::assertSame('[Int]!', (string) new NonNull(new ListOfType(Type::int())));
        self::assertSame('[Int!]', (string) new ListOfType(new NonNull(Type::int())));
        self::assertSame('[[Int]]', (string) new ListOfType(new ListOfType(Type::int())));
    }

    /** @see it('JSON stringifies simple types') */
    public function testJSONStringifiesSimpleTypes(): void
    {
        self::assertSame('"Int"', json_encode(Type::int(), JSON_THROW_ON_ERROR));
        self::assertSame('"Article"', json_encode($this->blogArticle, JSON_THROW_ON_ERROR));
        self::assertSame('"Interface"', json_encode($this->interfaceType, JSON_THROW_ON_ERROR));
        self::assertSame('"Union"', json_encode($this->unionType, JSON_THROW_ON_ERROR));
        self::assertSame('"Enum"', json_encode($this->enumType, JSON_THROW_ON_ERROR));
        self::assertSame('"InputObject"', json_encode($this->inputObjectType, JSON_THROW_ON_ERROR));
        self::assertSame('"Int!"', json_encode(Type::nonNull(Type::int()), JSON_THROW_ON_ERROR));
        self::assertSame('"[Int]"', json_encode(Type::listOf(Type::int()), JSON_THROW_ON_ERROR));
        self::assertSame('"[Int]!"', json_encode(Type::nonNull(Type::listOf(Type::int())), JSON_THROW_ON_ERROR));
        self::assertSame('"[Int!]"', json_encode(Type::listOf(Type::nonNull(Type::int())), JSON_THROW_ON_ERROR));
        self::assertSame('"[[Int]]"', json_encode(Type::listOf(Type::listOf(Type::int())), JSON_THROW_ON_ERROR));
    }

    /** @see it('identifies input types') */
    public function testIdentifiesInputTypes(): void
    {
        $expected = [
            [Type::int(), true],
            [$this->objectType, false],
            [$this->interfaceType, false],
            [$this->unionType, false],
            [$this->enumType, true],
            [$this->inputObjectType, true],

            [Type::boolean(), true],
            [Type::float(), true],
            [Type::id(), true],
            [Type::int(), true],
            [Type::listOf(Type::string()), true],
            [Type::listOf($this->objectType), false],
            [Type::nonNull(Type::string()), true],
            [Type::nonNull($this->objectType), false],
            [Type::string(), true],
        ];

        foreach ($expected as $entry) {
            self::assertSame(
                $entry[1],
                Type::isInputType($entry[0]),
                "Type {$entry[0]} was detected incorrectly"
            );
        }
    }

    /** @see it('identifies output types') */
    public function testIdentifiesOutputTypes(): void
    {
        $expected = [
            [Type::int(), true],
            [$this->objectType, true],
            [$this->interfaceType, true],
            [$this->unionType, true],
            [$this->enumType, true],
            [$this->inputObjectType, false],

            [Type::boolean(), true],
            [Type::float(), true],
            [Type::id(), true],
            [Type::int(), true],
            [Type::listOf(Type::string()), true],
            [Type::listOf($this->objectType), true],
            [Type::nonNull(Type::string()), true],
            [Type::nonNull($this->objectType), true],
            [Type::string(), true],
        ];

        foreach ($expected as $entry) {
            self::assertSame(
                $entry[1],
                Type::isOutputType($entry[0]),
                "Type {$entry[0]} was detected incorrectly"
            );
        }
    }

    /** @see it('allows a thunk for Union member types') */
    public function testAllowsThunkForUnionTypes(): void
    {
        $union = new UnionType([
            'name' => 'ThunkUnion',
            'types' => fn (): array => [$this->objectType],
        ]);
        $types = $union->getTypes();

        self::assertCount(1, $types);
        self::assertSame($this->objectType, $types[0]);
    }

    public function testAllowsRecursiveDefinitions(): void
    {
        // See https://github.com/webonyx/graphql-php/issues/16
        $node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
            ],
        ]);

        /** @var ObjectType|null $blog */
        $blog = null;

        $user = new ObjectType([
            'name' => 'User',
            'fields' => static function () use (&$blog): array {
                self::assertNotNull($blog, 'Blog type is expected to be defined at this point, but it is null');

                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'blogs' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($blog)))],
                ];
            },
            'interfaces' => static fn (): array => [$node],
        ]);

        $blog = new ObjectType([
            'name' => 'Blog',
            'fields' => static fn (): array => [
                'id' => ['type' => Type::nonNull(Type::id())],
                'owner' => ['type' => Type::nonNull($user)],
            ],
            'interfaces' => static fn (): array => [$node],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'node' => ['type' => $node],
                ],
            ]),
            'types' => [$user, $blog],
        ]);

        $schema->getType('Blog');

        self::assertSame([$node], $blog->getInterfaces());
        self::assertSame([$node], $user->getInterfaces());

        $blogFieldReturnType = $user->getField('blogs')->getType();
        self::assertInstanceOf(NonNull::class, $blogFieldReturnType);
        self::assertSame($blog, $blogFieldReturnType->getInnermostType());

        $ownerFieldReturnType = $blog->getField('owner')->getType();
        self::assertInstanceOf(NonNull::class, $ownerFieldReturnType);
        self::assertSame($user, $ownerFieldReturnType->getInnermostType());
    }

    public function testInputObjectTypeAllowsRecursiveDefinitions(): void
    {
        $called = false;
        $inputObject = new InputObjectType([
            'name' => 'InputObject',
            'fields' => static function () use (&$inputObject, &$called): array {
                $called = true;

                return [
                    'value' => ['type' => Type::string()],
                    'nested' => ['type' => $inputObject],
                ];
            },
        ]);

        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $inputObject]],
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation,
        ]);

        self::assertSame($inputObject, $schema->getType('InputObject'));
        self::assertTrue($called);
        self::assertCount(2, $inputObject->getFields());
        self::assertSame($inputObject->getField('nested')->getType(), $inputObject);

        $input = $someMutation->getField('mutateSomething')->getArg('input');
        self::assertInstanceOf(Argument::class, $input);
        self::assertSame($input->getType(), $inputObject);
    }

    public function testInterfaceTypeAllowsRecursiveDefinitions(): void
    {
        $called = false;
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => static function () use (&$interface, &$called): array {
                $called = true;

                return [
                    'value' => ['type' => Type::string()],
                    'nested' => ['type' => $interface],
                ];
            },
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => ['type' => $interface],
            ],
        ]);

        $schema = new Schema(['query' => $query]);

        self::assertSame($interface, $schema->getType('SomeInterface'));
        self::assertTrue($called);
        self::assertCount(2, $interface->getFields());
        self::assertSame($interface->getField('nested')->getType(), $interface);
        self::assertSame($interface->getField('value')->getType(), Type::string());
    }

    public function testAllowsShorthandFieldDefinition(): void
    {
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => static function () use (&$interface): array {
                return [
                    'value' => Type::string(),
                    'nested' => $interface,
                    'withArg' => [
                        'type' => Type::string(),
                        'args' => [
                            'arg1' => Type::int(),
                        ],
                    ],
                ];
            },
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => ['test' => $interface],
        ]);

        $schema = new Schema(['query' => $query]);

        /** @var InterfaceType $SomeInterface */
        $SomeInterface = $schema->getType('SomeInterface');

        $valueField = $SomeInterface->getField('value');
        self::assertEquals(Type::string(), $valueField->getType());

        $nestedField = $SomeInterface->getField('nested');
        self::assertEquals($interface, $nestedField->getType());

        $withArg = $SomeInterface->getField('withArg');
        self::assertEquals(Type::string(), $withArg->getType());

        self::assertSame('arg1', $withArg->args[0]->name);
        self::assertEquals(Type::int(), $withArg->args[0]->getType());

        /** @var ObjectType $Query */
        $Query = $schema->getType('Query');
        $testField = $Query->getField('test');
        self::assertEquals($interface, $testField->getType());
        self::assertSame('test', $testField->name);
    }

    public function testInfersNameFromClassname(): void
    {
        $myObj = new MyCustomType();
        self::assertSame('MyCustom', $myObj->name);

        $otherCustom = new OtherCustom();
        self::assertSame('OtherCustom', $otherCustom->name);
    }

    /** @dataProvider providerPropertyAccessMethodsGiveCorrectValues */
    public function testPropertyAccessMethodsGiveCorrectValues(NamedType $type): void
    {
        self::assertSame($type->name, $type->name());
        self::assertSame($type->description, $type->description());
        self::assertSame($type->astNode, $type->astNode());
        self::assertSame($type->extensionASTNodes, $type->extensionASTNodes());
    }

    /**
     * @throws InvariantViolation
     *
     * @return \Generator<array-key, array{NamedType}>
     */
    public static function providerPropertyAccessMethodsGiveCorrectValues(): \Generator
    {
        $description = 'To the quartered rhubarb add lobster, lettuce, eggs sauce and bitter strudel.';

        yield [new EnumType([
            'name' => 'EnumType',
            'description' => $description,
            'values' => [
                'A' => ['value' => 1],
            ],
        ])];

        yield [new InputObjectType([
            'name' => 'InputObjectType',
            'description' => $description,
            'fields' => ['a' => Type::string()],
        ])];

        yield [new InterfaceType([
            'name' => 'InterfaceType',
            'description' => $description,
            'fields' => ['a' => Type::string()],
        ])];

        yield [new ObjectType([
            'name' => 'ObjectType',
            'description' => $description,
            'fields' => ['a' => Type::string()],
        ])];

        yield [new CustomScalarType([
            'name' => 'ScalarType',
            'description' => $description,
        ])];

        yield [new UnionType([
            'name' => 'InterfaceType',
            'description' => $description,
            'types' => [],
        ])];
    }

    /** @see it('accepts an Object type with a field function') */
    public function testAcceptsAnObjectTypeWithAFieldFunction(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => static fn (): array => [
                'f' => [
                    'type' => Type::string(),
                ],
            ],
        ]);
        $objType->assertValid();
        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    public function testAcceptsAnObjectTypeWithShorthandNotationForFields(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => Type::string(),
            ],
        ]);
        $objType->assertValid();
        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /** @see it('rejects an Object type field with undefined config') */
    public function testRejectsAnObjectTypeFieldWithUndefinedConfig(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => ['f' => null],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject.f field config must be an array, but got: null'
        );
        $objType->getFields();
    }

    /** @see it('rejects an Object type with incorrectly typed fields') */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedFields(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [['field' => Type::string()]],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'SomeObject fields must be an associative array with field names as keys or a function which returns such an array.'
        ));
        $objType->getFields();
    }

    /** @see it('rejects an Object type with a field function that returns incorrect type') */
    public function testRejectsAnObjectTypeWithAFieldFunctionThatReturnsIncorrectType(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => static fn (): array => [
                ['field' => Type::string()],
            ],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'SomeObject fields must be an associative array with field names as keys or a function which returns such an array.'
        ));
        $objType->getFields();
    }

    // Field arg config must be object

    /** @see it('accepts an Object type with field args') */
    public function testAcceptsAnObjectTypeWithFieldArgs(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => Type::string(),
                    'args' => [
                        'goodArg' => [
                            'type' => Type::string(),
                            'deprecationReason' => 'Just because',
                        ],
                    ],
                ],
            ],
        ]);
        $objType->assertValid();
        $argument = $objType->getField('goodField')->getArg('goodArg');
        self::assertInstanceOf(Argument::class, $argument);
        self::assertTrue($argument->isDeprecated());
        self::assertSame('Just because', $argument->deprecationReason);
    }

    // Object interfaces must be array

    /** @see it('accepts an Object type with array interfaces') */
    public function testAcceptsAnObjectTypeWithArrayInterfaces(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => [$this->interfaceType],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        self::assertSame([$this->interfaceType], $objType->getInterfaces());
    }

    /** @see it('accepts an Object type with interfaces as a function returning an array') */
    public function testAcceptsAnObjectTypeWithInterfacesAsAFunctionReturningAnArray(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => fn (): array => [$this->interfaceType],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        self::assertSame([$this->interfaceType], $objType->getInterfaces());
    }

    /** @see it('rejects an Object type with incorrectly typed interfaces') */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedInterfaces(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => new \stdClass(),
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'SomeObject interfaces must be an iterable or a callable which returns an iterable.'
        ));
        $objType->assertValid();
    }

    /** @see it('rejects an Object type with interfaces as a function returning an incorrect type') */
    public function testRejectsAnObjectTypeWithInterfacesAsAFunctionReturningAnIncorrectType(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => static fn (): \stdClass => new \stdClass(),
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject interfaces must be an iterable or a callable which returns an iterable.'
        );
        $objType->assertValid();
    }

    // Type System: Object fields must have valid resolve values

    /** @see it('accepts a lambda as an Object field resolver') */
    public function testAcceptsALambdaAsAnObjectFieldResolver(): void
    {
        $this->schemaWithObjectWithFieldResolver(static fn () => null);
        $this->assertDidNotCrash();
    }

    /**
     * @param mixed $resolveValue
     *
     * @throws Error
     * @throws InvariantViolation
     */
    private function schemaWithObjectWithFieldResolver($resolveValue): Schema
    {
        $BadResolverType = new ObjectType([
            'name' => 'BadResolver',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'resolve' => $resolveValue,
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadResolverType],
                ],
            ]),
        ]);
        $schema->assertValid();

        return $schema;
    }

    /** @see it('rejects an empty Object field resolver') */
    public function testRejectsAnEmptyObjectFieldResolver(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'BadResolver.badField field resolver must be a function if provided, but got: []'
        );
        $this->schemaWithObjectWithFieldResolver([]);
    }

    /** @see it('rejects a constant scalar value resolver') */
    public function testRejectsAConstantScalarValueResolver(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'BadResolver.badField field resolver must be a function if provided, but got: 0'
        );
        $this->schemaWithObjectWithFieldResolver(0);
    }

    // Type System: Interface types must be resolvable

    /** @see it('accepts an Interface type defining resolveType') */
    public function testAcceptsAnInterfaceTypeDefiningResolveType(): void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('accepts an Interface type with an array of interfaces') */
    public function testAcceptsAnInterfaceTypeWithAnArrayOfInterfaces(): void
    {
        $interfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => [],
            'interfaces' => [$this->interfaceType],
        ]);
        self::assertSame([$this->interfaceType], $interfaceType->getInterfaces());
    }

    /** @see it('accepts an Interface type with interfaces as a function returning an array') */
    public function testAcceptsAnInterfaceTypeWithInterfacesAsAFunctionReturningAnArray(): void
    {
        $interfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => [],
            'interfaces' => fn (): array => [$this->interfaceType],
        ]);
        self::assertSame([$this->interfaceType], $interfaceType->getInterfaces());
    }

    /** @see it('rejects an Interface type with incorrectly typed interfaces') */
    public function testRejectsAnInterfaceTypeWithIncorrectlyTypedInterfaces(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $objType = new InterfaceType([
            'name' => 'AnotherInterface',
            'interfaces' => new \stdClass(),
            'fields' => [],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'AnotherInterface interfaces must be an iterable or a callable which returns an iterable.'
        ));
        $objType->assertValid();
    }

    /** @see it('rejects an Interface type with interfaces as a function returning an incorrect type') */
    public function testRejectsAnInterfaceTypeWithInterfacesAsAFunctionReturningAnIncorrectType(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $objType = new ObjectType([
            'name' => 'AnotherInterface',
            'interfaces' => static fn (): \stdClass => new \stdClass(),
            'fields' => [],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherInterface interfaces must be an iterable or a callable which returns an iterable.'
        );
        $objType->assertValid();
    }

    /**
     * @param Type&NamedType $type
     *
     * @throws Error
     * @throws InvariantViolation
     */
    private function schemaWithFieldType(Type $type): Schema
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'field' => ['type' => $type],
                ],
            ]),
            'types' => [$type],
        ]);
        $schema->assertValid();

        return $schema;
    }

    /** @see it('accepts an Interface with implementing type defining isTypeOf') */
    public function testAcceptsAnInterfaceWithImplementingTypeDefiningIsTypeOf(): void
    {
        $InterfaceTypeWithoutResolveType = new InterfaceType([
            'name' => 'InterfaceTypeWithoutResolveType',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$InterfaceTypeWithoutResolveType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('accepts an Interface type defining resolveType with implementing type defining isTypeOf') */
    public function testAcceptsAnInterfaceTypeDefiningResolveTypeWithImplementingTypeDefiningIsTypeOf(): void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('rejects an Interface type with an incorrect type for resolveType') */
    public function testRejectsAnInterfaceTypeWithAnIncorrectTypeForResolveType(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $type = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => new \stdClass(),
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Slightly deviating from the reference implementation in order to be idiomatic for PHP
        $this->expectExceptionObject(new InvariantViolation('AnotherInterface must provide "resolveType" as null or a callable, but got: instance of stdClass.'));
        $type->assertValid();
    }

    // Type System: Union types must be resolvable

    /** @see it('accepts a Union type defining resolveType') */
    public function testAcceptsAUnionTypeDefiningResolveType(): void
    {
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('accepts a Union of Object types defining isTypeOf') */
    public function testAcceptsAUnionOfObjectTypesDefiningIsTypeOf(): void
    {
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('accepts a Union type defining resolveType of Object types defining isTypeOf') */
    public function testAcceptsAUnionTypeDefiningResolveTypeOfObjectTypesDefiningIsTypeOf(): void
    {
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('rejects an Union type with an incorrect type for resolveType') */
    public function testRejectsAnUnionTypeWithAnIncorrectTypeForResolveType(): void
    {
        // Slightly deviating from the reference implementation in order to be idiomatic for PHP
        $this->expectExceptionObject(new InvariantViolation('SomeUnion must provide "resolveType" as null or a callable, but got: instance of stdClass.'));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new UnionType([
                'name' => 'SomeUnion',
                'resolveType' => new \stdClass(),
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /** @see it('accepts a Scalar type defining serialize') */
    public function testAcceptsAScalarTypeDefiningSerialize(): void
    {
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => static fn () => null,
            ])
        );
        $this->assertDidNotCrash();
    }

    // Type System: Scalar types must be serializable

    /** @see it('rejects a Scalar type defining serialize with an incorrect type') */
    public function testRejectsAScalarTypeDefiningSerializeWithAnIncorrectType(): void
    {
        $this->expectExceptionObject(new InvariantViolation('SomeScalar must provide "serialize" as a callable if given, but got: instance of stdClass.'));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => new \stdClass(),
            ])
        );
    }

    /** @see it('accepts a Scalar type defining parseValue and parseLiteral') */
    public function testAcceptsAScalarTypeDefiningParseValueAndParseLiteral(): void
    {
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => static function (): void {},
                'parseValue' => static function (): void {},
                'parseLiteral' => static function (): void {},
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('rejects a Scalar type defining parseValue but not parseLiteral') */
    public function testRejectsAScalarTypeDefiningParseValueButNotParseLiteral(): void
    {
        $this->expectExceptionObject(new InvariantViolation('SomeScalar must provide both "parseValue" and "parseLiteral" functions to work as an input type.'));

        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => static function (): void {},
                'parseValue' => static function (): void {},
            ])
        );
    }

    /** @see it('rejects a Scalar type defining parseLiteral but not parseValue') */
    public function testRejectsAScalarTypeDefiningParseLiteralButNotParseValue(): void
    {
        $this->expectExceptionObject(new InvariantViolation('SomeScalar must provide both "parseValue" and "parseLiteral" functions to work as an input type.'));

        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => static function (): void {},
                'parseLiteral' => static function (): void {},
            ])
        );
    }

    /** @see it('rejects a Scalar type defining parseValue and parseLiteral with an incorrect type') */
    public function testRejectsAScalarTypeDefiningParseValueAndParseLiteralWithAnIncorrectType(): void
    {
        $this->expectExceptionObject(new InvariantViolation('SomeScalar must provide "parseValue" as a callable if given, but got: instance of stdClass.'));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => static function (): void {},
                'parseValue' => new \stdClass(),
                'parseLiteral' => new \stdClass(),
            ])
        );
    }

    /** @see it('accepts an Object type with an isTypeOf function') */
    public function testAcceptsAnObjectTypeWithAnIsTypeOfFunction(): void
    {
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'AnotherObject',
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
        $this->assertDidNotCrash();
    }

    // Type System: Object types must be assertable

    /** @see it('rejects an Object type with an incorrect type for isTypeOf') */
    public function testRejectsAnObjectTypeWithAnIncorrectTypeForIsTypeOf(): void
    {
        // Slightly deviating from the reference implementation in order to be idiomatic for PHP
        $this->expectExceptionObject(new InvariantViolation('AnotherObject must provide "isTypeOf" as null or a callable, but got: instance of stdClass.'));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new ObjectType([
                'name' => 'AnotherObject',
                'isTypeOf' => new \stdClass(),
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /** @see it('accepts a Union type with array types') */
    public function testAcceptsAUnionTypeWithArrayTypes(): void
    {
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
        $this->assertDidNotCrash();
    }

    // Type System: Union types must be array

    /** @see it('accepts a Union type with function returning an array of types') */
    public function testAcceptsAUnionTypeWithFunctionReturningAnArrayOfTypes(): void
    {
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => fn (): array => [$this->objectType],
            ])
        );
        $this->assertDidNotCrash();
    }

    /** @see it('rejects a Union type without types') */
    public function testRejectsAUnionTypeWithoutTypes(): void
    {
        $this->expectExceptionObject(new InvariantViolation(
            'Must provide iterable of types or a callable which returns such an iterable for Union SomeUnion'
        ));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new UnionType(['name' => 'SomeUnion'])
        );
    }

    /** @see it('rejects a Union type with incorrectly typed types') */
    public function testRejectsAUnionTypeWithIncorrectlyTypedTypes(): void
    {
        $this->expectExceptionObject(new InvariantViolation(
            'Must provide iterable of types or a callable which returns such an iterable for Union SomeUnion'
        ));

        $this->schemaWithFieldType(
            // @phpstan-ignore-next-line intentionally wrong
            new UnionType([
                'name' => 'SomeUnion',
                'types' => (object) ['test' => $this->objectType],
            ])
        );
    }

    // Type System: Input Objects must have fields

    /** @see it('accepts an Input Object type with fields') */
    public function testAcceptsAnInputObjectTypeWithFields(): void
    {
        $fieldName = 'f';
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                $fieldName => [
                    'type' => Type::string(),
                    'deprecationReason' => 'Just because',
                ],
            ],
        ]);

        $inputObjType->assertValid();
        $field = $inputObjType->getField($fieldName);
        self::assertSame(Type::string(), $field->getType());
        self::assertTrue($field->isDeprecated());
        self::assertSame('Just because', $field->deprecationReason);
    }

    /** @see it('accepts an Input Object type with a field function') */
    public function testAcceptsAnInputObjectTypeWithAFieldFunction(): void
    {
        $fieldName = 'f';
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => static fn (): array => [
                $fieldName => [
                    'type' => Type::string(),
                    'deprecationReason' => 'Just because',
                ],
            ],
        ]);

        $inputObjType->assertValid();
        $field = $inputObjType->getField($fieldName);
        self::assertSame(Type::string(), $inputObjType->getField($fieldName)->getType());
        self::assertTrue($field->isDeprecated());
        self::assertSame('Just because', $field->deprecationReason);
    }

    /** @see it('accepts an Input Object type with a field type function') */
    public function testAcceptsAnInputObjectTypeWithAFieldTypeFunction(): void
    {
        $fieldName = 'f';
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                $fieldName => static fn (): Type => Type::string(),
            ],
        ]);

        $inputObjType->assertValid();
        self::assertSame(Type::string(), $inputObjType->getField($fieldName)->getType());
    }

    /** @see it('rejects an Input Object type with incorrect fields') */
    public function testRejectsAnInputObjectTypeWithIncorrectFields(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => 123,
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an iterable or a callable which returns an iterable, got: 123.'
        );
        $inputObjType->assertValid();
    }

    /** @see it('rejects an Input Object type with fields function that returns incorrect type') */
    public function testRejectsAnInputObjectTypeWithFieldsFunctionThatReturnsIncorrectType(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => static fn () => null,
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an iterable or a callable which returns an iterable, got: null.'
        );
        $inputObjType->assertValid();
    }

    /** @see it('rejects an Input Object type with resolvers') */
    public function testRejectsAnInputObjectTypeWithResolvers(): void
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): int => 0,
                ],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject.f field has a resolve property, but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    public function testAcceptsAnInputObjectTypeWithCallableReturningAConfigArray(): void
    {
        $fieldName = 'f';
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                $fieldName => static fn (): array => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $inputObjType->assertValid();
        self::assertSame(Type::string(), $inputObjType->getField($fieldName)->getType());
    }

    public function testInputObjectKnowsItsFields(): void
    {
        $fieldName = 'f';
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                $fieldName => Type::string(),
            ],
        ]);

        self::assertTrue($inputObjType->hasField($fieldName));
        self::assertFalse($inputObjType->hasField('unknown'));

        self::assertInstanceOf(InputObjectField::class, $inputObjType->findField($fieldName));
        self::assertNull($inputObjType->findField('unknown'));

        self::expectExceptionObject(new InvariantViolation('Field "unknown" is not defined for type "SomeInputObject"'));
        $inputObjType->getField('unknown');
    }

    public function testAllowsAnInputObjectTypeWithInputObjectField(): void
    {
        $fieldName = 'f';
        $objType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                new InputObjectField([
                    'name' => $fieldName,
                    'type' => Type::string(),
                ]),
            ],
        ]);

        $objType->assertValid();
        self::assertSame(Type::string(), $objType->getField($fieldName)->getType());
    }

    public function testRejectsAnInputObjectTypeWithIncorrectlyTypedFields(): void
    {
        $objType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an associative array with field names as keys, an array of arrays with a name attribute, or a callable which returns one of those.'
        );
        $objType->assertValid();
    }

    // Type System: Input Object fields must not have resolvers

    /** @see it('rejects an Input Object type with resolver constant') */
    public function testRejectsAnInputObjectTypeWithResolverConstant(): void
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => Type::string(),
                    'resolve' => new \stdClass(),
                ],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject.f field has a resolve property, but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    // Type System: A Schema must contain uniquely named types

    /** @see it('rejects a Schema which redefines a built-in type') */
    public function testRejectsASchemaWhichRedefinesABuiltInType(): void
    {
        $FakeString = new CustomScalarType([
            'name' => 'String',
            'serialize' => static function (): void {},
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'normal' => ['type' => Type::string()],
                'fake' => ['type' => $FakeString],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "String" '
            . '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        );
        $schema = new Schema(['query' => $QueryType]);
        $schema->assertValid();
    }

    /** @see it('rejects a Schema when a provided type has no name') */
    public function testRejectsASchemaWhenAProvidedTypeHasNoName(): void
    {
        self::markTestSkipped('Our types are more strict by default, given we use classes');

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
        ]);

        new Schema([
            'query' => $QueryType,
            'types' => [new \stdClass()],
        ]);
    }

    /** @see it('rejects a Schema which defines an object type twice') */
    public function testRejectsASchemaWhichDefinesAnObjectTypeTwice(): void
    {
        $A = new ObjectType([
            'name' => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $B = new ObjectType([
            'name' => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'a' => ['type' => $A],
                'b' => ['type' => $B],
            ],
        ]);

        $schema = new Schema(['query' => $QueryType]);

        $this->expectExceptionObject(new InvariantViolation(
            'Schema must contain unique named types but contains multiple types named "SameName" (see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        ));
        $schema->assertValid();
    }

    /** @see it('rejects a Schema which defines fields with conflicting types' */
    public function testRejectsASchemaWhichDefinesFieldsWithConflictingTypes(): void
    {
        $fields = [
            'f' => [
                'type' => Type::string(),
            ],
        ];

        $A = new ObjectType([
            'name' => 'SameName',
            'fields' => $fields,
        ]);

        $B = new ObjectType([
            'name' => 'SameName',
            'fields' => $fields,
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'a' => ['type' => $A],
                'b' => ['type' => $B],
            ],
        ]);

        $schema = new Schema(['query' => $QueryType]);

        $this->expectExceptionObject(new InvariantViolation(
            'Schema must contain unique named types but contains multiple types named "SameName" (see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        ));
        $schema->assertValid();
    }

    public function testRejectsASchemaWithSameNamedObjectsImplementingAnInterface(): void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $FirstBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $SecondBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'iface' => ['type' => $AnotherInterface],
            ],
        ]);

        $schema = new Schema([
            'query' => $QueryType,
            'types' => [$FirstBadObject, $SecondBadObject],
        ]);

        $this->expectExceptionMessageMatches('/Schema must contain unique named types but contains multiple types named "BadObject"/');
        $schema->assertValid();
    }
}
