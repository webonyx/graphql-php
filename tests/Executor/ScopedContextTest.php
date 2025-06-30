<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\MyScopedContext;
use GraphQL\Tests\Executor\TestClasses\MySharedContext;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class ScopedContextTest extends TestCase
{
    use ArraySubsetAsserts;

    private Schema $schema;

    private SyncPromiseAdapter $promiseAdapter;

    /** @var array<string, MyScopedContext|MySharedContext> */
    private array $contexts = [];

    protected function setUp(): void
    {
        $this->contexts = [];

        $b = new ObjectType([
            'name' => 'b',
            'fields' => [
                'c' => [
                    'type' => Type::string(),
                    'resolve' => function ($rootValue, $args, $context): string {
                        $context->path[] = 'c';
                        $this->contexts['c'] = $context;

                        return implode('.', $context->path);
                    },
                ],
            ],
        ]);

        $e = new ObjectType([
            'name' => 'e',
            'fields' => [
                'f' => [
                    'type' => Type::string(),
                    'resolve' => function ($rootValue, $args, $context): string {
                        $context->path[] = 'f';
                        $this->contexts['f'] = $context;

                        return implode('.', $context->path);
                    },
                ],
            ],
        ]);

        $d = new ObjectType([
            'name' => 'd',
            'fields' => [
                'e' => [
                    'type' => $e,
                    'resolve' => function ($rootValue, $args, $context) {
                        $context->path[] = 'e';
                        $this->contexts['e'] = $context;

                        return $rootValue;
                    },
                ],
            ],
        ]);

        $a = new ObjectType([
            'name' => 'a',
            'fields' => [
                'b' => [
                    'type' => $b,
                    'resolve' => function ($rootValue, $args, $context) {
                        $context->path[] = 'b';
                        $this->contexts['b'] = $context;

                        return $rootValue;
                    },
                ],
                'd' => [
                    'type' => $d,
                    'resolve' => function ($rootValue, $args, $context) {
                        $context->path[] = 'd';
                        $this->contexts['d'] = $context;

                        return $rootValue;
                    },
                ],
            ],
        ]);

        $h = new ObjectType([
            'name' => 'h',
            'fields' => [
                'i' => [
                    'type' => Type::string(),
                    'resolve' => function ($rootValue, $args, $context): string {
                        $context->path[] = 'i';
                        $this->contexts['i'] = $context;

                        return implode('.', $context->path);
                    },
                ],
            ],
        ]);

        $g = new ObjectType([
            'name' => 'g',
            'fields' => [
                'h' => [
                    'type' => $h,
                    'resolve' => function ($rootValue, $args, $context) {
                        $context->path[] = 'h';
                        $this->contexts['h'] = $context;

                        return $rootValue;
                    },
                ],
            ],
        ]);

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'a' => [
                        'type' => $a,
                        'resolve' => function ($rootValue, $args, $context) {
                            $context->path[] = 'a';
                            $this->contexts['a'] = $context;

                            return $rootValue;
                        },
                    ],
                    'g' => [
                        'type' => $g,
                        'resolve' => function ($rootValue, $args, $context) {
                            $context->path[] = 'g';
                            $this->contexts['g'] = $context;

                            return $rootValue;
                        },
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'a' => [
                        'type' => $a,
                        'resolve' => function ($rootValue, $args, $context) {
                            $context->path[] = 'a';
                            $this->contexts['a'] = $context;

                            return $rootValue;
                        },
                    ],
                    'g' => [
                        'type' => $g,
                        'resolve' => function ($rootValue, $args, $context) {
                            $context->path[] = 'g';
                            $this->contexts['g'] = $context;

                            return $rootValue;
                        },
                    ],
                ],
            ]),
        ]);

        $this->promiseAdapter = new SyncPromiseAdapter();
    }

    public function testQueryContextShouldBeScopedBeforePassingToChildren(): void
    {
        $context = new MyScopedContext();

        $doc = <<<'GRAPHQL'
            query { 
              a {
                b {
                  c
                }
                d {
                  e {
                    f
                  }
                }
              }
              g {
                h {
                  i
                }
              }
            }
            GRAPHQL;

        $result = Executor::promiseToExecute(
            $this->promiseAdapter,
            $this->schema,
            Parser::parse($doc),
            'rootValue',
            $context,
            [],
            null,
            null
        );

        $result = $this->promiseAdapter->wait($result);

        self::assertSame([], $context->path);
        self::assertSame(['a'], $this->contexts['a']->path);
        self::assertSame(['a', 'b'], $this->contexts['b']->path);
        self::assertSame(['a', 'b', 'c'], $this->contexts['c']->path);
        self::assertSame(['a', 'd'], $this->contexts['d']->path);
        self::assertSame(['a', 'd', 'e'], $this->contexts['e']->path);
        self::assertSame(['a', 'd', 'e', 'f'], $this->contexts['f']->path);
        self::assertSame(['g'], $this->contexts['g']->path);
        self::assertSame(['g', 'h'], $this->contexts['h']->path);
        self::assertSame(['g', 'h', 'i'], $this->contexts['i']->path);
        self::assertSame([
            'a' => [
                'b' => [
                    'c' => 'a.b.c',
                ],
                'd' => [
                    'e' => [
                        'f' => 'a.d.e.f',
                    ],
                ],
            ],
            'g' => [
                'h' => [
                    'i' => 'g.h.i',
                ],
            ],
        ], $result->data);
    }

    public function testMutationContextShouldBeScopedBeforePassingToChildren(): void
    {
        $context = new MyScopedContext();

        $doc = <<<'GRAPHQL'
            mutation { 
              a {
                b {
                  c
                }
                d {
                  e {
                    f
                  }
                }
              }
              g {
                h {
                  i
                }
              }
            }
            GRAPHQL;

        $result = Executor::promiseToExecute(
            $this->promiseAdapter,
            $this->schema,
            Parser::parse($doc),
            'rootValue',
            $context,
            [],
            null,
            null
        );

        $result = $this->promiseAdapter->wait($result);

        self::assertSame([], $context->path);
        self::assertSame(['a'], $this->contexts['a']->path);
        self::assertSame(['a', 'b'], $this->contexts['b']->path);
        self::assertSame(['a', 'b', 'c'], $this->contexts['c']->path);
        self::assertSame(['a', 'd'], $this->contexts['d']->path);
        self::assertSame(['a', 'd', 'e'], $this->contexts['e']->path);
        self::assertSame(['a', 'd', 'e', 'f'], $this->contexts['f']->path);
        self::assertSame(['g'], $this->contexts['g']->path);
        self::assertSame(['g', 'h'], $this->contexts['h']->path);
        self::assertSame(['g', 'h', 'i'], $this->contexts['i']->path);
        self::assertSame([
            'a' => [
                'b' => [
                    'c' => 'a.b.c',
                ],
                'd' => [
                    'e' => [
                        'f' => 'a.d.e.f',
                    ],
                ],
            ],
            'g' => [
                'h' => [
                    'i' => 'g.h.i',
                ],
            ],
        ], $result->data);
    }

    public function testContextShouldNotBeScoped(): void
    {
        $context = new MySharedContext();

        $doc = <<<'GRAPHQL'
            query { 
              a {
                b {
                  c
                }
                d {
                  e {
                    f
                  }
                }
              }
              g {
                h {
                  i
                }
              }
            }
            GRAPHQL;

        $result = Executor::promiseToExecute(
            $this->promiseAdapter,
            $this->schema,
            Parser::parse($doc),
            'rootValue',
            $context,
            [],
            null,
            null
        );

        $result = $this->promiseAdapter->wait($result);

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'], $context->path);
        self::assertSame($context, $this->contexts['a']);
        self::assertSame($context, $this->contexts['b']);
        self::assertSame($context, $this->contexts['c']);
        self::assertSame($context, $this->contexts['d']);
        self::assertSame($context, $this->contexts['e']);
        self::assertSame($context, $this->contexts['f']);
        self::assertSame($context, $this->contexts['g']);
        self::assertSame($context, $this->contexts['h']);
        self::assertSame($context, $this->contexts['i']);
        self::assertSame([
            'a' => [
                'b' => [
                    'c' => 'a.b.c',
                ],
                'd' => [
                    'e' => [
                        'f' => 'a.b.c.d.e.f',
                    ],
                ],
            ],
            'g' => [
                'h' => [
                    'i' => 'a.b.c.d.e.f.g.h.i',
                ],
            ],
        ], $result->data);
    }
}
