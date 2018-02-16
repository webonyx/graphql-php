<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\DirectiveLocation as NewDirectiveLocation;

trigger_error(
    'GraphQL\Type\Definition\DirectiveLocation was moved to GraphQL\Language\DirectiveLocation and will be deleted on next release',
    E_USER_DEPRECATED
);

/**
 * @deprecated moved to GraphQL\Language\DirectiveLocation
 */
class DirectiveLocation extends NewDirectiveLocation
{

}
