<?php declare(strict_types=1);

/*
 * The article/product resolver get now resolved asynchronous.
 * This can have big advantages if you fetch data from different microservices.
 * Keep in mind, everything in "call" should now be non-blocking, checkout out async libraries like (amphp/mysql, amphp/http-client).
 */
return new GraphQL\Type\Schema([
    'query' => new GraphQL\Type\Definition\ObjectType([
        'name' => 'Query',
        'fields' => [
            'product' => [
                'type' => GraphQL\Type\Definition\Type::string(),
                'resolve' => function () {
                    return \Amp\call(
                        function() {
                            // use here e.g. amphp/mysql, amphp/http-client, ...
                            return 'xyz';
                        }
                    );
                },
            ],
            'article' => [
                'type' => GraphQL\Type\Definition\Type::string(),
                'resolve' => function () {
                    return \Amp\call(
                        function() {
                            // use here e.g. amphp/mysql, amphp/http-client, ...
                            return 'zyx';
                        }
                    );
                },
            ],
        ],
    ])
]);
