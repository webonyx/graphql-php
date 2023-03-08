<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ValueNodeVariants from ValueNode
 */
class ObjectFieldNode extends Node
{
    public string $kind = NodeKind::OBJECT_FIELD;

    public NameNode $name;

    /** @var ValueNodeVariants */
    public ValueNode $value;
}
