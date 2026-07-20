<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

abstract class ServerTestCase extends TestCase
{
    /** @throws InvariantViolation */
    protected function buildSchema(): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f1' => [
                        'type' => Type::string(),
                        'resolve' => static fn ($rootValue, array $args, $context, ResolveInfo $info): string => $info->fieldName,
                    ],
                    'fieldWithPhpError' => [
                        'type' => Type::string(),
                        'resolve' => static function ($rootValue, array $args, $context, ResolveInfo $info): string {
                            trigger_error('deprecated', \E_USER_DEPRECATED);
                            trigger_error('notice', \E_USER_NOTICE);
                            trigger_error('warning', \E_USER_WARNING);

                            return $info->fieldName;
                        },
                    ],
                    'fieldWithSafeException' => [
                        'type' => Type::string(),
                        'resolve' => static function (): void {
                            throw new UserError('This is the exception we want');
                        },
                    ],
                    'fieldWithUnsafeException' => [
                        'type' => Type::string(),
                        'resolve' => static function (): void {
                            throw new Unsafe('This exception should not be shown to the user');
                        },
                    ],
                    'testContextAndRootValue' => [
                        'type' => Type::string(),
                        'resolve' => static function ($rootValue, array $args, $context, ResolveInfo $info): string {
                            $context->testedRootValue = $rootValue;

                            return $info->fieldName;
                        },
                    ],
                    'fieldWithArg' => [
                        'type' => Type::string(),
                        'args' => [
                            'arg' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                        ],
                        'resolve' => static fn ($rootValue, array $args): string => $args['arg'],
                    ],
                    'dfd' => [
                        'type' => Type::string(),
                        'args' => [
                            'num' => [
                                'type' => Type::nonNull(Type::int()),
                            ],
                        ],
                        'resolve' => static function ($rootValue, array $args, array $context): Deferred {
                            $context['buffer']($args['num']);

                            return new Deferred(static fn () => $context['load']($args['num']));
                        },
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'm1' => [
                        'type' => new ObjectType([
                            'name' => 'TestMutation',
                            'fields' => [
                                'result' => Type::string(),
                            ],
                        ]),
                    ],
                ],
            ]),
        ]);
    }
}
