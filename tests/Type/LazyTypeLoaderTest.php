<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use function lcfirst;

class LazyTypeLoaderTest extends TestCase
{
    /** @var ObjectType */
    private $query;

    /** @var ObjectType */
    private $mutation;

    /** @var callable */
    private $node;

    /** @var callable */
    private $content;

    /** @var callable */
    private $blogStory;

    /** @var callable */
    private $postStoryMutation;

    /** @var callable */
    private $postStoryMutationInput;

    /** @var callable */
    private $typeLoader;

    /** @var string[] */
    private $calls;

    /** @var Type[] */
    private $loadedTypes = [];

    protected function _lazyLoad(string $name)
    {
        return function () use ($name) {
            if (! isset($this->loadedTypes[$name])) {
                $type = null;
                switch ($name) {
                    case 'Node':
                        $type = new InterfaceType([
                            'name' => 'Node',
                            'fields' => function () : array {
                                $this->calls[] = 'Node.fields';

                                return [
                                    'id' => Type::string(),
                                ];
                            },
                            'resolveType' => static function () : void {
                            },
                        ]);
                        break;

                    case 'Content':
                        $type = new InterfaceType([
                            'name' => 'Content',
                            'fields' => function () : array {
                                $this->calls[] = 'Content.fields';

                                return [
                                    'title' => Type::string(),
                                    'body' => Type::string(),
                                ];
                            },
                            'resolveType' => static function () : void {
                            },
                        ]);
                        break;

                    case 'BlogStory':
                        $type = new ObjectType([
                            'name' => 'BlogStory',
                            'interfaces' => [
                                $this->node,
                                $this->content,
                            ],
                            'fields' => function () : array {
                                $this->calls[] = 'BlogStory.fields';

                                return [
                                    'id' => Type::string(),
                                    'title' => Type::string(),
                                    'body' => Type::string(),
                                ];
                            },
                        ]);
                        break;

                    case 'PostStoryMutation':
                        $type = new ObjectType([
                            'name'   => 'PostStoryMutation',
                            'fields' => [
                                'story' => $this->blogStory,
                            ],
                        ]);
                        break;

                    case 'PostStoryMutationInput':
                        $type = new InputObjectType([
                            'name'   => 'PostStoryMutationInput',
                            'fields' => [
                                'title'    => Type::string(),
                                'body'     => Type::string(),
                                'author'   => Type::id(),
                                'category' => Type::id(),
                            ],
                        ]);
                        break;
                }
                $this->loadedTypes[$name] = $type;
            }

            return $this->loadedTypes[$name];
        };
    }

    public function setUp() : void
    {
        $this->calls = [];

        $this->node                   = $this->_lazyLoad('Node');
        $this->blogStory              = $this->_lazyLoad('BlogStory');
        $this->content                = $this->_lazyLoad('Content');
        $this->postStoryMutation      = $this->_lazyLoad('PostStoryMutation');
        $this->postStoryMutationInput = $this->_lazyLoad('PostStoryMutationInput');
        $this->query                  = new ObjectType([
            'name'   => 'Query',
            'fields' => function () : array {
                $this->calls[] = 'Query.fields';

                return [
                    'latestContent' => $this->_lazyLoad('Content'),
                    'node'          => $this->_lazyLoad('Node'),
                ];
            },
        ]);

        $this->mutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => function () : array {
                $this->calls[] = 'Mutation.fields';

                return [
                    'postStory' => [
                        'type' => $this->postStoryMutation,
                        'args' => [
                            'input'           => Type::nonNull($this->postStoryMutationInput),
                            'clientRequestId' => Type::string(),
                        ],
                    ],
                ];
            },
        ]);

        $this->typeLoader = function ($name) : ?callable {
            $this->calls[] = $name;
            $prop          = lcfirst($name);

            switch ($prop) {
                case 'node':
                    return $this->node;
                case 'blogStory':
                    return $this->blogStory;
                case 'content':
                    return $this->content;
                case 'postStoryMutation':
                    return $this->postStoryMutation;
                case 'postStoryMutationInput':
                    return $this->postStoryMutationInput;
            }

            return null;
        };
    }

    public function testSchemaAcceptsTypeLoader() : void
    {
        $this->expectNotToPerformAssertions();
        new Schema([
            'query'      => new ObjectType([
                'name'   => 'Query',
                'fields' => ['a' => Type::string()],
            ]),
            'typeLoader' => static function () : void {
            },
        ]);
    }

    public function testSchemaRejectsNonCallableTypeLoader() : void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Schema type loader must be callable if provided but got: []');

        new Schema([
            'query'      => new ObjectType([
                'name'   => 'Query',
                'fields' => ['a' => Type::string()],
            ]),
            'typeLoader' => [],
        ]);
    }

    public function testWorksWithoutTypeLoader() : void
    {
        $schema = new Schema([
            'query'    => $this->query,
            'mutation' => $this->mutation,
            'types'    => [Schema::resolveType($this->blogStory)],
        ]);

        $expected = [
            'Query.fields',
            'Content.fields',
            'Node.fields',
            'Mutation.fields',
            'BlogStory.fields',
        ];
        self::assertEquals($expected, $this->calls);

        self::assertSame($this->query, $schema->getType('Query'));
        self::assertSame($this->mutation, $schema->getType('Mutation'));
        self::assertSame(Schema::resolveType($this->node), $schema->getType('Node'));
        self::assertSame(Schema::resolveType($this->content), $schema->getType('Content'));
        self::assertSame(Schema::resolveType($this->blogStory), $schema->getType('BlogStory'));
        self::assertSame(Schema::resolveType($this->postStoryMutation), $schema->getType('PostStoryMutation'));
        self::assertSame(Schema::resolveType($this->postStoryMutationInput), $schema->getType('PostStoryMutationInput'));

        $expectedTypeMap = [
            'Query'                  => $this->query,
            'Mutation'               => $this->mutation,
            'Node'                   => Schema::resolveType($this->node),
            'String'                 => Type::string(),
            'Content'                => Schema::resolveType($this->content),
            'BlogStory'              => Schema::resolveType($this->blogStory),
            'PostStoryMutationInput' => Schema::resolveType($this->postStoryMutationInput),
        ];

        self::assertArraySubset($expectedTypeMap, $schema->getTypeMap());
    }

    public function testWorksWithTypeLoader() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'mutation'   => $this->mutation,
            'typeLoader' => $this->typeLoader,
        ]);
        self::assertEquals([], $this->calls);

        $node = $schema->getType('Node');
        self::assertSame(Schema::resolveType($this->node), $node);
        self::assertEquals(['Node'], $this->calls);

        $content = $schema->getType('Content');
        self::assertSame(Schema::resolveType($this->content), $content);
        self::assertEquals(['Node', 'Content'], $this->calls);

        $input = $schema->getType('PostStoryMutationInput');
        self::assertSame(Schema::resolveType($this->postStoryMutationInput), $input);
        self::assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);

        $result = $schema->isPossibleType(
            Schema::resolveType($this->node),
            Schema::resolveType($this->blogStory)
        );
        self::assertTrue($result);
        self::assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);
    }

    public function testOnlyCallsLoaderOnce() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => $this->typeLoader,
        ]);

        $schema->getType('Node');
        self::assertEquals(['Node'], $this->calls);

        $schema->getType('Node');
        self::assertEquals(['Node'], $this->calls);
    }

    public function testFailsOnNonExistentType() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => static function () : void {
            },
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Type loader is expected to return a callable or valid type "NonExistingType", but it returned null');

        $schema->getType('NonExistingType');
    }

    public function testFailsOnNonType() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => static function () : stdClass {
                return new stdClass();
            },
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Type loader is expected to return a callable or valid type "Node", but it returned instance of stdClass');

        $schema->getType('Node');
    }

    public function testFailsOnInvalidLoad() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => function () : callable {
                return $this->content;
            },
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Type loader is expected to return type "Node", but it returned "Content"');

        $schema->getType('Node');
    }

    public function testPassesThroughAnExceptionInLoader() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => static function () : void {
                throw new Exception('This is the exception we are looking for');
            },
        ]);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('This is the exception we are looking for');

        $schema->getType('Node');
    }

    public function testReturnsIdenticalResults() : void
    {
        $withoutLoader = new Schema([
            'query'    => $this->query,
            'mutation' => $this->mutation,
        ]);

        $withLoader = new Schema([
            'query'      => $this->query,
            'mutation'   => $this->mutation,
            'typeLoader' => $this->typeLoader,
        ]);

        self::assertSame($withoutLoader->getQueryType(), $withLoader->getQueryType());
        self::assertSame($withoutLoader->getMutationType(), $withLoader->getMutationType());
        self::assertSame($withoutLoader->getType('BlogStory'), $withLoader->getType('BlogStory'));
        self::assertSame($withoutLoader->getDirectives(), $withLoader->getDirectives());
    }

    public function testSkipsLoaderForInternalTypes() : void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'mutation'   => $this->mutation,
            'typeLoader' => $this->typeLoader,
        ]);

        $type = $schema->getType('ID');
        self::assertSame(Type::id(), $type);
        self::assertEquals([], $this->calls);
    }
}
