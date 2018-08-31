<?php
namespace GraphQL\Tests\Type;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class DefinitionTest extends TestCase
{
    /**
     * @var ObjectType
     */
    public $blogImage;

    /**
     * @var ObjectType
     */
    public $blogArticle;

    /**
     * @var ObjectType
     */
    public $blogAuthor;

    /**
     * @var ObjectType
     */
    public $blogMutation;

    /**
     * @var ObjectType
     */
    public $blogQuery;

    /**
     * @var ObjectType
     */
    public $blogSubscription;

    /**
     * @var ObjectType
     */
    public $objectType;

    /**
     * @var ObjectType
     */
    public $objectWithIsTypeOf;

    /**
     * @var InterfaceType
     */
    public $interfaceType;

    /**
     * @var UnionType
     */
    public $unionType;

    /**
     * @var EnumType
     */
    public $enumType;

    /**
     * @var InputObjectType
     */
    public $inputObjectType;

    /**
     * @var CustomScalarType
     */
    public $scalarType;

    public function setUp()
    {
        $this->objectType = new ObjectType(['name' => 'Object', 'fields' => ['tmp' => Type::string()]]);
        $this->interfaceType = new InterfaceType(['name' => 'Interface']);
        $this->unionType = new UnionType(['name' => 'Union', 'types' => [$this->objectType]]);
        $this->enumType = new EnumType(['name' => 'Enum']);
        $this->inputObjectType = new InputObjectType(['name' => 'InputObject']);

        $this->objectWithIsTypeOf = new ObjectType([
            'name' => 'ObjectWithIsTypeOf',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->scalarType = new CustomScalarType([
            'name' => 'Scalar',
            'serialize' => function () {},
            'parseValue' => function () {},
            'parseLiteral' => function () {},
        ]);

        $this->blogImage = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()]
            ]
        ]);

        $this->blogAuthor = new ObjectType([
            'name' => 'Author',
            'fields' => function() {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [ 'type' => $this->blogImage, 'args' => [
                        'width' => ['type' => Type::int()],
                        'height' => ['type' => Type::int()]
                    ]],
                    'recentArticle' => $this->blogArticle,
                ];
            },
        ]);

        $this->blogArticle = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author' => ['type' => $this->blogAuthor],
                'title' => ['type' => Type::string()],
                'body' => ['type' => Type::string()]
            ]
        ]);

        $this->blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => ['type' => $this->blogArticle, 'args' => [
                    'id' => ['type' => Type::string()]
                ]],
                'feed' => ['type' => new ListOfType($this->blogArticle)]
            ]
        ]);

        $this->blogMutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'writeArticle' => ['type' => $this->blogArticle]
            ]
        ]);

        $this->blogSubscription = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'articleSubscribe' => [
                    'args' => [ 'id' => [ 'type' => Type::string() ]],
                    'type' => $this->blogArticle
                ]
            ]
        ]);
    }

    // Type System: Example

    /**
     * @see it('defines a query only schema')
     */
    public function testDefinesAQueryOnlySchema()
    {
        $blogSchema = new Schema([
            'query' => $this->blogQuery
        ]);

        $this->assertSame($blogSchema->getQueryType(), $this->blogQuery);

        $articleField = $this->blogQuery->getField('article');
        $this->assertSame($articleField->getType(), $this->blogArticle);
        $this->assertSame($articleField->getType()->name, 'Article');
        $this->assertSame($articleField->name, 'article');

        /** @var ObjectType $articleFieldType */
        $articleFieldType = $articleField->getType();
        $titleField = $articleFieldType->getField('title');

        $this->assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $titleField);
        $this->assertSame('title', $titleField->name);
        $this->assertSame(Type::string(), $titleField->getType());

        $authorField = $articleFieldType->getField('author');
        $this->assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $authorField);

        /** @var ObjectType $authorFieldType */
        $authorFieldType = $authorField->getType();
        $this->assertSame($this->blogAuthor, $authorFieldType);

        $recentArticleField = $authorFieldType->getField('recentArticle');
        $this->assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $recentArticleField);
        $this->assertSame($this->blogArticle, $recentArticleField->getType());

        $feedField = $this->blogQuery->getField('feed');
        $this->assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $feedField);

        /** @var ListOfType $feedFieldType */
        $feedFieldType = $feedField->getType();
        $this->assertInstanceOf('GraphQL\Type\Definition\ListOfType', $feedFieldType);
        $this->assertSame($this->blogArticle, $feedFieldType->getWrappedType());
    }

    /**
     * @see it('defines a mutation schema')
     */
    public function testDefinesAMutationSchema()
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $this->blogMutation
        ]);

        $this->assertSame($this->blogMutation, $schema->getMutationType());
        $writeMutation = $this->blogMutation->getField('writeArticle');

        $this->assertInstanceOf('GraphQL\Type\Definition\FieldDefinition', $writeMutation);
        $this->assertSame($this->blogArticle, $writeMutation->getType());
        $this->assertSame('Article', $writeMutation->getType()->name);
        $this->assertSame('writeArticle', $writeMutation->name);
    }

    /**
     * @see it('defines a subscription schema')
     */
    public function testDefinesSubscriptionSchema()
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'subscription' => $this->blogSubscription
        ]);

        $this->assertEquals($this->blogSubscription, $schema->getSubscriptionType());

        $sub = $this->blogSubscription->getField('articleSubscribe');
        $this->assertEquals($sub->getType(), $this->blogArticle);
        $this->assertEquals($sub->getType()->name, 'Article');
        $this->assertEquals($sub->name, 'articleSubscribe');
    }

    /**
     * @see it('defines an enum type with deprecated value')
     */
    public function testDefinesEnumTypeWithDeprecatedValue()
    {
        $enumTypeWithDeprecatedValue = new EnumType([
            'name' => 'EnumWithDeprecatedValue',
            'values' => [
                'foo' => ['deprecationReason' => 'Just because']
            ]
        ]);

        $value = $enumTypeWithDeprecatedValue->getValues()[0];

        $this->assertArraySubset([
            'name' => 'foo',
            'description' => null,
            'deprecationReason' => 'Just because',
            'value' => 'foo',
            'astNode' => null
        ], (array) $value);

        $this->assertEquals(true, $value->isDeprecated());
    }

    /**
     * @see it('defines an enum type with a value of `null` and `undefined`')
     */
    public function testDefinesAnEnumTypeWithAValueOfNullAndUndefined()
    {
        $EnumTypeWithNullishValue = new EnumType([
            'name' => 'EnumWithNullishValue',
            'values' => [
                'NULL' => ['value' => null],
                'UNDEFINED' => ['value' => null],
            ]
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

        $this->assertEquals(count($expected), count($actual));
        $this->assertArraySubset($expected[0], (array)$actual[0]);
        $this->assertArraySubset($expected[1], (array)$actual[1]);
    }

    /**
     * @see it('defines an object type with deprecated field')
     */
    public function testDefinesAnObjectTypeWithDeprecatedField()
    {
        $TypeWithDeprecatedField = new ObjectType([
          'name' => 'foo',
          'fields' => [
            'bar' => [
              'type' => Type::string(),
              'deprecationReason' => 'A terrible reason'
            ]
          ]
        ]);

        $field = $TypeWithDeprecatedField->getField('bar');

        $this->assertEquals(Type::string(), $field->getType());
        $this->assertEquals(true, $field->isDeprecated());
        $this->assertEquals('A terrible reason', $field->deprecationReason);
        $this->assertEquals('bar', $field->name);
        $this->assertEquals([], $field->args);
    }

    /**
     * @see it('includes nested input objects in the map')
     */
    public function testIncludesNestedInputObjectInTheMap()
    {
        $nestedInputObject = new InputObjectType([
            'name' => 'NestedInputObject',
            'fields' => ['value' => ['type' => Type::string()]]
        ]);
        $someInputObject = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => ['nested' => ['type' => $nestedInputObject]]
        ]);
        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $someInputObject]]
                ]
            ]
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation
        ]);
        $this->assertSame($nestedInputObject, $schema->getType('NestedInputObject'));
    }

    /**
     * @see it('includes interface possible types in the type map')
     */
    public function testIncludesInterfaceSubtypesInTheTypeMap()
    {
        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()]
            ]
        ]);

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => Type::int()]
            ],
            'interfaces' => [$someInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface]
                ]
            ]),
            'types' => [$someSubtype]
        ]);
        $this->assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /**
     * @see it('includes interfaces' thunk subtypes in the type map')
     */
    public function testIncludesInterfacesThunkSubtypesInTheTypeMap()
    {
        $someInterface = null;

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => Type::int()]
            ],
            'interfaces' => function() use (&$someInterface) { return [$someInterface]; },
        ]);

        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => Type::int()]
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface]
                ]
            ]),
            'types' => [$someSubtype]
        ]);

        $this->assertSame($someSubtype, $schema->getType('SomeSubtype'));
    }

    /**
     * @see it('stringifies simple types')
     */
    public function testStringifiesSimpleTypes()
    {
        $this->assertSame('Int', (string) Type::int());
        $this->assertSame('Article', (string) $this->blogArticle);

        $this->assertSame('Interface', (string) $this->interfaceType);
        $this->assertSame('Union', (string) $this->unionType);
        $this->assertSame('Enum', (string) $this->enumType);
        $this->assertSame('InputObject', (string) $this->inputObjectType);
        $this->assertSame('Object', (string) $this->objectType);

        $this->assertSame('Int!', (string) new NonNull(Type::int()));
        $this->assertSame('[Int]', (string) new ListOfType(Type::int()));
        $this->assertSame('[Int]!', (string) new NonNull(new ListOfType(Type::int())));
        $this->assertSame('[Int!]', (string) new ListOfType(new NonNull(Type::int())));
        $this->assertSame('[[Int]]', (string) new ListOfType(new ListOfType(Type::int())));
    }

    /**
     * @see it('JSON stringifies simple types')
     */
    public function testJSONStringifiesSimpleTypes()
    {
        $this->assertEquals('"Int"', json_encode(Type::int()));
        $this->assertEquals('"Article"', json_encode($this->blogArticle));
        $this->assertEquals('"Interface"', json_encode($this->interfaceType));
        $this->assertEquals('"Union"', json_encode($this->unionType));
        $this->assertEquals('"Enum"', json_encode($this->enumType));
        $this->assertEquals('"InputObject"', json_encode($this->inputObjectType));
        $this->assertEquals('"Int!"', json_encode(Type::nonNull(Type::int())));
        $this->assertEquals('"[Int]"', json_encode(Type::listOf(Type::int())));
        $this->assertEquals('"[Int]!"', json_encode(Type::nonNull(Type::listOf(Type::int()))));
        $this->assertEquals('"[Int!]"', json_encode(Type::listOf(Type::nonNull(Type::int()))));
        $this->assertEquals('"[[Int]]"', json_encode(Type::listOf(Type::listOf(Type::int()))));
    }

    /**
     * @see it('identifies input types')
     */
    public function testIdentifiesInputTypes()
    {
        $expected = [
            [Type::int(), true],
            [$this->objectType, false],
            [$this->interfaceType, false],
            [$this->unionType, false],
            [$this->enumType, true],
            [$this->inputObjectType, true]
        ];

        foreach ($expected as $index => $entry) {
            $this->assertSame($entry[1], Type::isInputType($entry[0]), "Type {$entry[0]} was detected incorrectly");
        }
    }

    /**
     * @see it('identifies output types')
     */
    public function testIdentifiesOutputTypes()
    {
        $expected = [
            [Type::int(), true],
            [$this->objectType, true],
            [$this->interfaceType, true],
            [$this->unionType, true],
            [$this->enumType, true],
            [$this->inputObjectType, false]
        ];

        foreach ($expected as $index => $entry) {
            $this->assertSame($entry[1], Type::isOutputType($entry[0]), "Type {$entry[0]} was detected incorrectly");
        }
    }

    /**
     * @see it('prohibits nesting NonNull inside NonNull')
     */
    public function testProhibitsNestingNonNullInsideNonNull()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Expected Int! to be a GraphQL nullable type.'
        );
        Type::nonNull(Type::nonNull(Type::int()));
    }

    /**
     * @see it('allows a thunk for Union member types')
     */
    public function testAllowsThunkForUnionTypes()
    {
        $union = new UnionType([
            'name' => 'ThunkUnion',
            'types' => function() {return [$this->objectType]; }
        ]);

        $types = $union->getTypes();
        $this->assertEquals(1, count($types));
        $this->assertSame($this->objectType, $types[0]);
    }

    public function testAllowsRecursiveDefinitions()
    {
        // See https://github.com/webonyx/graphql-php/issues/16
        $node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())]
            ]
        ]);

        $blog = null;
        $called = false;

        $user = new ObjectType([
            'name' => 'User',
            'fields' => function() use (&$blog, &$called) {
                $this->assertNotNull($blog, 'Blog type is expected to be defined at this point, but it is null');
                $called = true;

                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'blogs' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($blog)))]
                ];
            },
            'interfaces' => function() use ($node) {
                return [$node];
            }
        ]);

        $blog = new ObjectType([
            'name' => 'Blog',
            'fields' => function() use ($user) {
                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'owner' => ['type' => Type::nonNull($user)]
                ];
            },
            'interfaces' => function() use ($node) {
                return [$node];
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'node' => ['type' => $node]
                ]
            ]),
            'types' => [$user, $blog]
        ]);

        $this->assertTrue($called);
        $schema->getType('Blog');

        $this->assertEquals([$node], $blog->getInterfaces());
        $this->assertEquals([$node], $user->getInterfaces());

        $this->assertNotNull($user->getField('blogs'));
        $this->assertSame($blog, $user->getField('blogs')->getType()->getWrappedType(true));

        $this->assertNotNull($blog->getField('owner'));
        $this->assertSame($user, $blog->getField('owner')->getType()->getWrappedType(true));
    }

    public function testInputObjectTypeAllowsRecursiveDefinitions()
    {
        $called = false;
        $inputObject = new InputObjectType([
            'name' => 'InputObject',
            'fields' => function() use (&$inputObject, &$called) {
                $called = true;
                return [
                    'value' => ['type' => Type::string()],
                    'nested' => ['type' => $inputObject ]
                ];
            }
        ]);
        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $inputObject]]
                ]
            ]
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation
        ]);

        $this->assertSame($inputObject, $schema->getType('InputObject'));
        $this->assertTrue($called);
        $this->assertEquals(count($inputObject->getFields()), 2);
        $this->assertSame($inputObject->getField('nested')->getType(), $inputObject);
        $this->assertSame($someMutation->getField('mutateSomething')->getArg('input')->getType(), $inputObject);
    }

    public function testInterfaceTypeAllowsRecursiveDefinitions()
    {
        $called = false;
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => function() use (&$interface, &$called) {
                $called = true;
                return [
                    'value' => ['type' => Type::string()],
                    'nested' => ['type' => $interface ]
                ];
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => ['type' => $interface]
            ]
        ]);

        $schema = new Schema([
            'query' => $query
        ]);

        $this->assertSame($interface, $schema->getType('SomeInterface'));
        $this->assertTrue($called);
        $this->assertEquals(count($interface->getFields()), 2);
        $this->assertSame($interface->getField('nested')->getType(), $interface);
        $this->assertSame($interface->getField('value')->getType(), Type::string());
    }

    public function testAllowsShorthandFieldDefinition()
    {
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => function() use (&$interface) {
                return [
                    'value' => Type::string(),
                    'nested' => $interface,
                    'withArg' => [
                        'type' => Type::string(),
                        'args' => [
                            'arg1' => Type::int()
                        ]
                    ]
                ];
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => $interface
            ]
        ]);

        $schema = new Schema([
            'query' => $query
        ]);

        $valueField = $schema->getType('SomeInterface')->getField('value');
        $nestedField = $schema->getType('SomeInterface')->getField('nested');

        $this->assertEquals(Type::string(), $valueField->getType());
        $this->assertEquals($interface, $nestedField->getType());

        $withArg = $schema->getType('SomeInterface')->getField('withArg');
        $this->assertEquals(Type::string(), $withArg->getType());

        $this->assertEquals('arg1', $withArg->args[0]->name);
        $this->assertEquals(Type::int(), $withArg->args[0]->getType());

        $testField = $schema->getType('Query')->getField('test');
        $this->assertEquals($interface, $testField->getType());
        $this->assertEquals('test', $testField->name);
    }

    public function testInfersNameFromClassname()
    {
        $myObj = new MyCustomType();
        $this->assertEquals('MyCustom', $myObj->name);

        $otherCustom = new OtherCustom();
        $this->assertEquals('OtherCustom', $otherCustom->name);
    }

    public function testAllowsOverridingInternalTypes()
    {
        $idType = new CustomScalarType([
            'name' => 'ID',
            'serialize' => function() {},
            'parseValue' => function() {},
            'parseLiteral' => function() {}
        ]);

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => []]),
            'types' => [$idType]
        ]);

        $this->assertSame($idType, $schema->getType('ID'));
    }

    // Field config must be object

    /**
     * @see it('accepts an Object type with a field function')
     */
    public function testAcceptsAnObjectTypeWithAFieldFunction()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => function () {
                return [
                    'f' => ['type' => Type::string()],
                ];
            },
        ]);
        $objType->assertValid(true);
        $this->assertSame(Type::string(), $objType->getField('f')->getType());
    }

    /**
     * @see it('rejects an Object type field with undefined config')
     */
    public function testRejectsAnObjectTypeFieldWithUndefinedConfig()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => null,
            ],
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
    public function testRejectsAnObjectTypeWithIncorrectlyTypedFields()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
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
    public function testRejectsAnObjectTypeWithAFieldFunctionThatReturnsIncorrectType()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => function () {
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
    public function testAcceptsAnObjectTypeWithFieldArgs()
    {
        $this->expectNotToPerformAssertions();
        $objType = new ObjectType([
            'name' => 'SomeObject',
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
    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnField()
    {
        $OldObject = new ObjectType([
            'name' => 'OldObject',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
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
    public function testAcceptsAnObjectTypeWithArrayInterfaces()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => [$this->interfaceType],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        $this->assertSame($this->interfaceType, $objType->getInterfaces()[0]);
    }

    /**
     * @see it('accepts an Object type with interfaces as a function returning an array')
     */
    public function testAcceptsAnObjectTypeWithInterfacesAsAFunctionReturningAnArray()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => function () {
                return [$this->interfaceType];
            },
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        $this->assertSame($this->interfaceType, $objType->getInterfaces()[0]);
    }

    /**
     * @see it('rejects an Object type with incorrectly typed interfaces')
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedInterfaces()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => new \stdClass(),
            'fields' => ['f' => ['type' => Type::string()]],
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
    public function testRejectsAnObjectTypeWithInterfacesAsAFunctionReturningAnIncorrectType()
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => function () {
                return new \stdClass();
            },
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeObject interfaces must be an Array or a callable which returns an Array.'
        );
        $objType->getInterfaces();
    }

    // Type System: Object fields must have valid resolve values

    private function schemaWithObjectWithFieldResolver($resolveValue)
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

    /**
     * @see it('accepts a lambda as an Object field resolver')
     */
    public function testAcceptsALambdaAsAnObjectFieldResolver()
    {
        $this->expectNotToPerformAssertions();
        // should not throw:
        $this->schemaWithObjectWithFieldResolver(function () {});
    }

    /**
     * @see it('rejects an empty Object field resolver')
     */
    public function testRejectsAnEmptyObjectFieldResolver()
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
    public function testRejectsAConstantScalarValueResolver()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'BadResolver.badField field resolver must be a function if provided, but got: 0'
        );
        $this->schemaWithObjectWithFieldResolver(0);
    }


    // Type System: Interface types must be resolvable

    private function schemaWithFieldType($type)
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['field' => ['type' => $type]],
            ]),
            'types' => [$type],
        ]);
        $schema->assertValid();
        return $schema;
    }
    /**
     * @see it('accepts an Interface type defining resolveType')
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveType()
    {
        $this->expectNotToPerformAssertions();
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('accepts an Interface with implementing type defining isTypeOf')
     */
    public function testAcceptsAnInterfaceWithImplementingTypeDefiningIsTypeOf()
    {
        $this->expectNotToPerformAssertions();
        $InterfaceTypeWithoutResolveType = new InterfaceType([
            'name' => 'InterfaceTypeWithoutResolveType',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$InterfaceTypeWithoutResolveType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('accepts an Interface type defining resolveType with implementing type defining isTypeOf')
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveTypeWithImplementingTypeDefiningIsTypeOf()
    {
        $this->expectNotToPerformAssertions();
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        // Should not throw:
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'interfaces' => [$AnotherInterfaceType],
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('rejects an Interface type with an incorrect type for resolveType')
     */
    public function testRejectsAnInterfaceTypeWithAnIncorrectTypeForResolveType()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherInterface must provide "resolveType" as a function, but got: instance of stdClass'
        );

        $type = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => new \stdClass(),
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
        $type->assertValid();
    }

    // Type System: Union types must be resolvable

    private function ObjectWithIsTypeOf()
    {
        return new ObjectType([
            'name' => 'ObjectWithIsTypeOf',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);
    }

    /**
     * @see it('accepts a Union type defining resolveType')
     */
    public function testAcceptsAUnionTypeDefiningResolveType()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
    }

    /**
     * @see it('accepts a Union of Object types defining isTypeOf')
     */
    public function testAcceptsAUnionOfObjectTypesDefiningIsTypeOf()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /**
     * @see it('accepts a Union type defining resolveType of Object types defining isTypeOf')
     */
    public function testAcceptsAUnionTypeDefiningResolveTypeOfObjectTypesDefiningIsTypeOf()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    /**
     * @see it('rejects an Union type with an incorrect type for resolveType')
     */
    public function testRejectsAnUnionTypeWithAnIncorrectTypeForResolveType()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeUnion must provide "resolveType" as a function, but got: instance of stdClass'
        );
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'resolveType' => new \stdClass(),
                'types' => [$this->objectWithIsTypeOf],
            ])
        );
    }

    // Type System: Scalar types must be serializable

    /**
     * @see it('accepts a Scalar type defining serialize')
     */
    public function testAcceptsAScalarTypeDefiningSerialize()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => function () {
                    return null;
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type not defining serialize')
     */
    public function testRejectsAScalarTypeNotDefiningSerialize()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide "serialize" function. If this custom Scalar ' .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining serialize with an incorrect type')
     */
    public function testRejectsAScalarTypeDefiningSerializeWithAnIncorrectType()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide "serialize" function. If this custom Scalar ' .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => new \stdClass(),
            ])
        );
    }

    /**
     * @see it('accepts a Scalar type defining parseValue and parseLiteral')
     */
    public function testAcceptsAScalarTypeDefiningParseValueAndParseLiteral()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => function () {
                },
                'parseValue' => function () {
                },
                'parseLiteral' => function () {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseValue but not parseLiteral')
     */
    public function testRejectsAScalarTypeDefiningParseValueButNotParseLiteral()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => function () {
                },
                'parseValue' => function () {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseLiteral but not parseValue')
     */
    public function testRejectsAScalarTypeDefiningParseLiteralButNotParseValue()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => function () {
                },
                'parseLiteral' => function () {
                },
            ])
        );
    }

    /**
     * @see it('rejects a Scalar type defining parseValue and parseLiteral with an incorrect type')
     */
    public function testRejectsAScalarTypeDefiningParseValueAndParseLiteralWithAnIncorrectType()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $this->schemaWithFieldType(
            new CustomScalarType([
                'name' => 'SomeScalar',
                'serialize' => function () {
                },
                'parseValue' => new \stdClass(),
                'parseLiteral' => new \stdClass(),
            ])
        );
    }

    // Type System: Object types must be assertable

    /**
     * @see it('accepts an Object type with an isTypeOf function')
     */
    public function testAcceptsAnObjectTypeWithAnIsTypeOfFunction()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'AnotherObject',
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    /**
     * @see it('rejects an Object type with an incorrect type for isTypeOf')
     */
    public function testRejectsAnObjectTypeWithAnIncorrectTypeForIsTypeOf()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'AnotherObject must provide "isTypeOf" as a function, but got: instance of stdClass'
        );
        $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'AnotherObject',
                'isTypeOf' => new \stdClass(),
                'fields' => ['f' => ['type' => Type::string()]],
            ])
        );
    }

    // Type System: Union types must be array

    /**
     * @see it('accepts a Union type with array types')
     */
    public function testAcceptsAUnionTypeWithArrayTypes()
    {
        $this->expectNotToPerformAssertions();
        // Should not throw:
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => [$this->objectType],
            ])
        );
    }

    /**
     * @see it('accepts a Union type with function returning an array of types')
     */
    public function testAcceptsAUnionTypeWithFunctionReturningAnArrayOfTypes()
    {
        $this->expectNotToPerformAssertions();
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => function () {
                    return [$this->objectType];
                },
            ])
        );
    }

    /**
     * @see it('rejects a Union type without types')
     */
    public function testRejectsAUnionTypeWithoutTypes()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Must provide Array of types or a callable which returns such an array for Union SomeUnion'
        );
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
            ])
        );
    }

    /**
     * @see it('rejects a Union type with incorrectly typed types')
     */
    public function testRejectsAUnionTypeWithIncorrectlyTypedTypes()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Must provide Array of types or a callable which returns such an array for Union SomeUnion'
        );
        $this->schemaWithFieldType(
            new UnionType([
                'name' => 'SomeUnion',
                'types' => (object)[ 'test' => $this->objectType, ],
            ])
        );
    }

    // Type System: Input Objects must have fields

    /**
     * @see it('accepts an Input Object type with fields')
     */
    public function testAcceptsAnInputObjectTypeWithFields()
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => ['type' => Type::string()],
            ],
        ]);
        $inputObjType->assertValid();
        $this->assertSame(Type::string(), $inputObjType->getField('f')->getType());
    }

    /**
     * @see it('accepts an Input Object type with a field function')
     */
    public function testAcceptsAnInputObjectTypeWithAFieldFunction()
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => function () {
                return [
                    'f' => ['type' => Type::string()],
                ];
            },
        ]);
        $inputObjType->assertValid();
        $this->assertSame(Type::string(), $inputObjType->getField('f')->getType());
    }

    /**
     * @see it('rejects an Input Object type with incorrect fields')
     */
    public function testRejectsAnInputObjectTypeWithIncorrectFields()
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject fields must be an associative array with field names as keys or a callable '.
            'which returns such an array.'
        );
        $inputObjType->assertValid();
    }

    /**
     * @see it('rejects an Input Object type with fields function that returns incorrect type')
     */
    public function testRejectsAnInputObjectTypeWithFieldsFunctionThatReturnsIncorrectType()
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => function () {
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

    // Type System: Input Object fields must not have resolvers

    /**
     * @see it('rejects an Input Object type with resolvers')
     */
    public function testRejectsAnInputObjectTypeWithResolvers()
    {
        $inputObjType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => Type::string(),
                    'resolve' => function () {
                        return 0;
                    },
                ],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeInputObject.f field type has a resolve property, ' .
            'but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    /**
     * @see it('rejects an Input Object type with resolver constant')
     */
    public function testRejectsAnInputObjectTypeWithResolverConstant()
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
            'SomeInputObject.f field type has a resolve property, ' .
            'but Input Types cannot define resolvers.'
        );
        $inputObjType->assertValid();
    }

    // Type System: Enum types must be well defined

    /**
     * @see it('accepts a well defined Enum type with empty value definition')
     */
    public function testAcceptsAWellDefinedEnumTypeWithEmptyValueDefinition()
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => [],
                'BAR' => [],
            ],
        ]);
        $this->assertEquals('FOO', $enumType->getValue('FOO')->value);
        $this->assertEquals('BAR', $enumType->getValue('BAR')->value);
    }

    /**
     * @see it('accepts a well defined Enum type with internal value definition')
     */
    public function testAcceptsAWellDefinedEnumTypeWithInternalValueDefinition()
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => ['value' => 10],
                'BAR' => ['value' => 20],
            ],
        ]);
        $this->assertEquals(10, $enumType->getValue('FOO')->value);
        $this->assertEquals(20, $enumType->getValue('BAR')->value);
    }

    /**
     * @see it('rejects an Enum type with incorrectly typed values')
     */
    public function testRejectsAnEnumTypeWithIncorrectlyTypedValues()
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
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
    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnEnum()
    {
        $enumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => [
                    'isDeprecated' => true,
                ],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'SomeEnum.FOO should provide "deprecationReason" instead ' .
            'of "isDeprecated".'
        );
        $enumType->assertValid();
    }

    // Type System: List must accept only types

    public function testListMustAcceptOnlyTypes()
    {
        $types = [
            Type::string(),
            $this->scalarType,
            $this->objectType,
            $this->unionType,
            $this->interfaceType,
            $this->enumType,
            $this->inputObjectType,
            Type::listOf(Type::string()),
            Type::nonNull(Type::string()),
        ];

        $badTypes = [[], new \stdClass(), '', null];

        foreach ($types as $type) {
            try {
                Type::listOf($type);
            } catch (\Throwable $e) {
                $this->fail("List is expected to accept type: " . get_class($type) . ", but got error: ". $e->getMessage());
            }
        }
        foreach ($badTypes as $badType) {
            $typeStr = Utils::printSafe($badType);
            try {
                Type::listOf($badType);
                $this->fail("List should not accept $typeStr");
            } catch (InvariantViolation $e) {
                $this->assertEquals("Expected $typeStr to be a GraphQL type.", $e->getMessage());
            }
        }
    }

    // Type System: NonNull must only accept non-nullable types

    public function testNonNullMustOnlyAcceptNonNullableTypes()
    {
        $nullableTypes = [
            Type::string(),
            $this->scalarType,
            $this->objectType,
            $this->unionType,
            $this->interfaceType,
            $this->enumType,
            $this->inputObjectType,
            Type::listOf(Type::string()),
            Type::listOf(Type::nonNull(Type::string())),
        ];
        $notNullableTypes = [
            Type::nonNull(Type::string()),
            [],
            new \stdClass(),
            '',
            null,
        ];
        foreach ($nullableTypes as $type) {
            try {
                Type::nonNull($type);
            } catch (\Throwable $e) {
                $this->fail("NonNull is expected to accept type: " . get_class($type) . ", but got error: ". $e->getMessage());
            }
        }
        foreach ($notNullableTypes as $badType) {
            $typeStr = Utils::printSafe($badType);
            try {
                Type::nonNull($badType);
                $this->fail("Nulls should not accept $typeStr");
            } catch (InvariantViolation $e) {
                $this->assertEquals("Expected $typeStr to be a GraphQL nullable type.", $e->getMessage());
            }
        }
    }

    // Type System: A Schema must contain uniquely named types

    /**
     * @see it('rejects a Schema which redefines a built-in type')
     */
    public function testRejectsASchemaWhichRedefinesABuiltInType()
    {
        $FakeString = new CustomScalarType([
            'name' => 'String',
            'serialize' => function () {
            },
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
            'Schema must contain unique named types but contains multiple types named "String" '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );
        $schema = new Schema(['query' => $QueryType]);
        $schema->assertValid();
    }

    /**
     * @see it('rejects a Schema which defines an object type twice')
     */
    public function testRejectsASchemaWhichDefinesAnObjectTypeTwice()
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
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "SameName" ' .
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );
        $schema = new Schema([ 'query' => $QueryType ]);
        $schema->assertValid();
    }

    /**
     * @see it('rejects a Schema which have same named objects implementing an interface')
     */
    public function testRejectsASchemaWhichHaveSameNamedObjectsImplementingAnInterface()
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

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Schema must contain unique named types but contains multiple types named "BadObject" ' .
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );
        $schema = new Schema([
            'query' => $QueryType,
            'types' => [$FirstBadObject, $SecondBadObject],
        ]);
        $schema->assertValid();
    }
}
