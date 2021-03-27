<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog;

use GraphQL\Examples\Blog\Data\User;

/**
 * Instance available in all GraphQL resolvers as 3rd argument
 */
class AppContext
{
    /** @var string */
    public $rootUrl;

    /** @var User */
    public $viewer;

    /** @var mixed */
    public $request;
}
