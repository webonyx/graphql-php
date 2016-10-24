<?php
namespace GraphQL\Examples\Blog;

use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Utils;

/**
 * Class AppContext
 * Instance available in all GraphQL resolvers as 3rd argument
 *
 * @package GraphQL\Examples\Social
 */
class AppContext
{
    /**
     * @var string
     */
    public $rootUrl;

    /**
     * @var User
     */
    public $viewer;

    /**
     * @var \mixed
     */
    public $request;

    /**
     * @var DataSource
     */
    public $dataSource;
}
