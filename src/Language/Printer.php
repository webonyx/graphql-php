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
 * @phpstan-type Options array{
 *    queryShortForm?: bool,
 *  }
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
     * @param array<string, bool> $options
     *
     * @phpstan-param Options $options
     *
     * @throws \JsonException
     *
     * @api
     */
    public static function doPrint(Node $ast, array $options = []): string
    {
        return static::p($ast, $options);
    }

    /**
     * @param array<string, bool> $options
     *
     * @phpstan-param Options $options
     *
     * @throws \JsonException
     */
    protected static function p(?Node $node, array $options): string
    {
        if ($node === null) {
            return '';
        }

        switch (true) {
            case $node instanceof ArgumentNode:
            case $node instanceof ObjectFieldNode:
                return static::p($node->name, $options) . ': ' . static::p($node->value, $options);

            case $node instanceof BooleanValueNode:
                return $node->value
                    ? 'true'
                    : 'false';

            case $node instanceof DirectiveDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $arg) {
                    $argStrings[] = static::p($arg, $options);
                }

                $noIndent = true;
                foreach ($argStrings as $argString) {
                    if (strpos($argString, "\n") !== false) {
                        $noIndent = false;
                        break;
                    }
                }

                return static::addDescription($node->description, 'directive @'
                    . static::p($node->name, $options)
                    . ($noIndent
                        ? static::wrap('(', static::join($argStrings, ', '), ')')
                        : static::wrap("(\n", static::indent(static::join($argStrings, "\n")), "\n"))
                    . ($node->repeatable
                        ? ' repeatable'
                        : '')
                    . ' on ' . static::printList($node->locations, $options, ' | '), $options);

            case $node instanceof DirectiveNode:
                return '@' . static::p($node->name, $options) . static::wrap('(', static::printList($node->arguments, $options, ', '), ')');

            case $node instanceof DocumentNode:
                return static::printList($node->definitions, $options, "\n\n") . "\n";

            case $node instanceof EnumTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'enum',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->values, $options),
                    ],
                    ' '
                ), $options);

            case $node instanceof EnumTypeExtensionNode:
                return static::join(
                    [
                        'extend enum',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->values, $options),
                    ],
                    ' '
                );

            case $node instanceof EnumValueDefinitionNode:
                return static::addDescription(
                    $node->description,
                    static::join([static::p($node->name, $options), static::printList($node->directives, $options, ' ')], ' '),
                    $options
                );

            case $node instanceof EnumValueNode:
            case $node instanceof FloatValueNode:
            case $node instanceof IntValueNode:
            case $node instanceof NameNode:
                return $node->value;

            case $node instanceof FieldDefinitionNode:
                $argStrings = [];
                foreach ($node->arguments as $item) {
                    $argStrings[] = static::p($item, $options);
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
                    static::p($node->name, $options)
                    . ($noIndent
                        ? static::wrap('(', static::join($argStrings, ', '), ')')
                        : static::wrap("(\n", static::indent(static::join($argStrings, "\n")), "\n)"))
                    . ': ' . static::p($node->type, $options)
                    . static::wrap(' ', static::printList($node->directives, $options, ' ')),
                    $options
                );

            case $node instanceof FieldNode:
                $prefix = static::wrap('', $node->alias->value ?? null, ': ') . static::p($node->name, $options);

                $argsLine = $prefix . static::wrap(
                    '(',
                    static::printList($node->arguments, $options, ', '),
                    ')'
                );
                if (strlen($argsLine) > 80) {
                    $argsLine = $prefix . static::wrap(
                        "(\n",
                        static::indent(
                            static::printList($node->arguments, $options, "\n")
                        ),
                        "\n)"
                    );
                }

                return static::join(
                    [
                        $argsLine,
                        static::printList($node->directives, $options, ' '),
                        static::p($node->selectionSet, $options),
                    ],
                    ' '
                );

            case $node instanceof FragmentDefinitionNode:
                // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                return 'fragment ' . static::p($node->name, $options)
                    . static::wrap(
                        '(',
                        static::printList($node->variableDefinitions ?? new NodeList([]), $options, ', '),
                        ')'
                    )
                    . ' on ' . static::p($node->typeCondition->name, $options) . ' '
                    . static::wrap(
                        '',
                        static::printList($node->directives, $options, ' '),
                        ' '
                    )
                    . static::p($node->selectionSet, $options);

            case $node instanceof FragmentSpreadNode:
                return '...'
                    . static::p($node->name, $options)
                    . static::wrap(' ', static::printList($node->directives, $options, ' '));

            case $node instanceof InlineFragmentNode:
                return static::join(
                    [
                        '...',
                        static::wrap('on ', static::p($node->typeCondition->name ?? null, $options)),
                        static::printList($node->directives, $options, ' '),
                        static::p($node->selectionSet, $options),
                    ],
                    ' '
                );

            case $node instanceof InputObjectTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'input',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                ), $options);

            case $node instanceof InputObjectTypeExtensionNode:
                return static::join(
                    [
                        'extend input',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                );

            case $node instanceof InputValueDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        static::p($node->name, $options) . ': ' . static::p($node->type, $options),
                        static::wrap('= ', static::p($node->defaultValue, $options)),
                        static::printList($node->directives, $options, ' '),
                    ],
                    ' '
                ), $options);

            case $node instanceof InterfaceTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'interface',
                        static::p($node->name, $options),
                        static::wrap('implements ', static::printList($node->interfaces, $options, ' & ')),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                ), $options);

            case $node instanceof InterfaceTypeExtensionNode:
                return static::join(
                    [
                        'extend interface',
                        static::p($node->name, $options),
                        static::wrap('implements ', static::printList($node->interfaces, $options, ' & ')),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                );

            case $node instanceof ListTypeNode:
                return '[' . static::p($node->type, $options) . ']';

            case $node instanceof ListValueNode:
                return '[' . static::printList($node->values, $options, ', ') . ']';

            case $node instanceof NamedTypeNode:
                return static::p($node->name, $options);

            case $node instanceof NonNullTypeNode:
                return static::p($node->type, $options) . '!';

            case $node instanceof NullValueNode:
                return 'null';

            case $node instanceof ObjectTypeDefinitionNode:
                return static::addDescription($node->description, static::join(
                    [
                        'type',
                        static::p($node->name, $options),
                        static::wrap('implements ', static::printList($node->interfaces, $options, ' & ')),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                ), $options);

            case $node instanceof ObjectTypeExtensionNode:
                return static::join(
                    [
                        'extend type',
                        static::p($node->name, $options),
                        static::wrap('implements ', static::printList($node->interfaces, $options, ' & ')),
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->fields, $options),
                    ],
                    ' '
                );

            case $node instanceof ObjectValueNode:
                return '{ '
                    . static::printList($node->fields, $options, ', ')
                    . ' }';

            case $node instanceof OperationDefinitionNode:
                $op = $node->operation;
                $name = static::p($node->name, $options);
                $varDefs = static::wrap('(', static::printList($node->variableDefinitions, $options, ', '), ')');
                $directives = static::printList($node->directives, $options, ' ');
                $selectionSet = static::p($node->selectionSet, $options);

                // Anonymous queries with no directives or variable definitions can use
                // the query short form.
                return $name === '' && $directives === '' && $varDefs === '' && $op === 'query' && (! isset($options['queryShortForm']) || $options['queryShortForm'] === true)
                    ? $selectionSet
                    : static::join([$op, static::join([$name, $varDefs]), $directives, $selectionSet], ' ');

            case $node instanceof OperationTypeDefinitionNode:
                return $node->operation . ': ' . static::p($node->type, $options);

            case $node instanceof ScalarTypeDefinitionNode:
                return static::addDescription($node->description, static::join([
                    'scalar',
                    static::p($node->name, $options),
                    static::printList($node->directives, $options, ' '),
                ], ' '), $options);

            case $node instanceof ScalarTypeExtensionNode:
                return static::join(
                    [
                        'extend scalar',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                    ],
                    ' '
                );

            case $node instanceof SchemaDefinitionNode:
                return static::join(
                    [
                        'schema',
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->operationTypes, $options),
                    ],
                    ' '
                );

            case $node instanceof SchemaExtensionNode:
                return static::join(
                    [
                        'extend schema',
                        static::printList($node->directives, $options, ' '),
                        static::printListBlock($node->operationTypes, $options),
                    ],
                    ' '
                );

            case $node instanceof SelectionSetNode:
                return static::printListBlock($node->selections, $options);

            case $node instanceof StringValueNode:
                if ($node->block) {
                    return BlockString::print($node->value);
                }

                return json_encode($node->value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            case $node instanceof UnionTypeDefinitionNode:
                $typesStr = static::printList($node->types, $options, ' | ');

                return static::addDescription($node->description, static::join(
                    [
                        'union',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                ), $options);

            case $node instanceof UnionTypeExtensionNode:
                $typesStr = static::printList($node->types, $options, ' | ');

                return static::join(
                    [
                        'extend union',
                        static::p($node->name, $options),
                        static::printList($node->directives, $options, ' '),
                        $typesStr !== ''
                            ? "= {$typesStr}"
                            : '',
                    ],
                    ' '
                );

            case $node instanceof VariableDefinitionNode:
                return '$' . static::p($node->variable->name, $options)
                    . ': '
                    . static::p($node->type, $options)
                    . static::wrap(' = ', static::p($node->defaultValue, $options))
                    . static::wrap(' ', static::printList($node->directives, $options, ' '));

            case $node instanceof VariableNode:
                return '$' . static::p($node->name, $options);
        }

        return '';
    }

    /**
     * @template TNode of Node
     *
     * @param NodeList<TNode> $list
     * @param array<string, bool> $options
     *
     * @phpstan-param Options $options
     *
     * @throws \JsonException
     */
    protected static function printList(NodeList $list, array $options, string $separator = ''): string
    {
        $parts = [];
        foreach ($list as $item) {
            $parts[] = static::p($item, $options);
        }

        return static::join($parts, $separator);
    }

    /**
     * Print each item on its own line, wrapped in an indented "{ }" block.
     *
     * @template TNode of Node
     *
     * @param NodeList<TNode> $list
     * @param array<string, bool> $options
     *
     * @phpstan-param Options $options
     *
     * @throws \JsonException
     */
    protected static function printListBlock(NodeList $list, array $options): string
    {
        if (count($list) === 0) {
            return '';
        }

        $parts = [];
        foreach ($list as $item) {
            $parts[] = static::p($item, $options);
        }

        return "{\n" . static::indent(static::join($parts, "\n")) . "\n}";
    }

    /**
     * @param array<string, bool> $options
     *
     * @phpstan-param Options $options
     *
     * @throws \JsonException
     */
    protected static function addDescription(?StringValueNode $description, string $body, $options): string
    {
        return static::join([static::p($description, $options), $body], "\n");
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
