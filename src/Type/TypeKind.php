<?php

declare(strict_types=1);

namespace GraphQL\Type;

class TypeKind
{
    const SCALAR       = 'SCALAR';
    const OBJECT       = 'OBJECT';
    const INTERFACE    = 'INTERFACE';
    const UNION        = 'UNION';
    const ENUM         = 'ENUM';
    const INPUT_OBJECT = 'INPUT_OBJECT';
    const LIST         = 'LIST';
    const NON_NULL     = 'NON_NULL';
}
