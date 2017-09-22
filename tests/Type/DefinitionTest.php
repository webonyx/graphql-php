<?php
namespace GraphQL\Tests\Type;

require_once __DIR__ . '/TestClasses.php';

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

class DefinitionTest extends \PHPUnit_Framework_TestCase
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

    public function setUp()
    {
        $this->objectType = new ObjectType([
            'name' => 'Object',
            'isTypeOf' => function() {return true;}
        ]);
        $this->interfaceType = new InterfaceType(['name' => 'Interface']);
        $this->unionType = new UnionType(['name' => 'Union', 'types' => [$this->objectType]]);
        $this->enumType = new EnumType(['name' => 'Enum']);
        $this->inputObjectType = new InputObjectType(['name' => 'InputObject']);

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
     * @it defines a query only schema
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
     * @it defines a mutation schema
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
     * @it defines a subscription schema
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
     * @it defines an enum type with deprecated value
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
     * @it defines an enum type with a value of `null` and `undefined`
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
     * @it defines an object type with deprecated field
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
     * @it includes nested input objects in the map
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
     * @it includes interfaces\' subtypes in the type map
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
            'isTypeOf' => function() {return true;}
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
     * @it includes interfaces\' thunk subtypes in the type map
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
            'isTypeOf' => function() {return true;}
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
     * @it stringifies simple types
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
     * @it identifies input types
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
     * @it identifies output types
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
     * @it prohibits nesting NonNull inside NonNull
     */
    public function testProhibitsNonNullNesting()
    {
        $this->setExpectedException('\Exception');
        new NonNull(new NonNull(Type::int()));
    }

    /**
     * @it prohibits putting non-Object types in unions
     */
    public function testProhibitsPuttingNonObjectTypesInUnions()
    {
        $int = Type::int();

        $badUnionTypes = [
            $int,
            new NonNull($int),
            new ListOfType($int),
            $this->interfaceType,
            $this->unionType,
            $this->enumType,
            $this->inputObjectType
        ];

        foreach ($badUnionTypes as $type) {
            try {
                $union = new UnionType(['name' => 'BadUnion', 'types' => [$type]]);
                $union->assertValid();
                $this->fail('Expected exception not thrown');
            } catch (\Exception $e) {
                $this->assertSame(
                    'BadUnion may only contain Object types, it cannot contain: ' . Utils::printSafe($type) . '.',
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * @it allows a thunk for Union\'s types
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
}
