<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

interface WrappingType
{
    public function getWrappedType(bool $recurse = false) : Type;
}
