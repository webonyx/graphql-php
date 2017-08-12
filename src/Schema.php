<?php
namespace GraphQL;


/**
 * Schema Definition
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor.
 *
 * Example:
 *
 *     $schema = new GraphQL\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * Note: If an array of `directives` are provided to GraphQL\Schema, that will be
 * the exact list of directives represented and allowed. If `directives` is not
 * provided then a default set of the specified directives (e.g. @include and
 * @skip) will be used. If you wish to provide *additional* directives to these
 * specified directives, you must explicitly declare them. Example:
 *
 *     $mySchema = new GraphQL\Schema([
 *       ...
 *       'directives' => array_merge(GraphQL::getInternalDirectives(), [ $myCustomDirective ]),
 *     ])
 *
 * @package GraphQL
 * @deprecated moved to GraphQL\Type\Schema
 */
class Schema extends \GraphQL\Type\Schema
{
}
