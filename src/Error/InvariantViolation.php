<?php
namespace GraphQL\Error;

/**
 * Class InvariantVoilation
 *
 * Note:
 * This exception should not inherit base Error exception as it is raised when there is an error somewhere in
 * user-land code
 *
 * @package GraphQL\Error
 */
class InvariantViolation extends \LogicException
{
}
