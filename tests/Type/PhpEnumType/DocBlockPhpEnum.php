<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

use GraphQL\Type\Definition\Description;

/** foo */
enum DocBlockPhpEnum
{
    /** ignored */
    #[Description(description: 'preferred')]
    case A;
    /**
     * multi
     * line.
     */
    case B;
}
