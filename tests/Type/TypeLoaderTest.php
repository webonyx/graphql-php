<?php
namespace GraphQL\Tests\Type;


use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class TypeLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectType
     */
    private $query;

    /**
     * @var ObjectType
     */
    private $mutation;

    /**
     * @var InterfaceType
     */
    private $node;

    /**
     * @var InterfaceType
     */
    private $content;

    /**
     * @var ObjectType
     */
    private $blogStory;

    /**
     * @var ObjectType
     */
    private $postStoryMutation;

    /**
     * @var InputObjectType
     */
    private $postStoryMutationInput;

    /**
     * @var callable
     */
    private $typeLoader;

    /**
     * @var array
     */
    private $calls;

    public function setUp()
    {
        $this->calls = [];

        $this->node = new InterfaceType([
            'name' => 'Node',
            'fields' => function() {
                $this->calls[] = 'Node.fields';
                return [
                    'id' => Type::string()
                ];
            },
            'resolveType' => function() {}
        ]);

        $this->content = new InterfaceType([
            'name' => 'Content',
            'fields' => function() {
                $this->calls[] = 'Content.fields';
                return [
                    'title' => Type::string(),
                    'body' => Type::string(),
                ];
            },
            'resolveType' => function() {}
        ]);

        $this->blogStory = new ObjectType([
            'name' => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                $this->calls[] = 'BlogStory.fields';
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                ];
            },
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => function() {
                $this->calls[] = 'Query.fields';
                return [
                    'latestContent' => $this->content,
                    'node' => $this->node,
                ];
            }
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => function() {
                $this->calls[] = 'Mutation.fields';
                return [
                    'postStory' => [
                        'type' => $this->postStoryMutation,
                        'args' => [
                            'input' => Type::nonNull($this->postStoryMutationInput),
                            'clientRequestId' => Type::string()
                        ]
                    ]
                ];
            }
        ]);

        $this->postStoryMutation = new ObjectType([
            'name' => 'PostStoryMutation',
            'fields' => [
                'story' => $this->blogStory
            ]
        ]);

        $this->postStoryMutationInput = new InputObjectType([
            'name' => 'PostStoryMutationInput',
            'fields' => [
                'title' => Type::string(),
                'body' => Type::string(),
                'author' => Type::id(),
                'category' => Type::id()
            ]
        ]);

        $this->typeLoader = function($name) {
            $this->calls[] = $name;
            $prop = lcfirst($name);
            return isset($this->{$prop}) ? $this->{$prop} : null;
        };
    }

    public function testSchemaAcceptsTypeLoader()
    {
        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => Type::string()]
            ]),
            'typeLoader' => function() {}
        ]);
    }

    public function testSchemaRejectsNonCallableTypeLoader()
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Schema type loader must be callable if provided but got: array(0)'
        );

        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => Type::string()]
            ]),
            'typeLoader' => []
        ]);
    }

    public function testWorksWithoutTypeLoader()
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'types' => [$this->blogStory]
        ]);

        $expected = [
            'Query.fields',
            'Content.fields',
            'Node.fields',
            'Mutation.fields',
            'BlogStory.fields',
        ];
        $this->assertEquals($expected, $this->calls);

        $this->assertSame($this->query, $schema->getType('Query'));
        $this->assertSame($this->mutation, $schema->getType('Mutation'));
        $this->assertSame($this->node, $schema->getType('Node'));
        $this->assertSame($this->content, $schema->getType('Content'));
        $this->assertSame($this->blogStory, $schema->getType('BlogStory'));
        $this->assertSame($this->postStoryMutation, $schema->getType('PostStoryMutation'));
        $this->assertSame($this->postStoryMutationInput, $schema->getType('PostStoryMutationInput'));

        $expectedTypeMap = [
            'Query' => $this->query,
            'Mutation' => $this->mutation,
            'Node' => $this->node,
            'String' => Type::string(),
            'Content' => $this->content,
            'BlogStory' => $this->blogStory,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
        ];

        $this->assertArraySubset($expectedTypeMap, $schema->getTypeMap());
    }

    public function testWorksWithTypeLoader()
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);
        $this->assertEquals([], $this->calls);

        $node = $schema->getType('Node');
        $this->assertSame($this->node, $node);
        $this->assertEquals(['Node'], $this->calls);

        $content = $schema->getType('Content');
        $this->assertSame($this->content, $content);
        $this->assertEquals(['Node', 'Content'], $this->calls);

        $input = $schema->getType('PostStoryMutationInput');
        $this->assertSame($this->postStoryMutationInput, $input);
        $this->assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);

        $result = $schema->isPossibleType($this->node, $this->blogStory);
        $this->assertTrue($result);
        $this->assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);
    }

    public function testOnlyCallsLoaderOnce()
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => $this->typeLoader
        ]);

        $schema->getType('Node');
        $this->assertEquals(['Node'], $this->calls);

        $schema->getType('Node');
        $this->assertEquals(['Node'], $this->calls);
    }

    public function testFailsOnNonExistentType()
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {}
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return valid type "NonExistingType", but it returned null'
        );

        $schema->getType('NonExistingType');
    }

    public function testFailsOnNonType()
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                return new \stdClass();
            }
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return valid type "Node", but it returned instance of stdClass'
        );

        $schema->getType('Node');
    }

    public function testFailsOnInvalidLoad()
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                return $this->content;
            }
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return type "Node", but it returned "Content"'
        );

        $schema->getType('Node');
    }

    public function testPassesThroughAnExceptionInLoader()
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                throw new \Exception("This is the exception we are looking for");
            }
        ]);

        $this->setExpectedException(
            \Exception::class,
            'This is the exception we are looking for'
        );

        $schema->getType('Node');
    }

    public function testReturnsIdenticalResults()
    {
        $withoutLoader = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation
        ]);

        $withLoader = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);

        $this->assertSame($withoutLoader->getQueryType(), $withLoader->getQueryType());
        $this->assertSame($withoutLoader->getMutationType(), $withLoader->getMutationType());
        $this->assertSame($withoutLoader->getType('BlogStory'), $withLoader->getType('BlogStory'));
        $this->assertSame($withoutLoader->getDirectives(), $withLoader->getDirectives());
    }

    public function testSkipsLoaderForInternalTypes()
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);

        $type = $schema->getType('ID');
        $this->assertSame(Type::id(), $type);
        $this->assertEquals([], $this->calls);
    }
}
