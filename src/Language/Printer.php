<?php

declare(strict_types=1);

namespace GraphQL\Language;

use Exception;
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
use GraphQL\Language\AST\NodeKind;
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
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Utils\Utils;
use function array_map;
use function count;
use function implode;
use function is_null;
use function iterator_to_array;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;

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
 */
class Printer
{
    /**
     * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
     *
     * @param Node $ast
     *
     * @return string
     *
     * @api
     */
    public static function doPrint($ast)
    {
        static $instance;
        $instance = $instance ?? new static();

        if (! $ast instanceof Node) {
            throw new Exception('Invalid AST Node: ' . json_encode($ast));
        }

        return $instance->printAST($ast);
    }

    protected function __construct()
    {
    }

    public function printAST(Node $node)
    {
        return $this->p($node);
    }

    /**
     * @return array<Node>
     */
    protected function listToArray(?NodeList $list) : array
    {
        return isset($list) ? iterator_to_array($list) : [];
    }

    protected function printList(?NodeList $list, $separator = '') : string
    {
        return $this->printArray($this->listToArray($list), $separator);
    }

    protected function printListBlock(?NodeList $list) : string
    {
        return $this->block(array_map(function ($item) : string {
            return $this->p($item);
        }, $this->listToArray($list)));
    }

    /**
     * @param array<Node> $list
     */
    protected function printArray(array $list, string $separator = '') : string
    {
        return $this->join(array_map(function ($item) : string {
            return $this->p($item);
        }, $list), $separator);
    }

    public function p(?Node $node, bool $isDescription = false) : string
    {
        $res = '';
        if ($node === null) {
            return '';
        }
        switch (true) {
            case $node instanceof ArgumentNode:
                return $this->p($node->name) . ': ' . $this->p($node->value);
            case $node instanceof BooleanValueNode:
                return $node->value ? 'true' : 'false';
            case $node instanceof DirectiveDefinitionNode:
                $argStrings = array_map(function ($item) : string {
                    return $this->p($item);
                }, $this->listToArray($node->arguments));
                $noIndent   = Utils::every($argStrings, static function (string $arg) : bool {
                    return strpos($arg, "\n") === false;
                });

                return $this->addDescription($node->description, 'directive @'
                    . $this->p($node->name)
                    . ($noIndent
                        ? $this->wrap('(', $this->join($argStrings, ', '), ')')
                        : $this->wrap("(\n", $this->indent($this->join($argStrings, "\n")), "\n"))
                    . ($node->repeatable ? ' repeatable' : '')
                    . ' on ' . $this->printArray($node->locations, ' | '));
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
                return $node->value;
            case $node instanceof FieldDefinitionNode:
                $argStrings = array_map(function ($item) : string {
                    return $this->p($item);
                }, $this->listToArray($node->arguments));
                $noIndent   = Utils::every($argStrings, static function (string $arg) : bool {
                    return strpos($arg, "\n") === false;
                });

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
                return $this->join(
                    [
                        $this->wrap('', $node->alias->value ?? null, ': ') . $this->p($node->name) . $this->wrap(
                            '(',
                            $this->printList($node->arguments, ', '),
                            ')'
                        ),
                        $this->printList($node->directives, ' '),
                        $this->p($node->selectionSet),
                    ],
                    ' '
                );
            case $node instanceof FloatValueNode:
                return $node->value;
            case $node instanceof FragmentDefinitionNode:
                // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                return sprintf('fragment %s', $this->p($node->name))
                    . $this->wrap(
                        '(',
                        $this->printList($node->variableDefinitions, ', '),
                        ')'
                    )
                    . sprintf(' on %s ', $this->p($node->typeCondition->name))
                    . $this->wrap(
                        '',
                        $this->printList($node->directives, ' '),
                        ' '
                    )
                    . $this->p($node->selectionSet);
            case $node instanceof FragmentSpreadNode:
                return '...' . $this->p($node->name) . $this->wrap(' ', $this->printList($node->directives, ' '));
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
                        $this->printList($node->directives, ' '), // TODO: add tests. Assuming directives is NodeList...
                        $this->printListBlock($node->fields), // TODO: add tests. Not sure if fields is array or NodeList
                    ],
                    ' '
                );
            case $node instanceof IntValueNode:
                return $node->value;
            case $node instanceof ListTypeNode:
                return '[' . $this->p($node->type) . ']';
            case $node instanceof ListValueNode:
                return '[' . $this->printList($node->values, ', ') . ']';
            case $node instanceof NameNode:
                return $node->value;
            case $node instanceof NamedTypeNode:
                return $this->p($node->name);
            case $node instanceof NonNullTypeNode:
                return $this->p($node->type) . '!';
            case $node instanceof NullValueNode:
                return 'null';
            case $node instanceof ObjectFieldNode:
                return $this->p($node->name) . ': ' . $this->p($node->value);
            case $node instanceof ObjectTypeDefinitionNode:
                return $this->addDescription($node->description, $this->join(
                    [
                        'type',
                        $this->p($node->name),
                        $this->wrap('implements ', $this->printArray($node->interfaces, ' & ')),
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
                        $this->wrap('implements ', $this->printArray($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                );
            case $node instanceof ObjectValueNode:
                return '{' . $this->printList($node->fields, ', ') . '}';
            case $node instanceof OperationDefinitionNode:
                $op           = $node->operation;
                $name         = $this->p($node->name);
                $varDefs      = $this->wrap('(', $this->printList($node->variableDefinitions, ', '), ')');
                $directives   = $this->printList($node->directives, ' ');
                $selectionSet = $this->p($node->selectionSet);

                // Anonymous queries with no directives or variable definitions can use
                // the query short form.
                return (strlen($name) === 0) && (strlen($directives) === 0) && ! $varDefs && $op === 'query'
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
            case $node instanceof SchemaTypeExtensionNode:
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
                    return $this->printBlockString($node->value, $isDescription);
                }

                return json_encode($node->value);
            case $node instanceof UnionTypeDefinitionNode:
                $typesStr = $this->printArray($node->types, ' | ');

                return $this->addDescription($node->description, $this->join(
                    [
                        'union',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        strlen($typesStr) > 0
                            ? '= ' . $typesStr
                            : '',
                    ],
                    ' '
                ));
            case $node instanceof UnionTypeExtensionNode:
                $typesStr = $this->printArray($node->types, ' | ');

                return $this->join(
                    [
                        'extend union',
                        $this->p($node->name),
                        $this->printList($node->directives, ' '),
                        strlen($typesStr) > 0
                            ? '= ' . $typesStr
                            : '',
                    ],
                    ' '
                );
            case $node instanceof VariableDefinitionNode:
                return '$' . $this->p($node->variable->name)
                    . ': '
                    . $this->p($node->type->name)
                    . $this->wrap(' = ', $this->p($node->defaultValue))
                    . $this->wrap(' ', $this->printList($node->directives, ' '));
            case $node instanceof VariableNode:
                return '$' . $this->p($node->name);
            default:
                throw new Exception('Here there be dragons');
        }

        return $res;
    }

    public function addDescription(?StringValueNode $description, string $body) : string
    {
        return $this->join([$this->p($description, true), $body], "\n");
    }

    /**
     * If maybeString is not null or empty, then wrap with start and end, otherwise
     * print an empty string.
     */
    public function wrap($start, $maybeString, $end = '')
    {
        return $maybeString ? ($start . $maybeString . $end) : '';
    }

    /**
     * Given array, print each item on its own line, wrapped in an
     * indented "{ }" block.
     */
    public function block($array)
    {
        return $array && $this->length($array)
            ? "{\n" . $this->indent($this->join($array, "\n")) . "\n}"
            : '';
    }

    public function indent($maybeString)
    {
        return $maybeString ? '  ' . str_replace("\n", "\n  ", $maybeString) : '';
    }

    public function manyList($start, $list, $separator, $end)
    {
        return $this->length($list) === 0 ? null : ($start . $this->join($list, $separator) . $end);
    }

    public function length($maybeArray)
    {
        return $maybeArray ? count($maybeArray) : 0;
    }

    public function join($maybeArray, $separator = '')
    {
        return $maybeArray
            ? implode(
                $separator,
                Utils::filter(
                    $maybeArray,
                    static function ($x) : bool {
                        return (bool) $x;
                    }
                )
            )
            : '';
    }

    /**
     * Print a block string in the indented block form by adding a leading and
     * trailing blank line. However, if a block string starts with whitespace and is
     * a single-line, adding a leading blank line would strip that whitespace.
     */
    private function printBlockString($value, $isDescription)
    {
        $escaped = str_replace('"""', '\\"""', $value);

        return ($value[0] === ' ' || $value[0] === "\t") && strpos($value, "\n") === false
            ? ('"""' . preg_replace('/"$/', "\"\n", $escaped) . '"""')
            : ('"""' . "\n" . ($isDescription ? $escaped : $this->indent($escaped)) . "\n" . '"""');
    }
}
