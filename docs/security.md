# Query Complexity Analysis

This is a PHP port of [Query Complexity Analysis](http://sangria-graphql.org/learn/#query-complexity-analysis) in Sangria implementation.

Complexity analysis is a separate validation rule which calculates query complexity score before execution.
Every field in the query gets a default score 1 (including ObjectType nodes). Total complexity of the 
query is the sum of all field scores. For example, the complexity of introspection query is **109**.

If this score exceeds a threshold, a query is not executed and an error is returned instead.

Complexity analysis is disabled by default. To enabled it, add validation rule:

```php
<?php
use GraphQL\GraphQL;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\DocumentValidator;

$rule = new QueryComplexity($maxQueryComplexity = 100);
DocumentValidator::addRule($rule);

GraphQL::executeQuery(/*...*/);
```
This will set the rule globally. Alternatively you can provide validation rules [per execution](executing-queries/#custom-validation-rules).

To customize field score add **complexity** function to field definition:
```php
<?php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$type = new ObjectType([
    'name' => 'MyType',
    'fields' => [
        'someList' => [
            'type' => Type::listOf(Type::string()),
            'args' => [
                'limit' => [
                    'type' => Type::int(),
                    'defaultValue' => 10
                ]
            ],
            'complexity' => function($childrenComplexity, $args) {
                return $childrenComplexity * $args['limit'];
            }
        ]
    ]
]);
```

# Limiting Query Depth

This is a PHP port of [Limiting Query Depth](http://sangria-graphql.org/learn/#limiting-query-depth) in Sangria implementation.
For example max depth of the introspection query is **7**.

It is disabled by default. To enable it, add following validation rule:

```php
<?php
use GraphQL\GraphQL;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\DocumentValidator;

$rule = new QueryDepth($maxDepth = 10);
DocumentValidator::addRule($rule);

GraphQL::executeQuery(/*...*/);
```

This will set the rule globally. Alternatively you can provide validation rules [per execution](executing-queries/#custom-validation-rules).

# Disabling Introspection

This is a PHP port of [graphql-disable-introspection](https://github.com/helfer/graphql-disable-introspection).
It is a separate validation rule which prohibits queries that contain **__type** or **__schema** fields. 

Introspection is enabled by default. To disable it, add following validation rule:

```php
<?php
use GraphQL\GraphQL;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\DocumentValidator;

DocumentValidator::addRule(new DisableIntrospection());

GraphQL::executeQuery(/*...*/);
```
This will set the rule globally. Alternatively you can provide validation rules [per execution](executing-queries/#custom-validation-rules).
