<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\PhpEnumType;

enum StringPhpEnum: string
{
    case A = 'Avalue';
    case B = 'Bvalue';
    case C = 'Cvalue';
}
