<?php

declare(strict_types=1);

namespace GraphQL\Type;

class TypeKind
{
    const SCALAR         = 'SCALAR';
    const OBJECT         = 'OBJECT';
    const INTERFACE_KIND = 'INTERFACE_KIND';
    const UNION          = 'UNION';
    const ENUM           = 'ENUM';
    const INPUT_OBJECT   = 'INPUT_OBJECT';
    const LIST_KIND      = 'LIST_KIND';
    const NON_NULL       = 'NON_NULL';
}
