<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

enum StringPhpEnum: string
{
    case A = "a_string";
    case B = "b_string";
}
