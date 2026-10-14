<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ValueNodeVariants from ValueNode
 */
class ArgumentNode extends Node
{
    public string $kind = NodeKind::ARGUMENT;

    /** @var ValueNodeVariants */
    public ValueNode $value;

    public NameNode $name;
}
