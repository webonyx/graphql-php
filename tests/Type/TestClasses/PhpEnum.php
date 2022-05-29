<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\Deprecated;
use GraphQL\Type\Definition\Description;

#[Description(description: 'foo')]
enum PhpEnum
{
    #[Description(description: 'bar')]
    case A;
    #[Deprecated]
    case B;
    #[Deprecated(reason: 'baz')]
    case C;
}
