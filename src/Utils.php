<?php
namespace GraphQL;

trigger_error(
    'GraphQL\Utils was moved to GraphQL\Utils\Utils and will be deleted on next release',
    E_USER_DEPRECATED
);

class Utils extends \GraphQL\Utils\Utils
{
}
