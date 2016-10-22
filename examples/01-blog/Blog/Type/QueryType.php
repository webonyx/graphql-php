<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class QueryType extends BaseType
{
    public function __construct(TypeSystem $types)
    {
        $this->definition = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => $types->user(),
                    'description' => 'Returns user by id (in range of 1-5)',
                    'args' => [
                        'id' => $types->nonNull($types->id())
                    ]
                ],
                'viewer' => [
                    'type' => $types->user(),
                    'description' => 'Represents currently logged-in user (for the sake of example - simply returns user with id == 1)'
                ],
                'stories' => [
                    'type' => $types->listOf($types->story()),
                    'description' => 'Returns subset of stories posted for this blog',
                    'args' => [
                        'after' => [
                            'type' => $types->id(),
                            'description' => 'Fetch stories listed after the story with this ID'
                        ],
                        'limit' => [
                            'type' => $types->int(),
                            'description' => 'Number of stories to be returned',
                            'defaultValue' => 10
                        ]
                    ]
                ],
                'lastStoryPosted' => [
                    'type' => $types->story(),
                    'description' => 'Returns last story posted for this blog'
                ],
                'deprecatedField' => [
                    'type' => $types->string(),
                    'deprecationReason' => 'This field is deprecated!'
                ],
                'hello' => Type::string()
            ],
            'resolveField' => function($val, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($val, $args, $context, $info);
            }
        ]);
    }

    public function user($val, $args, AppContext $context)
    {
        return $context->dataSource->findUser($args['id']);
    }

    public function viewer($val, $args, AppContext $context)
    {
        return $context->viewer;
    }

    public function stories($val, $args, AppContext $context)
    {
        $args += ['after' => null];
        return $context->dataSource->findStories($args['limit'], $args['after']);
    }

    public function lastStoryPosted($val, $args, AppContext $context)
    {
        return $context->dataSource->findLatestStory();
    }

    public function hello()
    {
        return 'Your graphql-php endpoint is ready! Use GraphiQL to browse API';
    }

    public function deprecatedField()
    {
        return 'You can request deprecated field, but it is not displayed in auto-generated documentation by default.';
    }
}
