<?php

declare(strict_types=1);

namespace GraphQL\Language;

use _HumbugBox09702017065e\Nette\Neon\Exception;
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
use function count;
use function implode;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_replace;
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

        if(! $ast instanceof Node) {
            throw new Exception('Invalid AST Node: ' . json_encode($ast));
        }

        return $instance->printAST($ast);
    }

    public static function doPrint_bak($ast)
    {
        static $instance;
        $instance = $instance ?? new static();

        if(! $ast instanceof Node) {
            throw new Exception('Invalid AST Node: ' . json_encode($ast));
        }

        return $instance->printAST_bak($ast);
    }

    protected function __construct()
    {
    }

    public function printAST_bak($ast)
    {
        $res = Visitor::visit(
            $ast,
            [
                'leave' => [
                    NodeKind::NAME => static function (NameNode $node) : string {
                        return '' . $node->value;
                    },

                    NodeKind::VARIABLE => static function (VariableNode $node) : string {
                        return '$' . $node->name->value;
                    },

                    NodeKind::DOCUMENT => function (DocumentNode $node) : string {
                        return $this->join($node->definitions, "\n\n") . "\n";
                    },

                    NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) {
                        $op           = $node->operation;
                        $name         = $node->name->value ?? null;
                        $varDefs      = $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')');
                        $directives   = $this->join($node->directives, ' ');
                        $selectionSet = $node->selectionSet;

                        // Anonymous queries with no directives or variable definitions can use
                        // the query short form.
                        return $name === null && ! $directives && ! $varDefs && $op === 'query'
                            ? $selectionSet
                            : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');
                    },

                    NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $node) : string {
                        return '$' . $node->variable->name->value
                            . ': '
                            . $node->type->name->value
                            . $this->wrap(' = ', $node->defaultValue)
                            . $this->wrap(' ', $this->join($node->directives, ' '));
                    },

                    NodeKind::SELECTION_SET => function (SelectionSetNode $node) {
                        return $this->block($node->selections);
                    },

                    NodeKind::FIELD => function (FieldNode $node) {
                        return $this->join(
                            [
                                $this->wrap('', $node->alias->value ?? null, ': ') . $node->name->value . $this->wrap(
                                    '(',
                                    $this->join($node->arguments, ', '),
                                    ')'
                                ),
                                $this->join($node->directives, ' '),
                                $node->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::ARGUMENT => static function (ArgumentNode $node) : string {
                        return $node->name->value . ': ' . $node->value;
                    },

                    NodeKind::FRAGMENT_SPREAD => function (FragmentSpreadNode $node) : string {
                        return '...' . $node->name->value . $this->wrap(' ', $this->join($node->directives, ' '));
                    },

                    NodeKind::INLINE_FRAGMENT => function (InlineFragmentNode $node) {
                        return $this->join(
                            [
                                '...',
                                $this->wrap('on ', $node->typeCondition->name->value ?? null),
                                $this->join($node->directives, ' '),
                                $node->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) : string {
                        // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                        return sprintf('fragment %s', $node->name->value)
                            . $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')')
                            . sprintf(' on %s ', $node->typeCondition->name->value)
                            . $this->wrap('', $this->join($node->directives, ' '), ' ')
                            . $node->selectionSet;
                    },

                    NodeKind::INT => static function (IntValueNode $node) {
                        return $node->value;
                    },

                    NodeKind::FLOAT => static function (FloatValueNode $node) : string {
                        return $node->value;
                    },

                    NodeKind::STRING => function (StringValueNode $node, $key) {
                        if ($node->block) {
                            return $this->printBlockString($node->value, $key === 'description');
                        }

                        return json_encode($node->value);
                    },

                    NodeKind::BOOLEAN => static function (BooleanValueNode $node) {
                        return $node->value ? 'true' : 'false';
                    },

                    NodeKind::NULL => static function (NullValueNode $node) : string {
                        return 'null';
                    },

                    NodeKind::LST => function (ListValueNode $node) : string {
                        return '[' . $this->join($node->values, ', ') . ']';
                    },

                    NodeKind::OBJECT => function (ObjectValueNode $node) : string {
                        return '{' . $this->join($node->fields, ', ') . '}';
                    },

                    NodeKind::OBJECT_FIELD => static function (ObjectFieldNode $node) : string {
                        return $node->name->value . ': ' . $node->value->value;
                    },

                    NodeKind::DIRECTIVE => function (DirectiveNode $node) : string {
                        return '@' . $node->name->value . $this->wrap('(', $this->join($node->arguments, ', '), ')');
                    },

                    NodeKind::NAMED_TYPE => static function (NamedTypeNode $node) : string {
                        return $node->name->value;
                    },

                    NodeKind::LIST_TYPE => static function (ListTypeNode $node) : string {
                        return '[' . $node->type . ']';
                    },

                    NodeKind::NON_NULL_TYPE => static function (NonNullTypeNode $node) : string {
                        return $node->type . '!';
                    },

                    NodeKind::SCHEMA_DEFINITION => function (SchemaDefinitionNode $def) {
                        return $this->join(
                            [
                                'schema',
                                $this->join($def->directives, ' '),
                                $this->block($def->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OPERATION_TYPE_DEFINITION => static function (OperationTypeDefinitionNode $def) : string {
                        return $def->operation . ': ' . $def->type->name->value;
                    },

                    NodeKind::SCALAR_TYPE_DEFINITION => $this->addDescription(function (ScalarTypeDefinitionNode $def) {
                        return $this->join(['scalar', $def->name->value, $this->join($def->directives, ' ')], ' ');
                    }),

                    NodeKind::OBJECT_TYPE_DEFINITION => $this->addDescription(function (ObjectTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'type',
                                $def->name->value,
                                $this->wrap('implements ', $this->join($def->interfaces, ' & ')),
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::FIELD_DEFINITION => $this->addDescription(function (FieldDefinitionNode $def) {
                        $noIndent = Utils::every($def->arguments, static function (string $arg) : bool {
                            return strpos($arg, "\n") === false;
                        });

                        return ($def->name->value ?? null)
                            . ($noIndent
                                ? $this->wrap('(', $this->join($def->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($def->arguments, "\n")), "\n)"))
                            . ': ' . $def->type
                            . $this->wrap(' ', $this->join($def->directives, ' '));
                    }),

                    NodeKind::INPUT_VALUE_DEFINITION => $this->addDescription(function (InputValueDefinitionNode $def) {
                        return $this->join(
                            [
                                $def->name->value . ': ' . $def->type,
                                $this->wrap('= ', $def->defaultValue),
                                $this->join($def->directives, ' '),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::INTERFACE_TYPE_DEFINITION => $this->addDescription(
                        function (InterfaceTypeDefinitionNode $def) {
                            return $this->join(
                                [
                                    'interface',
                                    $def->name->value,
                                    $this->join($def->directives, ' '),
                                    $this->block($def->fields),
                                ],
                                ' '
                            );
                        }
                    ),

                    NodeKind::UNION_TYPE_DEFINITION => $this->addDescription(function (UnionTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'union',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $def->types
                                    ? '= ' . $this->join($def->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_TYPE_DEFINITION => $this->addDescription(function (EnumTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'enum',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $this->block($def->values),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_VALUE_DEFINITION => $this->addDescription(function (EnumValueDefinitionNode $def) {
                        return $this->join([$def->name->value, $this->join($def->directives, ' ')], ' ');
                    }),

                    NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $this->addDescription(function (
                        InputObjectTypeDefinitionNode $def
                    ) {
                        return $this->join(
                            [
                                'input',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::SCHEMA_EXTENSION => function (SchemaTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend schema',
                                $this->join($def->directives, ' '),
                                $this->block($def->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::SCALAR_TYPE_EXTENSION => function (ScalarTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend scalar',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OBJECT_TYPE_EXTENSION => function (ObjectTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend type',
                                $def->name->value,
                                $this->wrap('implements ', $this->join($def->interfaces, ' & ')),
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INTERFACE_TYPE_EXTENSION => function (InterfaceTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend interface',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::UNION_TYPE_EXTENSION => function (UnionTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend union',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $def->types
                                    ? '= ' . $this->join($def->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    },

                    NodeKind::ENUM_TYPE_EXTENSION => function (EnumTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend enum',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $this->block($def->values),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INPUT_OBJECT_TYPE_EXTENSION => function (InputObjectTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend input',
                                $def->name->value,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::DIRECTIVE_DEFINITION => $this->addDescription(function (DirectiveDefinitionNode $def) {
                        $noIndent = Utils::every($def->arguments, static function (string $arg) : bool {
                            return strpos($arg, "\n") === false;
                        });

                        return 'directive @'
                            . $def->name->value
                            . ($noIndent
                                ? $this->wrap('(', $this->join($def->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($def->arguments, "\n")), "\n"))
                            . ($def->repeatable ? ' repeatable' : '')
                            . ' on ' . $this->join($def->locations, ' | ');
                    }),
                ],
            ]
        );

        return $res;
    }

    public function printAST(Node $node) {
        return $this->p($node);
    }

    protected function listToArray(?NodeList $list) : array
    {
        return isset($list) ? iterator_to_array($list) : [];
    }

    protected function printList(?NodeList $list, $separator = '') : string {
        return $this->printArray($this->listToArray($list), $separator);
    }

    protected function printListBlock(?NodeList $list) : string {
        return $this->block( array_map(fn ($item) => $this->p($item), $this->listToArray($list)));
    }

    protected function printArray(array $list, $separator = '') : string {
        return $this->join(array_map(fn ($item) => $this->p($item), $list), $separator);
    }

    public function p(?Node $node, bool $isDescription = false) : string
    {
        $res = '';
        if(is_null($node)) {
            return '';
        }
        switch(true) {
            case $node instanceof ArgumentNode:
                $res = $node->name->value . ': ' . $this->p($node->value);
                break;

            case $node instanceof BooleanValueNode:
                return $node->value ? 'true' : 'false';

            case $node instanceof DirectiveDefinitionNode:
                $argStrings = array_map(fn ($item) => $this->p($item), $this->listToArray($node->arguments));
                $noIndent = Utils::every($argStrings, static function (string $arg) : bool {
                    return strpos($arg, "\n") === false;
                });

                $res = $this->printDescription($node->description, 'directive @'
                    . $node->name->value
                    . ($noIndent
                        ? $this->wrap('(', $this->join($argStrings, ', '), ')')
                        : $this->wrap("(\n", $this->indent($this->join($argStrings, "\n")), "\n"))
                    . ($node->repeatable ? ' repeatable' : '')
                    . ' on ' . $this->printArray($node->locations, ' | '));
                break;

            case $node instanceof DirectiveNode:
                $res = '@' . $node->name->value . $this->wrap('(', $this->printList( $node->arguments, ', '), ')');
                break;

            case $node instanceof DocumentNode:
                $res = $this->printList( $node->definitions, "\n\n") . "\n";
                break;

            case $node instanceof EnumTypeDefinitionNode:

                $res = $this->printDescription($node->description, $this->join(
                    [
                        'enum',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->values),
                    ],
                    ' '
                ));
                break;

            case $node instanceof EnumTypeExtensionNode:
                return $this->join(
                    [
                        'extend enum',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->values), // TODO: add tests and confirm if values is NodeList or array
                    ],
                    ' '
                );

            case $node instanceof EnumValueDefinitionNode:
                $res = $this->join([$node->name->value, $this->printList($node->directives, ' ')], ' ');
                break;

            case $node instanceof EnumValueNode:
                return $node->value;

            case $node instanceof FieldDefinitionNode:
                $argStrings = array_map(fn ($item) => $this->p($item), $this->listToArray($node->arguments));
                $noIndent = Utils::every($argStrings, static function (string $arg) : bool {
                    return strpos($arg, "\n") === false;
                });

                $res = ($node->name->value ?? null)
                    . ($noIndent
                        ? $this->wrap('(', $this->join($argStrings, ', '), ')')
                        : $this->wrap("(\n", $this->indent($this->join($argStrings, "\n")), "\n)"))
                    . ': ' . $this->p($node->type)
                    . $this->wrap(' ', $this->printList($node->directives, ' '));
                break;

            case $node instanceof FieldNode:
                $res = $this->join(
                    [
                        $this->wrap('', $node->alias->value ?? null, ': ') . $node->name->value . $this->wrap(
                            '(',
                            $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->arguments)), ', '),
                            ')'
                        ),
                        $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '),
                        $this->p($node->selectionSet),
                    ],
                    ' '
                );
                break;

            case $node instanceof FloatValueNode:
                return $node->value;

            case $node instanceof FragmentDefinitionNode:
                // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                $res = sprintf('fragment %s', $node->name->value)
                    . $this->wrap('(', $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->variableDefinitions)), ', '), ')')
                    . sprintf(' on %s ', $node->typeCondition->name->value)
                    . $this->wrap('', $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '), ' ')
                    . $this->p($node->selectionSet);
                break;

            case $node instanceof FragmentSpreadNode:
                $res = '...' . $node->name->value . $this->wrap(' ', $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '));
                break;

            case $node instanceof InlineFragmentNode:
                $res = $this->join(
                    [
                        '...',
                        $this->wrap('on ', $node->typeCondition->name->value ?? null),
                        $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '),
                        $this->p($node->selectionSet),
                    ],
                    ' '
                );
            break;

            case $node instanceof InputObjectTypeDefinitionNode:
                $res = $this->printDescription($node->description, $this->join(
                    [
                        'input',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                ));
                break;

            case $node instanceof InputObjectTypeExtensionNode:
                return $this->join(
                    [
                        'extend input',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields),
                    ],
                    ' '
                );

            case $node instanceof InputValueDefinitionNode:
                $res = $this->join(
                    [
                        $node->name->value . ': ' . $this->p($node->type),
                        $this->wrap('= ', $this->p($node->defaultValue)),
                        $this->printList($node->directives, ' '),
                    ],
                    ' '
                );
                break;

            case $node instanceof InterfaceTypeDefinitionNode:
                $res = $this->printDescription($node->description, $this->join(
                        [
                            'interface',
                            $node->name->value,
                            $this->printList($node->directives, ' '),
                            $this->printListBlock($node->fields),
                        ],
                        ' '
                    ));
                break;

            case $node instanceof InterfaceTypeExtensionNode:
                return $this->join(
                    [
                        'extend interface',
                        $node->name->value,
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
                return '[' . $this->join(array_map(fn ($item) => $this->p($item), $this->listToArray($node->values)), ', ') . ']';

            case $node instanceof NameNode:
                return $node->value;

            case $node instanceof NamedTypeNode:
                return $node->name->value;

            case $node instanceof NonNullTypeNode:
                $res = $this->p($node->type) . '!';
                break;

            case $node instanceof NullValueNode:
                return 'null';

            case $node instanceof ObjectFieldNode:
                $res = $node->name->value . ': ' . $this->p($node->value);
                break;

            case $node instanceof ObjectTypeDefinitionNode:
                $res = $this->join(
                    [
                        'type',
                        $node->name->value,
                        $this->wrap('implements ', $this->printArray($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->block(array_map(fn ($item) => $this->p($item), $this->listToArray($node->fields))),
                    ],
                    ' '
                );
                break;

            case $node instanceof ObjectTypeExtensionNode:
                return $this->join(
                    [
                        'extend type',
                        $node->name->value,
                        $this->wrap('implements ', $this->printList($node->interfaces, ' & ')),
                        $this->printList($node->directives, ' '),
                        $this->printListBlock($node->fields), // TODO: add tests. Not sure if fields is an array or a NodeList
                    ],
                    ' '
                );

            case $node instanceof ObjectValueNode:
                return '{' . $this->printList($node->fields, ', ') . '}';

            case $node instanceof OperationDefinitionNode:
                $op           = $node->operation;
                $name         = $node->name->value ?? null;
                $varDefs      = $this->wrap('(', $this->join( array_map(fn ($item) => $this->p($item), $this->listToArray($node->variableDefinitions)), ', '), ')');
                $directives   = $this->join( array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' ');
                $selectionSet = $this->p($node->selectionSet);

                // Anonymous queries with no directives or variable definitions can use
                // the query short form.
                $res = $name === null && ! $directives && ! $varDefs && $op === 'query'
                    ? $selectionSet
                    : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');
                break;

            case $node instanceof OperationTypeDefinitionNode:
                $res = $node->operation . ': ' . $this->p($node->type);
                break;

            case $node instanceof ScalarTypeDefinitionNode:
                $res = $this->join(['scalar', $node->name->value, $this->join(
                    array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '
                )], ' ');
                break;

            case $node instanceof ScalarTypeExtensionNode:
                return $this->join(
                    [
                        'extend scalar',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                    ],
                    ' '
                );

            case $node instanceof SchemaDefinitionNode:
                $res = $this->join(
                    [
                        'schema',
                        $this->printList( $node->directives, ' '),
                        $this->block(array_map(fn ($item) => $this->p($item), $this->listToArray($node->operationTypes))),
                    ],
                    ' '
                );
                break;

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
                $res = $this->block(array_map(fn ($item) => $this->p($item), $this->listToArray($node->selections)));
                break;

            case $node instanceof StringValueNode:
                if ($node->block) {
                    return $this->printBlockString($node->value, $isDescription);
                }

                return json_encode($node->value);

            case $node instanceof UnionTypeDefinitionNode:
                $res = $this->printDescription($node->description, $this->join(
                    [
                        'union',
                        $node->name->value,
                        $this->printList($node->directives, ' '),
                        isset($node->types)
                            ? '= ' . $this->printArray($node->types, ' | ')
                            : '',
                    ],
                    ' '
                ));
                break;

            case $node instanceof UnionTypeExtensionNode:
                return $this->join(
                    [
                        'extend union',
                        $node->name->value,
                        $this->printList($node->directives, ' '), // TODO: add tests. Not sure if directives is NodeList or array
                        isset($node->types)
                            ? '= ' . $this->printList($node->types, ' | ') // TODO: add tests. Not sure if types is NodeList or array
                            : '',
                    ],
                    ' '
                );

            case $node instanceof VariableDefinitionNode:
                $res = '$' . $node->variable->name->value
                    . ': '
                    . $node->type->name->value
                    . $this->wrap(' = ', $this->p($node->defaultValue))
                    . $this->wrap(' ', $this->join( array_map(fn ($item) => $this->p($item), $this->listToArray($node->directives)), ' '));
                break;

            case $node instanceof VariableNode:
                return '$' . $node->name->value;

            default:
                throw new Exception("Here there be dragons");
        };

        return $res;
    }

    public function addDescription(callable $cb)
    {
        return function ($node) use ($cb) {
            return $this->join([$node->description, $cb($node)], "\n");
        };
    }

    public function printDescription(?StringValueNode $description, string $body) : string {
        return $this->join([$description, $body], "\n");
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
