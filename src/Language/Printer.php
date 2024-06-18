<?php declare(strict_types=1);

namespace GraphQL\Language;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;

/**
 * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
 * Useful for pretty-printing queries or printing back AST for logging, documentation, etc.
 *
 * Usage example:
 *
 * ```php
 * $query = 'query myQuery {someField}';
 * $ast = GraphQL\Language\Parser::parse($query);
 * $printed = GraphQL\Language\Printer::doPrint($ast);
 * ```
 *
 * @see \GraphQL\Tests\Language\PrinterTest
 */
class Printer
{
    /**
     * Converts the AST of a GraphQL node to a string.
     *
     * Handles both executable definitions and schema definitions.
     *
     * @api
     */
    public static function doPrint(Node $ast): string
    {
        static $instance;
        $instance ??= new static();

        return $instance->printAST($ast);
    }

    protected function __construct() {}

    /**
     * Recursively traverse an AST depth-first and produce a pretty string.
     *
     * @throws \JsonException
     */
    public function printAST(Node $node): string
    {
        return $this->p($node);
    }

    /** @throws \JsonException */
    protected function p(?Node $node): string
    {
        if ($node === null) {
            return '';
        }

        switch (true) {
            case $node instanceof ArgumentNode:
            case $node instanceof ObjectFieldNode:
                return $this->p($node->name) . ': ' . $this->p($node->value);

            case $node instanceof BooleanValueNode:
                return $node->value
                    ? 'true'
                    : 'false';

            case $node instanceof DirectiveDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $arg) {
                    $argStrings[] = $this->p($arg);
                }

                $noIndent = true;
                foreach ($argStrings as $argString) {
                    if (\strpos($argString, "\n") !== false) {
                        $noIndent = false;
                        break;
                    }
                }

                return $this->addDescription($node->description, 'directive @'
                    . $this->p($node->name)
                    . ($noIndent
                        ? $this->wrap('(', $this->join($argStrings, ', '), ')')
                        : $this->wrap("(\n", $this->indent($this->join($argStrings, "\n")), "\n"))
                    . ($node->repeatable
                        ? ' repeatable'
                        : '')
                    . ' on ' . $this->printList($node->locations, ' | '));

            case $node instanceof DirectiveNode:
                return '@' . $this->p($node->name) . $this->wrap('(', $this->printList($node->arguments, ', '), ')');

            case $node instanceof DocumentNode:
                return $this->printList($node->definitions, "\n\n") . "\n";

            case $node instanceof EnumTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        'enum',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->values),
                    ],
                    ' '
                ));

            case $node instanceof EnumTypeExtensionNode:
                return $this->join(
                    [
                        'extend enum',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->values),
                    ],
                    ' '
                );

            case $node instanceof EnumValueDefinitionNode:
                return $this->addDescription(
                    $node->description,
                    $this->join([$this->p($node->name), $this->printList($node->directives, ' ')], ' ')
                );

            case $node instanceof EnumValueNode:
            case $node instanceof FloatValueNode:
            case $node instanceof IntValueNode:
            case $node instanceof NameNode:
                return $node->value;

            case $node instanceof FieldDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $item) {
                    $argStrings[] = $this->p($item);
                }

                $noIndent = true;
                foreach ($argStrings as $argString) {
                    if (\strpos($argString, "\n") !== false) {
                        $noIndent = false;
                        break;
                    }
                }

                return $this->addDescription(
                    $node->description,
                    $this->p($node->name)
                    . ($noIndent
                        ? $this->wrap('(', $this->join($argStrings, ', '), ')')
                        : $this->wrap("(\n", $this->indent($this->join($argStrings, "\n")), "\n)"))
                    . ': ' . $this->p($node->type)
                    . $this->wrap(' ', $this->printList($node->directives, ' '))
                );

            case $node instanceof FieldNode:
                $prefix = $this->wrap('', $node->alias->value ?? null, ': ') . $this->p($node->name);

                $argsLine = $prefix . $this->wrap(
                    '(',
                    $this->printList($node->arguments, ', '),
                    ')'
                );
                if (strlen($argsLine) > 80) {
                    $argsLine = $prefix . $this->wrap(
                        "(\n",
                        $this->indent(
                            $this->printList($node->arguments, "\n")
                        ),
                        "\n)"
                    );
                }

                return $this->join(
                    [
                        $argsLine,
                        $this->printList($node->directives, ' '),
                        $this->p($node->selectionSet),
                    ],
                    ' '
                );

            case $node instanceof FragmentDefinitionNode:
                // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                return 'fragment ' . $this->p($node->name)
                    . $this->wrap(
                        '(',
                        $this->printList($node->variableDefinitions ?? new NodeList([]), ', '),
                        ')'
                    )
                    . ' on ' . $this->p($node->typeCondition->name) . ' '
                    . $this->wrap(
                        '',
                        $this->printList($node->directives, ' '),
                        ' '
                    )
                    . $this->p($node->selectionSet);

            case $node instanceof FragmentSpreadNode:
                return '...'
                    . $this->p($node->name)
                    . $this->wrap(' ', $this->printList($node->directives, ' '));

            case $node instanceof InlineFragmentNode:
                return $this->join(
                    [
                        '...',
                        $this->wrap('on ', $this->p($node->typeCondition->name ?? null)),
                        $this->printList($node->directives, ' '),
                        $this->p($node->selectionSet),
                    ],
                    ' '
                );

            case $node instanceof InputObjectTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        'input',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof InputObjectTypeExtensionNode:
                return $this->join(
                    [
                        'extend input',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof InputValueDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        $this->p($node->name) . ': ' . $this->p($node->type),
                        $this->wrap('= ', $this->p($node->defaultValue)),
                        $this->printList($node->directives, ' '),
                    ],
                    ' '
                ));

            case $node instanceof InterfaceTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        'interface',
                        $this->p($node->name),
                        $this->wrap('implements ', $this->printList($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof InterfaceTypeExtensionNode:
                return $this->join(
                    [
                        'extend interface',
                        $this->p($node->name),
                        $this->wrap('implements ', $this->printList($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof ListTypeNode:
                return '[' . $this->p($node->type) . ']';

            case $node instanceof ListValueNode:
                return '[' . $this->printList($node->values, ', ') . ']';

            case $node instanceof NamedTypeNode:
                return $this->p($node->name);

            case $node instanceof NonNullTypeNode:
                return $this->p($node->type) . '!';

            case $node instanceof NullValueNode:
                return 'null';

            case $node instanceof ObjectTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        'type',
                        $this->p($node->name),
                        $this->wrap('implements ', $this->printList($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof ObjectTypeExtensionNode:
                return $this->join(
                    [
                        'extend type',
                        $this->p($node->name),
                        $this->wrap('implements ', $this->printList($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof ObjectValueNode:
                return "{ {$this->printList($node->fields, ', ')} }";

            case $node instanceof OperationDefinitionNode:
                $op = $node->operation;
                $name = $this->p($node->name);
                $varDefs = $this->wrap('(', $this->printList($node->variableDefinitions, ', '), ')');
                $directives = $this->printList($node->directives, ' ');
                $selectionSet = $this->p($node->selectionSet);

                // Anonymous queries with no directives or variable definitions can use
                // the query short form.
                return $name === '' && $directives === '' && $varDefs === '' && $op === 'query'
                    ? $selectionSet
                    : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');

            case $node instanceof OperationTypeDefinitionNode:
                return $node->operation . ': ' . $this->p($node->type);

            case $node instanceof ScalarTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join([
                    'scalar',
                    $this->p($node->name),
                    $this->printList($node->directives, ' '),
                ], ' '));

            case $node instanceof ScalarTypeExtensionNode:
                return $this->join(
                    [
                        'extend scalar',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                    ],
                    ' '
                );

            case $node instanceof SchemaDefinitionNode:
                return $this->join(
                    [
                        'schema',
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->operationTypes),
                    ],
                    ' '
                );

            case $node instanceof SchemaExtensionNode:
                return $this->join(
                    [
                        'extend schema',
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->operationTypes),
                    ],
                    ' '
                );

            case $node instanceof SelectionSetNode:
                return $this->printListBlock($node->selections);

            case $node instanceof StringValueNode:
                if ($node->block) {
                    return BlockString::print($node->value);
                }

                return \json_encode($node->value, JSON_THROW_ON_ERROR);

            case $node instanceof UnionTypeDefinitionNode:
                $typesStr = $this->printList($node->types, ' | ');

                return $this->addDescription($node->description, $this->join(
                    [
                        'union',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                ));

            case $node instanceof UnionTypeExtensionNode:
                $typesStr = $this->printList($node->types, ' | ');

                return $this->join(
                    [
                        'extend union',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                );

            case $node instanceof VariableDefinitionNode:
                return '$' . $this->p($node->variable->name)
                    . ': '
                    . $this->p($node->type)
                    . $this->wrap(' = ', $this->p($node->defaultValue))
                    . $this->wrap(' ', $this->printList($node->directives, ' '));

            case $node instanceof VariableNode:
                return '$' . $this->p($node->name);
        }

        return '';
    }

    /**
     * @template TNode of Node
     *
     * @param NodeList<TNode> $list
     *
     * @throws \JsonException
     */
    protected function printList(NodeList $list, string $separator = ''): string
    {
        $parts = [];
        foreach ($list as $item) {
            $parts[] = $this->p($item);
        }

        return $this->join($parts, $separator);
    }

    /**
     * Print each item on its own line, wrapped in an indented "{ }" block.
     *
     * @template TNode of Node
     *
     * @param NodeList<TNode> $list
     *
     * @throws \JsonException
     */
    protected function printListBlock(NodeList $list): string
    {
        if (\count($list) === 0) {
            return '';
        }

        $parts = [];
        foreach ($list as $item) {
            $parts[] = $this->p($item);
        }

        return "{\n" . $this->indent($this->join($parts, "\n")) . "\n}";
    }

    /** @throws \JsonException */
    protected function addDescription(?StringValueNode $description, string $body): string
    {
        return $this->join([$this->p($description), $body], "\n");
    }

    /**
     * If maybeString is not null or empty, then wrap with start and end, otherwise
     * print an empty string.
     */
    protected function wrap(string $start, ?string $maybeString, string $end = ''): string
    {
        if ($maybeString === null || $maybeString === '') {
            return '';
        }

        return $start . $maybeString . $end;
    }

    protected function indent(string $string): string
    {
        if ($string === '') {
            return '';
        }

        return '  ' . \str_replace("\n", "\n  ", $string);
    }

    /** @param array<string|null> $parts */
    protected function join(array $parts, string $separator = ''): string
    {
        return \implode($separator, \array_filter($parts));
    }
}
