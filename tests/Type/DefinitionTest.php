<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Tests\Type\TestClasses\MyCustomType;
use GraphQL\Tests\Type\TestClasses\OtherCustom;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function count;
use function json_encode;
use function sprintf;

class DefinitionTest extends TestCase
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
        $this->objectType      = new ObjectType(['name' => 'Object', 'fields' => ['tmp' => Type::string()]]);
        $this->interfaceType   = new InterfaceType(['name' => 'Interface']);
        $this->unionType       = new UnionType(['name' => 'Union', 'types' => [$this->objectType]]);
        $this->enumType        = new EnumType(['name' => 'Enum']);
        $this->inputObjectType = new InputObjectType(['name' => 'InputObject']);

        $this->objectWithIsTypeOf = new ObjectType([
            'name'   => 'ObjectWithIsTypeOf',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->scalarType = new CustomScalarType([
            'name'         => 'Scalar',
            'serialize'    => static function (): void {
            },
            'parseValue'   => static function (): void {
            },
            'parseLiteral' => static function (): void {
            },
        ]);

        $this->blogImage = new ObjectType([
            'name'   => 'Image',
            'fields' => [
                'url'    => ['type' => Type::string()],
                'width'  => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $this->blogAuthor = new ObjectType([
            'name'   => 'Author',
            'fields' => function (): array {
                return [
                    'id'            => ['type' => Type::string()],
                    'name'          => ['type' => Type::string()],
                    'pic'           => [
                        'type' => $this->blogImage,
                        'args' => [
                            'width'  => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                    ],
                    'recentArticle' => $this->blogArticle,
                ];
            },
        ]);

        $this->blogArticle = new ObjectType([
            'name'   => 'Article',
            'fields' => [
                'id'          => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author'      => ['type' => $this->blogAuthor],
                'title'       => ['type' => Type::string()],
                'body'        => ['type' => Type::string()],
            ],
        ]);

        $this->blogQuery = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'article' => [
                    'type' => $this->blogArticle,
                    'args' => [
                        'id' => ['type' => Type::string()],
                    ],
                ],
                'feed'    => ['type' => new ListOfType($this->blogArticle)],
            ],
        ]);

        $this->blogMutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => [
                'writeArticle' => ['type' => $this->blogArticle],
            ],
        ]);

        $this->blogSubscription = new ObjectType([
            'name'   => 'Subscription',
            'fields' => [
                'articleSubscribe' => [
                    'args' => ['id' => ['type' => Type::string()]],
                    'type' => $this->blogArticle,
                ],
            ],
        ]);
    }

    // Type System: Example

    /**
     * @see it('defines a query only schema')
     */
    public function testDefinesAQueryOnlySchema(): void
    {
        $blogSchema = new Schema([
            'query' => $this->blogQuery,
        ]);

        self::assertSame($blogSchema->getQueryType(), $this->blogQuery);

        $articleField = $this->blogQuery->getField('article');
        self::assertSame($articleField->getType(), $this->blogArticle);
        self::assertSame($articleField->getType()->name, 'Article');
        self::assertSame($articleField->name, 'article');

        /** @var ObjectType $articleFieldType */
        $articleFieldType = $articleField->getType();
        $titleField       = $articleFieldType->getField('title');

        self::assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $titleField);
        self::assertSame('title', $titleField->name);
        self::assertSame(Type::string(), $titleField->getType());

        $authorField = $articleFieldType->getField('author');
        self::assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $authorField);

        /** @var ObjectType $authorFieldType */
        $authorFieldType = $authorField->getType();
        self::assertSame($this->blogAuthor, $authorFieldType);

        $recentArticleField = $authorFieldType->getField('recentArticle');
        self::assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $recentArticleField);
        self::assertSame($this->blogArticle, $recentArticleField->getType());

        $feedField = $this->blogQuery->getField('feed');
        self::assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $feedField);

        /** @var ListOfType $feedFieldType */
        $feedFieldType = $feedField->getType();
        self::assertInstanceOf('GraphQL\Type\Definition\ListOfType', $feedFieldType);
        self::assertSame($this->blogArticle, $feedFieldType->getWrappedType());
    }

    public function testFieldDefinitionPublicTypeGetDeprecation(): void
    {
        $fieldDef = FieldDefinition::create([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public getter for \'type\' on FieldDefinition has been deprecated and will be removed in the next major version. Please update your code to use the \'getType\' method.');
        });

        self::assertFalse(isset($fieldDef->nonExistentProp));
        $fieldDef->nonExistentProp = 'someValue';
        self::assertTrue(isset($fieldDef->nonExistentProp));

        // @phpstan-ignore-next-line type is private, but we're allowing its access temporarily via a magic method
        $type = $fieldDef->type;
    }

    public function testFieldDefinitionPublicTypeSetDeprecation(): void
    {
        $fieldDef = FieldDefinition::create([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public setter for \'type\' on FieldDefinition has been deprecated and will be removed in the next major version.');
        });

        // @phpstan-ignore-next-line type is private, but we're allowing its access temporarily via a magic method
        $fieldDef->type = Type::int();

        $fieldDef->nonExistentProp = 'someValue';
        self::assertEquals($fieldDef->nonExistentProp, 'someValue');
    }

    public function testFieldDefinitionPublicTypeIssetDeprecation(): void
    {
        $fieldDef = FieldDefinition::create([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public getter for \'type\' on FieldDefinition has been deprecated and will be removed in the next major version. Please update your code to use the \'getType\' method.');
        });

        isset($fieldDef->type);
    }

    public function testInputObjectFieldPublicTypeGetDeprecation(): void
    {
        $fieldDef = new InputObjectField([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public getter for \'type\' on InputObjectField has been deprecated and will be removed in the next major version. Please update your code to use the \'getType\' method.');
        });

        // @phpstan-ignore-next-line type is private, but we're allowing its access temporarily via a magic method
        $type = $fieldDef->type;
    }

    public function testInputObjectFieldPublicTypeSetDeprecation(): void
    {
        $fieldDef = new InputObjectField([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public setter for \'type\' on InputObjectField has been deprecated and will be removed in the next major version.');
        });

        // @phpstan-ignore-next-line type is private, but we're allowing its access temporarily via a magic method
        $fieldDef->type = Type::int();
    }

    public function testInputObjectFieldPublicTypeIssetDeprecation(): void
    {
        $fieldDef = new InputObjectField([
            'type' => Type::string(),
            'name' => 'GenericField',
        ]);

        Warning::setWarningHandler(static function ($message): void {
            self::assertEquals($message, 'The public getter for \'type\' on InputObjectField has been deprecated and will be removed in the next major version. Please update your code to use the \'getType\' method.');
        });

        isset($fieldDef->type);

        self::assertFalse(isset($fieldDef->nonExistentProp));
        $fieldDef->nonExistentProp = 'someValue';
        self::assertTrue(isset($fieldDef->nonExistentProp));
        self::assertEquals($fieldDef->nonExistentProp, 'someValue');
    }

    /**
     * @see it('defines a mutation schema')
     */
    public function testDefinesAMutationSchema(): void
    {
        $schema = new Schema([
            'query'    => $this->blogQuery,
            'mutation' => $this->blogMutation,
        ]);

        self::assertSame($this->blogMutation, $schema->getMutationType());
        $writeMutation = $this->blogMutation->getField('writeArticle');

        self::assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $writeMutation);
        self::assertSame($this->blogArticle, $writeMutation->getType());
        self::assertSame('Article', $writeMutation->getType()->name);
        self::assertSame('writeArticle', $writeMutation->name);
    }

    /**
     * @see it('defines a subscription schema')
     */
    public function testDefinesSubscriptionSchema(): void
    {
        $schema = new Schema([
            'query'        => $this->blogQuery,
            'subscription' => $this->blogSubscription,
        ]);

        self::assertEquals($this->blogSubscription, $schema->getSubscriptionType());

        $sub = $this->blogSubscription->getField('articleSubscribe');
        self::assertEquals($sub->getType(), $this->blogArticle);
        self::assertEquals($sub->getType()->name, 'Article');
        self::assertEquals($sub->name, 'articleSubscribe');
    }

    /**
     * @see it('defines an enum type with deprecated value')
     */
    public function testDefinesEnumTypeWithDeprecatedValue(): void
    {
        $enumTypeWithDeprecatedValue = new EnumType([
            'name'   => 'EnumWithDeprecatedValue',
            'values' => [
                'foo' => ['deprecationReason' => 'Just because'],
            ],
        ]);

        $value = $enumTypeWithDeprecatedValue->getValues()[0];

        self::assertArraySubset(
            [
                'name'              => 'foo',
                'description'       => null,
                'deprecationReason' => 'Just because',
                'value'             => 'foo',
                'astNode'           => null,
            ],
            (array) $value
        );

        self::assertEquals(true, $value->isDeprecated());
    }

    /**
     * @see it('defines an enum type with a value of `null` and `undefined`')
     */
    public function testDefinesAnEnumTypeWithAValueOfNullAndUndefined(): void
    {
        $EnumTypeWithNullishValue = new EnumType([
            'name'   => 'EnumWithNullishValue',
            'values' => [
                'NULL'      => ['value' => null],
                'UNDEFINED' => ['value' => null],
            ],
        ]);

        $expected = [
            [
                'name'              => 'NULL',
                'description'       => null,
                'deprecationReason' => null,
                'value'             => null,
                'astNode'           => null,
            ],
            [
                'name'              => 'UNDEFINED',
                'description'       => null,
                'deprecationReason' => null,
                'value'             => null,
                'astNode'           => null,
            ],
        ];

        $actual = $EnumTypeWithNullishValue->getValues();

        self::assertEquals(count($expected), count($actual));
        self::assertArraySubset($expected[0], (array) $actual[0]);
        self::assertArraySubset($expected[1], (array) $actual[1]);
    }

    /**
     * @see it('defines an object type with deprecated field')
     */
    public function testDefinesAnObjectTypeWithDeprecatedField(): void
    {
        $TypeWithDeprecatedField = new ObjectType([
            'name'   => 'foo',
            'fields' => [
                'bar' => [
                    'type'              => Type::string(),
                    'deprecationReason' => 'A terrible reason',
                ],
            ],
        ]);

        $field = $TypeWithDeprecatedField->getField('bar');

        self::assertEquals(Type::string(), $field->getType());
        self::assertEquals(true, $field->isDeprecated());
        self::assertEquals('A terrible reason', $field->deprecationReason);
        self::assertEquals('bar', $field->name);
        self::assertEquals([], $field->args);
    }

    /**
     * @see it('includes nested input objects in the map')
     */
    public function testIncludesNestedInputObjectInTheMap(): void
    {
        $nestedInputObject = new InputObjectType([
            'name'   => 'NestedInputObject',
            'fields' => ['value' => ['type' => Type::string()]],
        ]);
        $someInputObject   = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => ['nested' => ['type' => $nestedInputObject]],
        ]);
        $someMutation      = new ObjectType([
            'name'   => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $someInputObject]],
                ],
            ],
        ]);

        $schema = new Schema([
            'query'    => $this->blogQuery,
            'mutation' => $someMutation,
        ]);
        self::assertSame($nestedInputObject, $schema->getType('NestedInputObject'));
    }

    /**
     * @see it('includes interface possible types in the type map')
     */
    public function testIncludesInterfaceSubtypesInTheTypeMap(): void
    {
        $someInterface = new InterfaceType([
            'name'   => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
        ]);

        $someSubtype = new ObjectType([
            'name'       => 'SomeSubtype',
            'fields'     => [
                'f' => ['type' => Type::int()],
            ],
            'interfaces' => [$someInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface],
                ],
            ]),
            'types' => [$someSubtype],
        ]);
        self::assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /**
     * @see it('includes interfaces' thunk subtypes in the type map')
     */
    public function testIncludesInterfacesThunkSubtypesInTheTypeMap(): void
    {
        $someInterface = null;

        $someSubtype = new ObjectType([
            'name'       => 'SomeSubtype',
            'fields'     => [
                'f' => ['type' => Type::int()],
            ],
            'interfaces' => static function () use (&$someInterface): array {
                return [$someInterface];
            },
        ]);

        $someInterface = new InterfaceType([
            'name'   => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface],
                ],
            ]),
            'types' => [$someSubtype],
        ]);

        self::assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /**
     * @see it('stringifies simple types')
     */
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

    /**
     * @see it('JSON stringifies simple types')
     */
    public function testJSONStringifiesSimpleTypes(): void
    {
        self::assertEquals('"Int"', json_encode(Type::int()));
        self::assertEquals('"Article"', json_encode($this->blogArticle));
        self::assertEquals('"Interface"', json_encode($this->interfaceType));
        self::assertEquals('"Union"', json_encode($this->unionType));
        self::assertEquals('"Enum"', json_encode($this->enumType));
        self::assertEquals('"InputObject"', json_encode($this->inputObjectType));
        self::assertEquals('"Int!"', json_encode(Type::nonNull(Type::int())));
        self::assertEquals('"[Int]"', json_encode(Type::listOf(Type::int())));
        self::assertEquals('"[Int]!"', json_encode(Type::nonNull(Type::listOf(Type::int()))));
        self::assertEquals('"[Int!]"', json_encode(Type::listOf(Type::nonNull(Type::int()))));
        self::assertEquals('"[[Int]]"', json_encode(Type::listOf(Type::listOf(Type::int()))));
    }

    /**
     * @see it('identifies input types')
     */
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
            [Type::float(),true ],
            [Type::id(), true],
            [Type::int(), true],
            [Type::listOf(Type::string()), true],
            [Type::listOf($this->objectType), false],
            [Type::nonNull(Type::string()), true],
            [Type::nonNull($this->objectType), false],
            [Type::string(), true],
        ];

        foreach ($expected as $index => $entry) {
            self::assertSame(
                $entry[1],
                Type::isInputType($entry[0]),
                sprintf('Type %s was detected incorrectly', $entry[0])
            );
        }
    }

    /**
     * @see it('identifies output types')
     */
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
            [Type::float(),true ],
            [Type::id(), true],
            [Type::int(), true],
            [Type::listOf(Type::string()), true],
            [Type::listOf($this->objectType), true],
            [Type::nonNull(Type::string()), true],
            [Type::nonNull($this->objectType), true],
            [Type::string(), true],
        ];

        foreach ($expected as $index => $entry) {
            self::assertSame(
                $entry[1],
                Type::isOutputType($entry[0]),
                sprintf('Type %s was detected incorrectly', $entry[0])
            );
        }
    }

    /**
     * @see it('allows a thunk for Union member types')
     */
    public function testAllowsThunkForUnionTypes(): void
    {
        $union = new UnionType([
            'name'  => 'ThunkUnion',
            'types' => function (): array {
                return [$this->objectType];
            },
        ]);

        $types = $union->getTypes();
        self::assertEquals(1, count($types));
        self::assertSame($this->objectType, $types[0]);
    }

    public function testAllowsRecursiveDefinitions(): void
    {
        // See https://github.com/webonyx/graphql-php/issues/16
        $node = new InterfaceType([
            'name'   => 'Node',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
            ],
        ]);

        $blog   = null;
        $called = false;

        $user = new ObjectType([
            'name'       => 'User',
            'fields'     => static function () use (&$blog, &$called): array {
                self::assertNotNull($blog, 'Blog type is expected to be defined at this point, but it is null');
                $called = true;

                return [
                    'id'    => ['type' => Type::nonNull(Type::id())],
                    'blogs' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($blog)))],
                ];
            },
            'interfaces' => static function () use ($node): array {
                return [$node];
            },
        ]);

        $blog = new ObjectType([
            'name'       => 'Blog',
            'fields'     => static function () use ($user): array {
                return [
                    'id'    => ['type' => Type::nonNull(Type::id())],
                    'owner' => ['type' => Type::nonNull($user)],
                ];
            },
            'interfaces' => static function () use ($node): array {
                return [$node];
            },
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'node' => ['type' => $node],
                ],
            ]),
            'types' => [$user, $blog],
        ]);

        self::assertTrue($called);
        $schema->getType('Blog');

        self::assertEquals([$node], $blog->getInterfaces());
        self::assertEquals([$node], $user->getInterfaces());

        self::assertNotNull($user->getField('blogs'));
        /** @var NonNull $blogFieldReturnType */
        $blogFieldReturnType = $user->getField('blogs')->getType();
        self::assertSame($blog, $blogFieldReturnType->getWrappedType(true));

        self::assertNotNull($blog->getField('owner'));
        /** @var NonNull $ownerFieldReturnType */
        $ownerFieldReturnType = $blog->getField('owner')->getType();
        self::assertSame($user, $ownerFieldReturnType->getWrappedType(true));
    }

    public function testInputObjectTypeAllowsRecursiveDefinitions(): void
    {
        $called      = false;
        $inputObject = new InputObjectType([
            'name'   => 'InputObject',
            'fields' => static function () use (&$inputObject, &$called): array {
                $called = true;

                return [
                    'value'  => ['type' => Type::string()],
                    'nested' => ['type' => $inputObject],
                ];
            },
        ]);

        $someMutation = new ObjectType([
            'name'   => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $inputObject]],
                ],
            ],
        ]);

        $schema = new Schema([
            'query'    => $this->blogQuery,
            'mutation' => $someMutation,
        ]);

        self::assertSame($inputObject, $schema->getType('InputObject'));
        self::assertTrue($called);
        self::assertEquals(count($inputObject->getFields()), 2);
        self::assertSame($inputObject->getField('nested')->getType(), $inputObject);
        self::assertSame($someMutation->getField('mutateSomething')->getArg('input')->getType(), $inputObject);
    }

    public function testInterfaceTypeAllowsRecursiveDefinitions(): void
    {
        $called    = false;
        $interface = new InterfaceType([
            'name'   => 'SomeInterface',
            'fields' => static function () use (&$interface, &$called): array {
                $called = true;

                return [
                    'value'  => ['type' => Type::string()],
                    'nested' => ['type' => $interface],
                ];
            },
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'test' => ['type' => $interface],
            ],
        ]);

        $schema = new Schema(['query' => $query]);

        self::assertSame($interface, $schema->getType('SomeInterface'));
        self::assertTrue($called);
        self::assertEquals(count($interface->getFields()), 2);
        self::assertSame($interface->getField('nested')->getType(), $interface);
        self::assertSame($interface->getField('value')->getType(), Type::string());
    }

    public function testAllowsShorthandFieldDefinition(): void
    {
        $interface = new InterfaceType([
            'name'   => 'SomeInterface',
            'fields' => static function () use (&$interface): array {
                return [
                    'value'   => Type::string(),
                    'nested'  => $interface,
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
            'name'   => 'Query',
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

        self::assertEquals('arg1', $withArg->args[0]->name);
        self::assertEquals(Type::int(), $withArg->args[0]->getType());

        /** @var ObjectType $Query */
        $Query     = $schema->getType('Query');
        $testField = $Query->getField('test');
        self::assertEquals($interface, $testField->getType());
        self::assertEquals('test', $testField->name);
    }

    public function testInfersNameFromClassname(): void
    {
        $myObj = new MyCustomType();
        self::assertEquals('MyCustom', $myObj->name);

        $otherCustom = new OtherCustom();
        self::assertEquals('OtherCustom', $otherCustom->name);
    }

    public function testAllowsOverridingInternalTypes(): void
    {
        $idType = new CustomScalarType([
            'name'         => 'ID',
            'serialize'    => static function (): void {
            },
            'parseValue'   => static function (): void {
            },
            'parseLiteral' => static function (): void {
            },
        ]);

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => []]),
            'types' => [$idType],
        ]);

        self::assertSame($idType, $schema->getType('ID'));
    }

    // Field config must be object

    /**
     * @see it('accepts an Object type with a field function')
     */
    public function testAcceptsAnObjectTypeWithAFieldFunction(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => static function (): array {
                return [
                    'f' => ['type' => Type::string()],
                ];
            },
        ]);
        $objType->assertValid();
        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /**
     * @see it('rejects an Object type field with undefined config')
     */
    public function testRejectsAnObjectTypeFieldWithUndefinedConfig(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => ['f' => null],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject.f field config must be an array, but got: null'
        );
        $objType->getFields();
    }

    /**
     * @see it('rejects an Object type with incorrectly typed fields')
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedFields(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [['field' => Type::string()]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject fields must be an associative array with field names as keys or a ' .
            'function which returns such an array.'
        );
        $objType->getFields();
    }

    /**
     * @see it('rejects an Object type with a field function that returns incorrect type')
     */
    public function testRejectsAnObjectTypeWithAFieldFunctionThatReturnsIncorrectType(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => static function (): array {
                return [['field' => Type::string()]];
            },
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject fields must be an associative array with field names as keys or a ' .
            'function which returns such an array.'
        );
        $objType->getFields();
    }

    // Field arg config must be object

    /**
     * @see it('accepts an Object type with field args')
     */
    public function testAcceptsAnObjectTypeWithFieldArgs(): void
    {
        $this->expectNotToPerformAssertions();
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => Type::string(),
                    'args' => [
                        'goodArg' => ['type' => Type::string()],
                    ],
                ],
            ],
        ]);
        // Should not throw:
        $objType->assertValid();
    }

    // rejects an Object type with incorrectly typed field args

    /**
     * @see it('does not allow isDeprecated without deprecationReason on field')
     */
    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnField(): void
    {
        $OldObject = new ObjectType([
            'name'   => 'OldObject',
            'fields' => [
                'field' => [
                    'type'         => Type::string(),
                    'isDeprecated' => true,
                ],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'OldObject.field should provide "deprecationReason" instead of "isDeprecated".'
        );
        $OldObject->assertValid();
    }

    // Object interfaces must be array

    /**
     * @see it('accepts an Object type with array interfaces')
     */
    public function testAcceptsAnObjectTypeWithArrayInterfaces(): void
    {
        $objType = new ObjectType([
            'name'       => 'SomeObject',
            'interfaces' => [$this->interfaceType],
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);
        self::assertSame($this->interfaceType, $objType->getInterfaces()[0]);
    }

    /**
     * @see it('accepts an Object type with interfaces as a function returning an array')
     */
    public function testAcceptsAnObjectTypeWithInterfacesAsAFunctionReturningAnArray(): void
    {
        $objType = new ObjectType([
            'name'       => 'SomeObject',
            'interfaces' => function (): array {
                return [$this->interfaceType];
            },
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);
        self::assertSame($this->interfaceType, $objType->getInterfaces()[0]);
    }

    /**
     * @see it('rejects an Object type with incorrectly typed interfaces')
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedInterfaces(): void
    {
        $objType = new ObjectType([
            'name'       => 'SomeObject',
            'interfaces' => new stdClass(),
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject interfaces must be an Array or a callable which returns an Array.'
        );
        $objType->getInterfaces();
    }

    /**
     * @see it('rejects an Object type with interfaces as a function returning an incorrect type')
     */
    public function testRejectsAnObjectTypeWithInterfacesAsAFunctionReturningAnIncorrectType(): void
    {
        $objType = new ObjectType([
            'name'       => 'SomeObject',
            'interfaces' => static function (): stdClass {
                return new stdClass();
            },
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject interfaces must be an Array or a callable which returns an Array.'
        );
        $objType->getInterfaces();
    }

    // Type System: Object fields must have valid resolve values

    /**
     * @see it('accepts a lambda as an Object field resolver')
     */
    public function testAcceptsALambdaAsAnObjectFieldResolver(): void
    {
        $this->expectNotToPerformAssertions();
        // should not throw:
        $this->schemaWithObjectWithFieldResolver(static function (): void {
        });
    }

    private function schemaWithObjectWithFieldResolver($resolveValue)
    {
        $BadResolverType = new ObjectType([
            'name'   => 'BadResolver',
            'fields' => [
                'badField' => [
                    'type'    => Type::string(),
                    'resolve' => $resolveValue,
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'f' => ['type' => $BadResolverType],
                ],
            ]),
        ]);
        $schema->assertValid();

        return $schema;
    }

    /**
     * @see it('rejects an empty Object field resolver')
     */
    public function testRejectsAnEmptyObjectFieldResolver(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'BadResolver.badField field resolver must be a function if provided, but got: []'
        );
        $this->schemaWithObjectWithFieldResolver([]);
    }

    /**
     * @see it('rejects a constant scalar value resolver')
     */
    public function testRejectsAConstantScalarValueResolver(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'BadResolver.badField field resolver must be a function if provided, but got: 0'
        );
        $this->schemaWithObjectWithFieldResolver(0);
    }

    // Type System: Interface types must be resolvable

    /**
     * @see it('accepts an Interface type defining resolveType')
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveType(): void
    {
        $this->expectNotToPerformAssertions();
        $AnotherInterfaceType = new InterfaceType([
            'name'   => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name'       => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields'     => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('accepts an Interface type with an array of interfaces')
     */
    public function testAcceptsAnInterfaceTypeWithAnArrayOfInterfaces(): void
    {
        $interfaceType = new InterfaceType([
            'name'   => 'AnotherInterface',
            'fields' => [],
            'interfaces' => [$this->interfaceType],
        ]);
        self::assertSame($this->interfaceType, $interfaceType->getInterfaces()[0]);
    }

    /**
     * @see it('accepts an Interface type with interfaces as a function returning an array')
     */
    public function testAcceptsAnInterfaceTypeWithInterfacesAsAFunctionReturningAnArray(): void
    {
        $interfaceType = new InterfaceType([
            'name'   => 'AnotherInterface',
            'fields' => [],
            'interfaces' => function (): array {
                return [$this->interfaceType];
            },
        ]);
        self::assertSame($this->interfaceType, $interfaceType->getInterfaces()[0]);
    }

    /**
     * @see it('rejects an Interface type with incorrectly typed interfaces')
     */
    public function testRejectsAnInterfaceTypeWithIncorrectlyTypedInterfaces(): void
    {
        $objType = new InterfaceType([
            'name'       => 'AnotherInterface',
            'interfaces' => new stdClass(),
            'fields'     => [],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherInterface interfaces must be an Array or a callable which returns an Array.'
        );
        $objType->getInterfaces();
    }

    /**
     * @see it('rejects an Interface type with interfaces as a function returning an incorrect type')
     */
    public function testRejectsAnInterfaceTypeWithInterfacesAsAFunctionReturningAnIncorrectType(): void
    {
        $objType = new ObjectType([
            'name'       => 'AnotherInterface',
            'interfaces' => static function (): stdClass {
                return new stdClass();
            },
            'fields'     => [],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherInterface interfaces must be an Array or a callable which returns an Array.'
        );
        $objType->getInterfaces();
    }

    private function schemaWithFieldType($type)
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => ['field' => ['type' => $type]],
            ]),
            'types' => [$type],
        ]);
        $schema->assertValid();

        return $schema;
    }

    /**
     * @see it('accepts an Interface with implementing type defining isTypeOf')
     */
    public function testAcceptsAnInterfaceWithImplementingTypeDefiningIsTypeOf(): void
    {
        $this->expectNotToPerformAssertions();
        $InterfaceTypeWithoutResolveType = new InterfaceType([
            'name'   => 'InterfaceTypeWithoutResolveType',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name'       => 'SomeObject',
                'interfaces' => [$InterfaceTypeWithoutResolveType],
                'fields'     => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('accepts an Interface type defining resolveType with implementing type defining isTypeOf')
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveTypeWithImplementingTypeDefiningIsTypeOf(): void
    {
        $this->expectNotToPerformAssertions();
        $AnotherInterfaceType = new InterfaceType([
            'name'   => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name'       => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields'     => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('rejects an Interface type with an incorrect type for resolveType')
     */
    public function testRejectsAnInterfaceTypeWithAnIncorrectTypeForResolveType(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherInterface must provide "resolveType" as a function, but got: instance of stdClass'
        );

        $type = new InterfaceType([
            'name'        => 'AnotherInterface',
            'resolveType' => new stdClass(),
            'fields'      => ['f' => ['type' => Type::string()]],
        ]);
        $type->assertValid();
    }

    // Type System: Union types must be resolvable

    /**
     * @see it('accepts a Union type defining resolveType')
     */
    public function testAcceptsAUnionTypeDefiningResolveType(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
    }

    /**
     * @see it('accepts a Union of Object types defining isTypeOf')
     */
    public function testAcceptsAUnionOfObjectTypesDefiningIsTypeOf(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /**
     * @see it('accepts a Union type defining resolveType of Object types defining isTypeOf')
     */
    public function testAcceptsAUnionTypeDefiningResolveTypeOfObjectTypesDefiningIsTypeOf(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /**
     * @see it('rejects an Union type with an incorrect type for resolveType')
     */
    public function testRejectsAnUnionTypeWithAnIncorrectTypeForResolveType(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeUnion must provide "resolveType" as a function, but got: instance of stdClass'
        );
        $this->schemaWithFieldType(
            new UnionType([
                'name'        => 'SomeUnion',
                'resolveType' => new stdClass(),
                'types'       => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /**
     * @see it('accepts a Scalar type defining serialize')
     */
    public function testAcceptsAScalarTypeDefiningSerialize(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'      => 'SomeScalar',
                'serialize' => static function () {
                    return null;
                },
            ])
        );
    }

    // Type System: Scalar types must be serializable

    /**
     * @see it('rejects a Scalar type not defining serialize')
     */
    public function testRejectsAScalarTypeNotDefiningSerialize(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide "serialize" function. If this custom Scalar ' .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType(['name' => 'SomeScalar'])
        );
    }

    /**
     * @see it('rejects a Scalar type defining serialize with an incorrect type')
     */
    public function testRejectsAScalarTypeDefiningSerializeWithAnIncorrectType(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide "serialize" function. If this custom Scalar ' .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'      => 'SomeScalar',
                'serialize' => new stdClass(),
            ])
        );
    }

    /**
     * @see it('accepts a Scalar type defining parseValue and parseLiteral')
     */
    public function testAcceptsAScalarTypeDefiningParseValueAndParseLiteral(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'         => 'SomeScalar',
                'serialize'    => static function (): void {
                },
                'parseValue'   => static function (): void {
                },
                'parseLiteral' => static function (): void {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseValue but not parseLiteral')
     */
    public function testRejectsAScalarTypeDefiningParseValueButNotParseLiteral(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'       => 'SomeScalar',
                'serialize'  => static function (): void {
                },
                'parseValue' => static function (): void {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseLiteral but not parseValue')
     */
    public function testRejectsAScalarTypeDefiningParseLiteralButNotParseValue(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'         => 'SomeScalar',
                'serialize'    => static function (): void {
                },
                'parseLiteral' => static function (): void {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseValue and parseLiteral with an incorrect type')
     */
    public function testRejectsAScalarTypeDefiningParseValueAndParseLiteralWithAnIncorrectType(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name'         => 'SomeScalar',
                'serialize'    => static function (): void {
                },
                'parseValue'   => new stdClass(),
                'parseLiteral' => new stdClass(),
            ])
        );
    }

    /**
     * @see it('accepts an Object type with an isTypeOf function')
     */
    public function testAcceptsAnObjectTypeWithAnIsTypeOfFunction(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw
        $this->schemaWithFieldType(
            new ObjectType([
                'name'   => 'AnotherObject',
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    // Type System: Object types must be assertable

    /**
     * @see it('rejects an Object type with an incorrect type for isTypeOf')
     */
    public function testRejectsAnObjectTypeWithAnIncorrectTypeForIsTypeOf(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherObject must provide "isTypeOf" as a function, but got: instance of stdClass'
        );
        $this->schemaWithFieldType(
            new ObjectType([
                'name'     => 'AnotherObject',
                'isTypeOf' => new stdClass(),
                'fields'   => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('accepts a Union type with array types')
     */
    public function testAcceptsAUnionTypeWithArrayTypes(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
    }

    // Type System: Union types must be array

    /**
     * @see it('accepts a Union type with function returning an array of types')
     */
    public function testAcceptsAUnionTypeWithFunctionReturningAnArrayOfTypes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => function (): array {
                    return [$this->objectType];
                },
            ])
        );
    }

    /**
     * @see it('rejects a Union type without types')
     */
    public function testRejectsAUnionTypeWithoutTypes(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Must provide Array of types or a callable which returns such an array for Union SomeUnion'
        );
        $this->schemaWithFieldType(
            new UnionType(['name' => 'SomeUnion'])
        );
    }

    /**
     * @see it('rejects a Union type with incorrectly typed types')
     */
    public function testRejectsAUnionTypeWithIncorrectlyTypedTypes(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Must provide Array of types or a callable which returns such an array for Union SomeUnion'
        );
        $this->schemaWithFieldType(
            new UnionType([
                'name'  => 'SomeUnion',
                'types' => (object) ['test' => $this->objectType],
            ])
        );
    }

    /**
     * @see it('accepts an Input Object type with fields')
     */
    public function testAcceptsAnInputObjectTypeWithFields(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => [
                'f' => ['type' => Type::string()],
            ],
        ]);
        $inputObjType->assertValid();
        self::assertSame(Type::string(), $inputObjType->getField('f')->getType());
    }

    // Type System: Input Objects must have fields

    /**
     * @see it('accepts an Input Object type with a field function')
     */
    public function testAcceptsAnInputObjectTypeWithAFieldFunction(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => static function (): array {
                return [
                    'f' => ['type' => Type::string()],
                ];
            },
        ]);
        $inputObjType->assertValid();
        self::assertSame(Type::string(), $inputObjType->getField('f')->getType());
    }

    /**
     * @see it('accepts an Input Object type with a field type function')
     */
    public function testAcceptsAnInputObjectTypeWithAFieldTypeFunction(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => [
                'f' => static function (): Type {
                    return Type::string();
                },
            ],
        ]);
        $inputObjType->assertValid();
        self::assertSame(Type::string(), $inputObjType->getField('f')->getType());
    }

    /**
     * @see it('rejects an Input Object type with incorrect fields')
     */
    public function testRejectsAnInputObjectTypeWithIncorrectFields(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => [],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an associative array with field names as keys or a callable ' .
            'which returns such an array.'
        );
        $inputObjType->assertValid();
    }

    /**
     * @see it('rejects an Input Object type with fields function that returns incorrect type')
     */
    public function testRejectsAnInputObjectTypeWithFieldsFunctionThatReturnsIncorrectType(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => static function (): array {
                return [];
            },
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an associative array with field names as keys or a ' .
            'callable which returns such an array.'
        );
        $inputObjType->assertValid();
    }

    /**
     * @see it('rejects an Input Object type with resolvers')
     */
    public function testRejectsAnInputObjectTypeWithResolvers(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type'    => Type::string(),
                    'resolve' => static function (): int {
                        return 0;
                    },
                ],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject.f field has a resolve property, ' .
            'but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    // Type System: Input Object fields must not have resolvers

    /**
     * @see it('rejects an Input Object type with resolver constant')
     */
    public function testRejectsAnInputObjectTypeWithResolverConstant(): void
    {
        $inputObjType = new InputObjectType([
            'name'   => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type'    => Type::string(),
                    'resolve' => new stdClass(),
                ],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject.f field has a resolve property, ' .
            'but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    /**
     * @see it('accepts a well defined Enum type with empty value definition')
     */
    public function testAcceptsAWellDefinedEnumTypeWithEmptyValueDefinition(): void
    {
        $enumType = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [
                'FOO' => [],
                'BAR' => [],
            ],
        ]);
        self::assertEquals('FOO', $enumType->getValue('FOO')->value);
        self::assertEquals('BAR', $enumType->getValue('BAR')->value);
    }

    // Type System: Enum types must be well defined

    /**
     * @see it('accepts a well defined Enum type with internal value definition')
     */
    public function testAcceptsAWellDefinedEnumTypeWithInternalValueDefinition(): void
    {
        $enumType = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [
                'FOO' => ['value' => 10],
                'BAR' => ['value' => 20],
            ],
        ]);
        self::assertEquals(10, $enumType->getValue('FOO')->value);
        self::assertEquals(20, $enumType->getValue('BAR')->value);
    }

    /**
     * @see it('rejects an Enum type with incorrectly typed values')
     */
    public function testRejectsAnEnumTypeWithIncorrectlyTypedValues(): void
    {
        $enumType = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [['FOO' => 10]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeEnum values must be an array with value names as keys.'
        );
        $enumType->assertValid();
    }

    /**
     * @see it('does not allow isDeprecated without deprecationReason on enum')
     */
    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnEnum(): void
    {
        $enumType = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [
                'FOO' => ['isDeprecated' => true],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeEnum.FOO should provide "deprecationReason" instead ' .
            'of "isDeprecated".'
        );
        $enumType->assertValid();
    }

    /**
     * @see it('rejects a Schema which redefines a built-in type')
     */
    public function testRejectsASchemaWhichRedefinesABuiltInType(): void
    {
        $FakeString = new CustomScalarType([
            'name'      => 'String',
            'serialize' => static function (): void {
            },
        ]);

        $QueryType = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'normal' => ['type' => Type::string()],
                'fake'   => ['type' => $FakeString],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "String" ' .
            '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        );
        $schema = new Schema(['query' => $QueryType]);
        $schema->assertValid();
    }

    // Type System: A Schema must contain uniquely named types

    /**
     * @see it('rejects a Schema which defines an object type twice')
     */
    public function testRejectsASchemaWhichDefinesAnObjectTypeTwice(): void
    {
        $A = new ObjectType([
            'name'   => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $B = new ObjectType([
            'name'   => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'a' => ['type' => $A],
                'b' => ['type' => $B],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "SameName" ' .
            '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        );
        $schema = new Schema(['query' => $QueryType]);
        $schema->assertValid();
    }

    /**
     * @see it('rejects a Schema which have same named objects implementing an interface')
     */
    public function testRejectsASchemaWhichHaveSameNamedObjectsImplementingAnInterface(): void
    {
        $AnotherInterface = new InterfaceType([
            'name'   => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $FirstBadObject = new ObjectType([
            'name'       => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);

        $SecondBadObject = new ObjectType([
            'name'       => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields'     => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'iface' => ['type' => $AnotherInterface],
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "BadObject" ' .
            '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
        );
        $schema = new Schema([
            'query' => $QueryType,
            'types' => [$FirstBadObject, $SecondBadObject],
        ]);
        $schema->assertValid();
    }

    // Lazy Fields

    /**
     * @see it('allows a type to define its fields as closure returning array field definition to be lazy loaded')
     */
    public function testAllowsTypeWhichDefinesItFieldsAsClosureReturningFieldDefinitionAsArray(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => static function (): array {
                    return ['type' => Type::string()];
                },
            ],
        ]);

        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /**
     * @see it('allows a type to define its fields as closure returning object field definition to be lazy loaded')
     */
    public function testAllowsTypeWhichDefinesItFieldsAsClosureReturningFieldDefinitionAsObject(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => static function (): FieldDefinition {
                    return FieldDefinition::create(['name' => 'f', 'type' => Type::string()]);
                },
            ],
        ]);

        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /**
     * @see it('allows a type to define its fields as invokable class returning array field definition to be lazy loaded')
     */
    public function testAllowsTypeWhichDefinesItFieldsAsInvokableClassReturningFieldDefinitionAsArray(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => new class {
                    public function __invoke(): array
                    {
                        return ['type' => Type::string()];
                    }
                },
            ],
        ]);

        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /**
     * @see it('does not resolve field definitions if they are not accessed')
     */
    public function testFieldClosureNotExecutedIfNotAccessed(): void
    {
        $resolvedCount = 0;
        $fieldCallback = static function () use (&$resolvedCount): array {
            $resolvedCount++;

            return ['type' => Type::string()];
        };

        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => $fieldCallback,
                'b' => static function (): void {
                    throw new RuntimeException('Would not expect this to be called!');
                },
            ],
        ]);

        self::assertSame(Type::string(), $objType->getField('f')->getType());
        self::assertSame(1, $resolvedCount);
    }

    /**
     * @see it('does resolve all field definitions when validating the type')
     */
    public function testAllUnresolvedFieldsAreResolvedWhenValidatingType(): void
    {
        $resolvedCount = 0;
        $fieldCallback = static function () use (&$resolvedCount): array {
            $resolvedCount++;

            return ['type' => Type::string()];
        };

        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => $fieldCallback,
                'o' => $fieldCallback,
            ],
        ]);
        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
        self::assertSame(2, $resolvedCount);
    }

    /**
     * @see it('does throw when lazy loaded array field definition changes its name')
     */
    public function testThrowsWhenLazyLoadedArrayFieldDefinitionChangesItsName(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => static function (): array {
                    return ['name' => 'foo', 'type' => Type::string()];
                },
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject.f should not dynamically change its name when resolved lazily.'
        );

        $objType->assertValid();
    }

    /**
     * @see it('does throw when lazy loaded object field definition changes its name')
     */
    public function testThrowsWhenLazyLoadedObjectFieldDefinitionChangesItsName(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => static function (): FieldDefinition {
                    return FieldDefinition::create(['name' => 'foo', 'type' => Type::string()]);
                },
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject.f should not dynamically change its name when resolved lazily.'
        );

        $objType->assertValid();
    }

    /**
     * @see it('does throw when lazy loaded field definition has no keys for field names')
     */
    public function testThrowsWhenLazyLoadedFieldDefinitionHasNoKeysForFieldNames(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                static function (): array {
                    return ['type' => Type::string()];
                },
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject lazy fields must be an associative array with field names as keys.'
        );

        $objType->assertValid();
    }

    /**
     * @see it('does throw when lazy loaded field definition has invalid args')
     */
    public function testThrowsWhenLazyLoadedFieldHasInvalidArgs(): void
    {
        $objType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'f' => static function (): array {
                    return ['args' => 'invalid', 'type' => Type::string()];
                },
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject.f args must be an array.'
        );

        $objType->assertValid();
    }
}
