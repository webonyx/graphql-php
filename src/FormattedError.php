<?php
namespace GraphQL;

trigger_error(
    'GraphQL\FormattedError was moved to GraphQL\Error\FormattedError and will be deleted on next release',
    E_USER_DEPRECATED
);

/**
 * Class FormattedError
 * @deprecated since 2016-10-21 in favor of GraphQL\Error\FormattedError
 * @package GraphQL
 */
class FormattedError extends \GraphQL\Error\FormattedError
{
}
