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
     * @throws \JsonException
     *
     * @api
     */
    public static function doPrint(Node $ast): string
    {
        return static::p($ast);
    }

    /** @throws \JsonException */
    protected static function p(?Node $node): string
    {
        if ($node === null) {
            return '';
        }

        switch (true) {
            case $node instanceof ArgumentNode:
            case $node instanceof ObjectFieldNode:
                return static::p($node->name) . ': ' . static::p($node->value);

            case $node instanceof BooleanValueNode:
                return $node->value
                    ? 'true'
                    : 'false';

            case $node instanceof DirectiveDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $arg) {
                    $argStrings[] = static::p($arg);
                }

                $noIndent = true;
                foreach ($argStrings as $argString) {
                    if (strpos($argString, "\n") !== false) {
                        $noIndent = false;
                        break;
                    }
                }

                return static::addDescription($node->description, 'directive @'
                    . static::p($node->name)
                    . ($noIndent
                        ? static::wrap('(', static::join($argStrings, ', '), ')')
                        : static::wrap("(\n", static::indent(static::join($argStrings, "\n")), "\n"))
                    . ($node->repeatable
                        ? ' repeatable'
                        : '')
                    . ' on ' . static::printList($node->locations, ' | '));

            case $node instanceof DirectiveNode:
                return '@' . static::p($node->name) . static::wrap('(', static::printList($node->arguments, ', '), ')');

            case $node instanceof DocumentNode:
                return static::printList($node->definitions, "\n\n") . "\n";

            case $node instanceof EnumTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'enum',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->values),
                    ],
                    ' '
                ));

            case $node instanceof EnumTypeExtensionNode:
                return static::join(
                    [
                        'extend enum',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->values),
                    ],
                    ' '
                );

            case $node instanceof EnumValueDefinitionNode:
                return static::addDescription(
                    $node->description,
                    static::join([static::p($node->name), static::printList($node->directives, ' ')], ' ')
                );

            case $node instanceof EnumValueNode:
            case $node instanceof FloatValueNode:
            case $node instanceof IntValueNode:
            case $node instanceof NameNode:
                return $node->value;

            case $node instanceof FieldDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $item) {
                    $argStrings[] = static::p($item);
                }

                $noIndent = true;
                foreach ($argStrings as $argString) {
                    if (strpos($argString, "\n") !== false) {
                        $noIndent = false;
                        break;
                    }
                }

                return static::addDescription(
                    $node->description,
                    static::p($node->name)
                    . ($noIndent
                        ? static::wrap('(', static::join($argStrings, ', '), ')')
                        : static::wrap("(\n", static::indent(static::join($argStrings, "\n")), "\n)"))
                    . ': ' . static::p($node->type)
                    . static::wrap(' ', static::printList($node->directives, ' '))
                );

            case $node instanceof FieldNode:
                $prefix = static::wrap('', $node->alias->value ?? null, ': ') . static::p($node->name);

                $argsLine = $prefix . static::wrap(
                    '(',
                    static::printList($node->arguments, ', '),
                    ')'
                );
                if (strlen($argsLine) > 80) {
                    $argsLine = $prefix . static::wrap(
                        "(\n",
                        static::indent(
                            static::printList($node->arguments, "\n")
                        ),
                        "\n)"
                    );
                }

                return static::join(
                    [
                        $argsLine,
                        static::printList($node->directives, ' '),
                        static::p($node->selectionSet),
                    ],
                    ' '
                );

            case $node instanceof FragmentDefinitionNode:
                // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                return 'fragment ' . static::p($node->name)
                    . static::wrap(
                        '(',
                        static::printList($node->variableDefinitions ?? new NodeList([]), ', '),
                        ')'
                    )
                    . ' on ' . static::p($node->typeCondition->name) . ' '
                    . static::wrap(
                        '',
                        static::printList($node->directives, ' '),
                        ' '
                    )
                    . static::p($node->selectionSet);

            case $node instanceof FragmentSpreadNode:
                return '...'
                    . static::p($node->name)
                    . static::wrap(' ', static::printList($node->directives, ' '));

            case $node instanceof InlineFragmentNode:
                return static::join(
                    [
                        '...',
                        static::wrap('on ', static::p($node->typeCondition->name ?? null)),
                        static::printList($node->directives, ' '),
                        static::p($node->selectionSet),
                    ],
                    ' '
                );

            case $node instanceof InputObjectTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'input',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof InputObjectTypeExtensionNode:
                return static::join(
                    [
                        'extend input',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof InputValueDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        static::p($node->name) . ': ' . static::p($node->type),
                        static::wrap('= ', static::p($node->defaultValue)),
                        static::printList($node->directives, ' '),
                    ],
                    ' '
                ));

            case $node instanceof InterfaceTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'interface',
                        static::p($node->name),
                        static::wrap('implements ', static::printList($node->interfaces, ' & ')),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof InterfaceTypeExtensionNode:
                return static::join(
                    [
                        'extend interface',
                        static::p($node->name),
                        static::wrap('implements ', static::printList($node->interfaces, ' & ')),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof ListTypeNode:
                return '[' . static::p($node->type) . ']';

            case $node instanceof ListValueNode:
                return '[' . static::printList($node->values, ', ') . ']';

            case $node instanceof NamedTypeNode:
                return static::p($node->name);

            case $node instanceof NonNullTypeNode:
                return static::p($node->type) . '!';

            case $node instanceof NullValueNode:
                return 'null';

            case $node instanceof ObjectTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'type',
                        static::p($node->name),
                        static::wrap('implements ', static::printList($node->interfaces, ' & ')),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                ));

            case $node instanceof ObjectTypeExtensionNode:
                return static::join(
                    [
                        'extend type',
                        static::p($node->name),
                        static::wrap('implements ', static::printList($node->interfaces, ' & ')),
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof ObjectValueNode:
                return '{ '
                    . static::printList($node->fields, ', ')
                    . ' }';

            case $node instanceof OperationDefinitionNode:
                $op = $node->operation;
                $name = static::p($node->name);
                $varDefs = static::wrap('(', static::printList($node->variableDefinitions, ', '), ')');
                $directives = static::printList($node->directives, ' ');
                $selectionSet = static::p($node->selectionSet);

                // Anonymous queries with no directives or variable definitions can use
                // the query short form.
                return $name === '' && $directives === '' && $varDefs === '' && $op === 'query'
                    ? $selectionSet
                    : static::join([$op, static::join([$name, $varDefs]), $directives, $selectionSet], ' ');

            case $node instanceof OperationTypeDefinitionNode:
                return $node->operation . ': ' . static::p($node->type);

            case $node instanceof ScalarTypeDefinitionNode:
                return static::addDescription($node->description, static::join([
                    'scalar',
                    static::p($node->name),
                    static::printList($node->directives, ' '),
                ], ' '));

            case $node instanceof ScalarTypeExtensionNode:
                return static::join(
                    [
                        'extend scalar',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                    ],
                    ' '
                );

            case $node instanceof SchemaDefinitionNode:
                return static::join(
                    [
                        'schema',
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->operationTypes),
                    ],
                    ' '
                );

            case $node instanceof SchemaExtensionNode:
                return static::join(
                    [
                        'extend schema',
                        static::printList($node->directives, ' '),
                        static::printListBlock($node->operationTypes),
                    ],
                    ' '
                );

            case $node instanceof SelectionSetNode:
                return static::printListBlock($node->selections);

            case $node instanceof StringValueNode:
                if ($node->block) {
                    return BlockString::print($node->value);
                }

                return json_encode($node->value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            case $node instanceof UnionTypeDefinitionNode:
                $typesStr = static::printList($node->types, ' | ');

                return static::addDescription($node->description, static::join(
                    [
                        'union',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                ));

            case $node instanceof UnionTypeExtensionNode:
                $typesStr = static::printList($node->types, ' | ');

                return static::join(
                    [
                        'extend union',
                        static::p($node->name),
                        static::printList($node->directives, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                );

            case $node instanceof VariableDefinitionNode:
                return '$' . static::p($node->variable->name)
                    . ': '
                    . static::p($node->type)
                    . static::wrap(' = ', static::p($node->defaultValue))
                    . static::wrap(' ', static::printList($node->directives, ' '));

            case $node instanceof VariableNode:
                return '$' . static::p($node->name);
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
    protected static function printList(NodeList $list, string $separator = ''): string
    {
        $parts = [];
        foreach ($list as $item) {
            $parts[] = static::p($item);
        }

        return static::join($parts, $separator);
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
    protected static function printListBlock(NodeList $list): string
    {
        if (count($list) === 0) {
            return '';
        }

        $parts = [];
        foreach ($list as $item) {
            $parts[] = static::p($item);
        }

        return "{\n" . static::indent(static::join($parts, "\n")) . "\n}";
    }

    /** @throws \JsonException */
    protected static function addDescription(?StringValueNode $description, string $body): string
    {
        return static::join([static::p($description), $body], "\n");
    }

    /**
     * If maybeString is not null or empty, then wrap with start and end, otherwise
     * print an empty string.
     */
    protected static function wrap(string $start, ?string $maybeString, string $end = ''): string
    {
        if ($maybeString === null || $maybeString === '') {
            return '';
        }

        return $start . $maybeString . $end;
    }

    protected static function indent(string $string): string
    {
        if ($string === '') {
            return '';
        }

        return '  ' . str_replace("\n", "\n  ", $string);
    }

    /** @param array<string|null> $parts */
    protected static function join(array $parts, string $separator = ''): string
    {
        return implode($separator, array_filter($parts, static fn (?string $part) => $part !== '' && $part !== null));
    }
}
