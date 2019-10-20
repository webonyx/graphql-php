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

    /** @var InterfaceType */
    private $node;

    /** @var InterfaceType */
    private $content;

    /** @var ObjectType */
    private $blogStory;

    /** @var ObjectType */
    private $postStoryMutation;

    /** @var InputObjectType */
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
                            'fields' => function () {
                                $this->calls[] = 'Node.fields';

                                return [
                                    'id' => Type::string(),
                                ];
                            },
                            'resolveType' => static function () {
                            },
                        ]);
                        break;

                    case 'Content':
                        $type = new InterfaceType([
                            'name' => 'Content',
                            'fields' => function () {
                                $this->calls[] = 'Content.fields';

                                return [
                                    'title' => Type::string(),
                                    'body' => Type::string(),
                                ];
                            },
                            'resolveType' => static function () {
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
                            'fields' => function () {
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

    public function setUp()
    {
        $this->calls = [];

        $this->node                   = $this->_lazyLoad('Node');
        $this->blogStory              = $this->_lazyLoad('BlogStory');
        $this->content                = $this->_lazyLoad('Content');
        $this->postStoryMutation      = $this->_lazyLoad('PostStoryMutation');
        $this->postStoryMutationInput = $this->_lazyLoad('PostStoryMutationInput');
        $this->query                  = new ObjectType([
            'name'   => 'Query',
            'fields' => function () {
                $this->calls[] = 'Query.fields';

                return [
                    'latestContent' => $this->_lazyLoad('Content'),
                    'node'          => $this->_lazyLoad('Node'),
                ];
            },
        ]);

        $this->mutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => function () {
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

        $this->typeLoader = function ($name) {
            $this->calls[] = $name;
            $prop          = lcfirst($name);

            return $this->{$prop} ?? null;
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
            'typeLoader' => static function () {
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
            'types'    => [Type::resolveLazyType($this->blogStory)],
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
        self::assertSame(Type::resolveLazyType($this->node), $schema->getType('Node'));
        self::assertSame(Type::resolveLazyType($this->content), $schema->getType('Content'));
        self::assertSame(Type::resolveLazyType($this->blogStory), $schema->getType('BlogStory'));
        self::assertSame(Type::resolveLazyType($this->postStoryMutation), $schema->getType('PostStoryMutation'));
        self::assertSame(Type::resolveLazyType($this->postStoryMutationInput), $schema->getType('PostStoryMutationInput'));

        $expectedTypeMap = [
            'Query'                  => $this->query,
            'Mutation'               => $this->mutation,
            'Node'                   => Type::resolveLazyType($this->node),
            'String'                 => Type::string(),
            'Content'                => Type::resolveLazyType($this->content),
            'BlogStory'              => Type::resolveLazyType($this->blogStory),
            'PostStoryMutationInput' => Type::resolveLazyType($this->postStoryMutationInput),
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
        self::assertSame(Type::resolveLazyType($this->node), $node);
        self::assertEquals(['Node'], $this->calls);

        $content = $schema->getType('Content');
        self::assertSame(Type::resolveLazyType($this->content), $content);
        self::assertEquals(['Node', 'Content'], $this->calls);

        $input = $schema->getType('PostStoryMutationInput');
        self::assertSame(Type::resolveLazyType($this->postStoryMutationInput), $input);
        self::assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);

        $result = $schema->isPossibleType(
            Type::resolveLazyType($this->node),
            Type::resolveLazyType($this->blogStory)
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
            'typeLoader' => static function () {
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
            'typeLoader' => static function () {
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
            'typeLoader' => function () {
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
            'typeLoader' => static function () {
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
