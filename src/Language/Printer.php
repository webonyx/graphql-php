<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Utils\Utils;

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
     * @api
     * @param Node $ast
     * @return string
     */
    public static function doPrint($ast)
    {
        static $instance;
        $instance = $instance ?: new static();
        return $instance->printAST($ast);
    }

    protected function __construct()
    {}

    public function printAST($ast)
    {
        return Visitor::visit($ast, [
            'leave' => [
                NodeKind::NAME => function(Node $node) {
                    return '' . $node->value;
                },
                NodeKind::VARIABLE => function($node) {
                    return '$' . $node->name;
                },
                NodeKind::DOCUMENT => function(DocumentNode $node) {
                    return $this->join($node->definitions, "\n\n") . "\n";
                },
                NodeKind::OPERATION_DEFINITION => function(OperationDefinitionNode $node) {
                    $op = $node->operation;
                    $name = $node->name;
                    $varDefs = $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')');
                    $directives = $this->join($node->directives, ' ');
                    $selectionSet = $node->selectionSet;
                    // Anonymous queries with no directives or variable definitions can use
                    // the query short form.
                    return !$name && !$directives && !$varDefs && $op === 'query'
                        ? $selectionSet
                        : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');
                },
                NodeKind::VARIABLE_DEFINITION => function(VariableDefinitionNode $node) {
                    return $node->variable . ': ' . $node->type . $this->wrap(' = ', $node->defaultValue);
                },
                NodeKind::SELECTION_SET => function(SelectionSetNode $node) {
                    return $this->block($node->selections);
                },
                NodeKind::FIELD => function(FieldNode $node) {
                    return $this->join([
                        $this->wrap('', $node->alias, ': ') . $node->name . $this->wrap('(', $this->join($node->arguments, ', '), ')'),
                        $this->join($node->directives, ' '),
                        $node->selectionSet
                    ], ' ');
                },
                NodeKind::ARGUMENT => function(ArgumentNode $node) {
                    return $node->name . ': ' . $node->value;
                },

                // Fragments
                NodeKind::FRAGMENT_SPREAD => function(FragmentSpreadNode $node) {
                    return '...' . $node->name . $this->wrap(' ', $this->join($node->directives, ' '));
                },
                NodeKind::INLINE_FRAGMENT => function(InlineFragmentNode $node) {
                    return $this->join([
                        "...",
                        $this->wrap('on ', $node->typeCondition),
                        $this->join($node->directives, ' '),
                        $node->selectionSet
                    ], ' ');
                },
                NodeKind::FRAGMENT_DEFINITION => function(FragmentDefinitionNode $node) {
                    // Note: fragment variable definitions are experimental and may be changed
                    // or removed in the future.
                    return "fragment {$node->name}"
                        . $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')')
                        . " on {$node->typeCondition} "
                        . $this->wrap('', $this->join($node->directives, ' '), ' ')
                        . $node->selectionSet;
                },

                // Value
                NodeKind::INT => function(IntValueNode  $node) {
                    return $node->value;
                },
                NodeKind::FLOAT => function(FloatValueNode $node) {
                    return $node->value;
                },
                NodeKind::STRING => function(StringValueNode $node, $key) {
                    if ($node->block) {
                       return $this->printBlockString($node->value, $key === 'description');
                    }
                    return json_encode($node->value);
                },
                NodeKind::BOOLEAN => function(BooleanValueNode $node) {
                    return $node->value ? 'true' : 'false';
                },
                NodeKind::NULL => function(NullValueNode $node) {
                    return 'null';
                },
                NodeKind::ENUM => function(EnumValueNode $node) {
                    return $node->value;
                },
                NodeKind::LST => function(ListValueNode $node) {
                    return '[' . $this->join($node->values, ', ') . ']';
                },
                NodeKind::OBJECT => function(ObjectValueNode $node) {
                    return '{' . $this->join($node->fields, ', ') . '}';
                },
                NodeKind::OBJECT_FIELD => function(ObjectFieldNode $node) {
                    return $node->name . ': ' . $node->value;
                },

                // DirectiveNode
                NodeKind::DIRECTIVE => function(DirectiveNode $node) {
                    return '@' . $node->name . $this->wrap('(', $this->join($node->arguments, ', '), ')');
                },

                // Type
                NodeKind::NAMED_TYPE => function(NamedTypeNode $node) {
                    return $node->name;
                },
                NodeKind::LIST_TYPE => function(ListTypeNode $node) {
                    return '[' . $node->type . ']';
                },
                NodeKind::NON_NULL_TYPE => function(NonNullTypeNode $node) {
                    return $node->type . '!';
                },

                // Type System Definitions
                NodeKind::SCHEMA_DEFINITION => function(SchemaDefinitionNode $def) {
                    return $this->join([
                        'schema',
                        $this->join($def->directives, ' '),
                        $this->block($def->operationTypes)
                    ], ' ');
                },
                NodeKind::OPERATION_TYPE_DEFINITION => function(OperationTypeDefinitionNode $def) {
                    return $def->operation . ': ' . $def->type;
                },

                NodeKind::SCALAR_TYPE_DEFINITION => function(ScalarTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join(['scalar', $def->name, $this->join($def->directives, ' ')], ' ')
                    ], "\n");
                },
                NodeKind::OBJECT_TYPE_DEFINITION => function(ObjectTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            'type',
                            $def->name,
                            $this->wrap('implements ', $this->join($def->interfaces, ', ')),
                            $this->join($def->directives, ' '),
                            $this->block($def->fields)
                        ], ' ')
                    ], "\n");
                },
                NodeKind::FIELD_DEFINITION => function(FieldDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $def->name
                         . $this->wrap('(', $this->join($def->arguments, ', '), ')')
                         . ': ' . $def->type
                         . $this->wrap(' ', $this->join($def->directives, ' '))
                    ], "\n");
                },
                NodeKind::INPUT_VALUE_DEFINITION => function(InputValueDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            $def->name . ': ' . $def->type,
                            $this->wrap('= ', $def->defaultValue),
                            $this->join($def->directives, ' ')
                        ], ' ')
                    ], "\n");
                },
                NodeKind::INTERFACE_TYPE_DEFINITION => function(InterfaceTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            'interface',
                            $def->name,
                            $this->join($def->directives, ' '),
                            $this->block($def->fields)
                        ], ' ')
                    ], "\n");
                },
                NodeKind::UNION_TYPE_DEFINITION => function(UnionTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            'union',
                            $def->name,
                            $this->join($def->directives, ' '),
                            $def->types
                                ? '= ' . $this->join($def->types, ' | ')
                                : ''
                        ], ' ')
                    ], "\n");
                },
                NodeKind::ENUM_TYPE_DEFINITION => function(EnumTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            'enum',
                            $def->name,
                            $this->join($def->directives, ' '),
                            $this->block($def->values)
                        ], ' ')
                    ], "\n");
                },
                NodeKind::ENUM_VALUE_DEFINITION => function(EnumValueDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([$def->name, $this->join($def->directives, ' ')], ' ')
                    ], "\n");
                },
                NodeKind::INPUT_OBJECT_TYPE_DEFINITION => function(InputObjectTypeDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        $this->join([
                            'input',
                            $def->name,
                            $this->join($def->directives, ' '),
                            $this->block($def->fields)
                        ], ' ')
                    ], "\n");
                },
                NodeKind::SCALAR_TYPE_EXTENSION => function(ScalarTypeExtensionNode $def) {
                    return $this->join([
                        'extend scalar',
                        $def->name,
                        $this->join($def->directives, ' '),
                    ], ' ');
                },
                NodeKind::OBJECT_TYPE_EXTENSION => function(ObjectTypeExtensionNode $def) {
                    return $this->join([
                        'extend type',
                        $def->name,
                        $this->wrap('implements ', $this->join($def->interfaces, ', ')),
                        $this->join($def->directives, ' '),
                        $this->block($def->fields),
                    ], ' ');
                },
                NodeKind::INTERFACE_TYPE_EXTENSION => function(InterfaceTypeExtensionNode $def) {
                    return $this->join([
                        'extend interface',
                        $def->name,
                        $this->join($def->directives, ' '),
                        $this->block($def->fields),
                    ], ' ');
                },
                NodeKind::UNION_TYPE_EXTENSION => function(UnionTypeExtensionNode $def) {
                    return $this->join([
                        'extend union',
                        $def->name,
                        $this->join($def->directives, ' '),
                        $def->types
                            ? '= ' . $this->join($def->types, ' | ')
                            : ''
                    ], ' ');
                },
                NodeKind::ENUM_TYPE_EXTENSION => function(EnumTypeExtensionNode $def) {
                    return $this->join([
                        'extend enum',
                        $def->name,
                        $this->join($def->directives, ' '),
                        $this->block($def->values),
                    ], ' ');
                },
                NodeKind::INPUT_OBJECT_TYPE_EXTENSION => function(InputObjectTypeExtensionNode $def) {
                    return $this->join([
                        'extend input',
                        $def->name,
                        $this->join($def->directives, ' '),
                        $this->block($def->fields),
                    ], ' ');
                },
                NodeKind::DIRECTIVE_DEFINITION => function(DirectiveDefinitionNode $def) {
                    return $this->join([
                        $def->description,
                        'directive @' . $def->name . $this->wrap('(', $this->join($def->arguments, ', '), ')')
                        . ' on ' . $this->join($def->locations, ' | ')
                    ], "\n");
                }
            ]
        ]);
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
        return ($array && $this->length($array))
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
                    function($x) { return !!$x;}
                )
            )
            : '';
    }

    /**
     * Print a block string in the indented block form by adding a leading and
     * trailing blank line. However, if a block string starts with whitespace and is
     * a single-line, adding a leading blank line would strip that whitespace.
     */
    private function printBlockString($value, $isDescription) {
        $escaped = str_replace('"""', '\\"""', $value);
        return (($value[0] === ' ' || $value[0] === "\t") && strpos($value, "\n") === false)
            ? ('"""' . preg_replace('/"$/', "\"\n", $escaped) . '"""')
            : ("\"\"\"\n" . ($isDescription ? $escaped : $this->indent($escaped)) . "\n\"\"\"");
    }
}
