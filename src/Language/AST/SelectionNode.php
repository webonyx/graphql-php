<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

interface SelectionNode
{
    /**
     * export type SelectionNode = FieldNode | FragmentSpreadNode | InlineFragmentNode
     */
    public function getKind() : string;
}
