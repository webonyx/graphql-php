<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ConstValueNodeVariants from ConstValueNode
 */
class ConstArgumentNode extends Node
{
    public string $kind = NodeKind::ARGUMENT;

    /** @var ConstValueNodeVariants */
    public ConstValueNode $value;

    public NameNode $name;
}
