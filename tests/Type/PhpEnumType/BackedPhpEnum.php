<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

enum BackedPhpEnum: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
}
