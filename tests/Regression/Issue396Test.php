<?php

declare(strict_types=1);

namespace GraphQL\Tests\Regression;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use function stristr;

/**
 * @see https://github.com/webonyx/graphql-php/issues/396
 */
class Issue396Test extends TestCase
{
    public function testUnionResolveType()
    {
        $a = new ObjectType(['name' => 'A', 'fields' => ['name' => Type::string()]]);
        $b = new ObjectType(['name' => 'B', 'fields' => ['name' => Type::string()]]);
        $c = new ObjectType(['name' => 'C', 'fields' => ['name' => Type::string()]]);

        $log = [];

        $unionResult = new UnionType([
            'name' => 'UnionResult',
            'types' => [$a, $b, $c],
            'resolveType' => static function ($result, $value, ResolveInfo $info) use ($a, $b, $c, &$log) : ?Type {
                $log[] = [$result, $info->path];
                if (stristr($result['name'], 'A')) {
                    return $a;
                }
                if (stristr($result['name'], 'B')) {
                    return $b;
                }
                if (stristr($result['name'], 'C')) {
                    return $c;
                }

                return null;
            },
        ]);

        $exampleType = new ObjectType([
            'name' => 'Example',
            'fields' => [
                'field' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($unionResult))),
                    'resolve' => static function () : array {
                        return [
                            ['name' => 'A 1'],
                            ['name' => 'B 2'],
                            ['name' => 'C 3'],
                        ];
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $exampleType]);

        $query = '
            query {
                field {
                    ... on A {
                        name
                    }
                    ... on B {
                        name
                    }
                    ... on C {
                        name
                    }
                }
            }
        ';

        GraphQL::executeQuery($schema, $query);

        $expected = [
            [['name' => 'A 1'], ['field', 0]],
            [['name' => 'B 2'], ['field', 1]],
            [['name' => 'C 3'], ['field', 2]],
        ];
        self::assertEquals($expected, $log);
    }

    public function testInterfaceResolveType()
    {
        $log = [];

        $interfaceResult = new InterfaceType([
            'name' => 'InterfaceResult',
            'fields' => [
                'name' => Type::string(),
            ],
            'resolveType' => static function ($result, $value, ResolveInfo $info) use (&$a, &$b, &$c, &$log) : ?Type {
                $log[] = [$result, $info->path];
                if (stristr($result['name'], 'A')) {
                    return $a;
                }
                if (stristr($result['name'], 'B')) {
                    return $b;
                }
                if (stristr($result['name'], 'C')) {
                    return $c;
                }

                return null;
            },
        ]);

        $a = new ObjectType(['name' => 'A', 'fields' => ['name' => Type::string()], 'interfaces' => [$interfaceResult]]);
        $b = new ObjectType(['name' => 'B', 'fields' => ['name' => Type::string()], 'interfaces' => [$interfaceResult]]);
        $c = new ObjectType(['name' => 'C', 'fields' => ['name' => Type::string()], 'interfaces' => [$interfaceResult]]);

        $exampleType = new ObjectType([
            'name' => 'Example',
            'fields' => [
                'field' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($interfaceResult))),
                    'resolve' => static function () : array {
                        return [
                            ['name' => 'A 1'],
                            ['name' => 'B 2'],
                            ['name' => 'C 3'],
                        ];
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $exampleType,
            'types' => [$a, $b, $c],
        ]);

        $query = '
            query {
                field {
                    name
                }
            }
        ';

        GraphQL::executeQuery($schema, $query);

        $expected = [
            [['name' => 'A 1'], ['field', 0]],
            [['name' => 'B 2'], ['field', 1]],
            [['name' => 'C 3'], ['field', 2]],
        ];
        self::assertEquals($expected, $log);
    }
}
