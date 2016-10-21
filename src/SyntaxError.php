<?php
namespace GraphQL;

trigger_error(
    'GraphQL\SyntaxError was moved to GraphQL\Error\SyntaxError and will be deleted on next release',
    E_USER_DEPRECATED
);

/**
 * Class SyntaxError
 * @deprecated since 2016-10-21 in favor of GraphQL\Error\SyntaxError
 * @package GraphQL
 */
class SyntaxError extends \GraphQL\Error\SyntaxError
{
}
