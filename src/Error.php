<?php
namespace GraphQL;

trigger_error(
    'GraphQL\Error was moved to GraphQL\Error\Error and will be deleted on next release',
    E_USER_DEPRECATED
);


/**
 * Class Error
 *
 * @deprecated since 2016-10-21 in favor of GraphQL\Error\Error
 * @package GraphQL
 */
class Error extends \GraphQL\Error\Error
{
}
