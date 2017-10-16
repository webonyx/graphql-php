<?php
namespace GraphQL\Error;

/**
 * Class UserError
 *
 * Error caused by actions of GraphQL clients. Can be safely displayed to a client...
 *
 * @package GraphQL\Error
 */
class UserError extends \RuntimeException implements ClientAware
{
    /**
     * @return bool
     */
    public function isClientSafe()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return 'user';
    }
}
