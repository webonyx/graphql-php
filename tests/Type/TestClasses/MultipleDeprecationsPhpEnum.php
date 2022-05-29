<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\Deprecated;

enum MultipleDeprecationsPhpEnum
{
    #[Deprecated]
    // @phpstan-ignore-next-line intentionally wrong
    #[Deprecated(reason: 'foo')]
    case A;
}
