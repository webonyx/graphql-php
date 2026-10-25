<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ConstValueNodeVariants from ConstValueNode
 */
class ConstObjectFieldNode extends Node
{
    public string $kind = NodeKind::OBJECT_FIELD;

    public NameNode $name;

    /** @var ConstValueNodeVariants */
    public ConstValueNode $value;
}
