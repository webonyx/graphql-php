<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

use GraphQL\Type\Definition\Description;

#[Description(description: 'foo')]
// @phpstan-ignore-next-line intentionally wrong
#[Description(description: 'bar')]
enum MultipleDescriptionsPhpEnum
{
    case A;
}
