<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

enum BackedPhpEnum: string
{
    case A = 'a';
    case B = 'B';
    case C = 'the value does not really matter';
}
