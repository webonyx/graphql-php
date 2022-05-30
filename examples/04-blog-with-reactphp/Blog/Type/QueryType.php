<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class QueryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => Types::user(),
                    'description' => 'Returns user by id (in range of 1-5)',
                    'args' => [
                        'id' => Types::nonNull(Types::id())
                    ]
                ],
                'viewer' => [
                    'type' => Types::user(),
                    'description' => 'Represents currently logged-in user (for the sake of example - simply returns user with id == 1)'
                ],
                'stories' => [
                    'type' => Types::listOf(Types::story()),
                    'description' => 'Returns subset of stories posted for this blog',
                    'args' => [
                        'after' => [
                            'type' => Types::id(),
                            'description' => 'Fetch stories listed after the story with this ID'
                        ],
                        'limit' => [
                            'type' => Types::int(),
                            'description' => 'Number of stories to be returned',
                            'defaultValue' => 10
                        ]
                    ]
                ],
                'lastStoryPosted' => [
                    'type' => Types::story(),
                    'description' => 'Returns last story posted for this blog'
                ],
                'deprecatedField' => [
                    'type' => Types::string(),
                    'deprecationReason' => 'This field is deprecated!'
                ],
                'fieldWithException' => [
                    'type' => Types::string(),
                    'resolve' => function() {
                        throw new \Exception("Exception message thrown in field resolver");
                    }
                ],
                'hello' => Type::string()
            ],
            'resolveField' => function($rootValue, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($rootValue, $args, $context, $info);
            }
        ];
        parent::__construct($config);
    }

    public function user($rootValue, $args)
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function()use($args){
            $data = DataSource::findUser($args['id']);
            return $data;
        });
        $deferred->resolve();
        return $promise;
    }

    public function viewer($rootValue, $args, AppContext $context)
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function()use($args){
            return $context->viewer;
        });
        $deferred->resolve();
        return $promise;
    }

    public function stories($rootValue, $args)
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function()use($args){
            $args += ['after' => null];
            return DataSource::findStories($args['limit'], $args['after']);
        });
        $deferred->resolve();
        return $promise;

    }

    public function lastStoryPosted()
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function(){
            return DataSource::findLatestStory();
        });
        $deferred->resolve();
        return $promise;
    }

    public function hello()
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function($data){
            return "Your graphql-php endpoint is ready! Use GraphiQL to browse API";
        });
        $deferred->resolve();
        return $promise;
    }

    public function deprecatedField()
    {
        $deferred = new \React\Promise\Deferred();
        $promise = $deferred->promise();
        $promise = $promise->then(function($data){
            return 'You can request deprecated field, but it is not displayed in auto-generated documentation by default.';
        });
        $deferred->resolve();
        return $promise;
    }
}
