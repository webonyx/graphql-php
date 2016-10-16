<?php
namespace GraphQL\Language;


use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\DirectiveDefinition;
use GraphQL\Language\AST\EnumTypeDefinition;
use GraphQL\Language\AST\EnumValueDefinition;
use GraphQL\Language\AST\FieldDefinition;
use GraphQL\Language\AST\InputObjectTypeDefinition;
use GraphQL\Language\AST\InputValueDefinition;
use GraphQL\Language\AST\InterfaceTypeDefinition;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NonNullType;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\AST\ObjectTypeDefinition;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\OperationTypeDefinition;
use GraphQL\Language\AST\ScalarTypeDefinition;
use GraphQL\Language\AST\SchemaDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\TypeExtensionDefinition;
use GraphQL\Language\AST\UnionTypeDefinition;
use GraphQL\Language\AST\VariableDefinition;

class Printer
{
    public static function doPrint($ast)
    {
        return Visitor::visit($ast, array(
            'leave' => array(
                Node::NAME => function($node) {return '' . $node->value;},
                Node::VARIABLE => function($node) {return '$' . $node->name;},
                Node::DOCUMENT => function(Document $node) {return self::join($node->definitions, "\n\n") . "\n";},
                Node::OPERATION_DEFINITION => function(OperationDefinition $node) {
                    $op = $node->operation;
                    $name = $node->name;
                    $varDefs = self::wrap('(', self::join($node->variableDefinitions, ', '), ')');
                    $directives = self::join($node->directives, ' ');
                    $selectionSet = $node->selectionSet;
                    // Anonymous queries with no directives or variable definitions can use
                    // the query short form.
                    return !$name && !$directives && !$varDefs && $op === 'query'
                        ? $selectionSet
                        : self::join([$op, self::join([$name, $varDefs]), $directives, $selectionSet], ' ');
                },
                Node::VARIABLE_DEFINITION => function(VariableDefinition $node) {
                    return $node->variable . ': ' . $node->type . self::wrap(' = ', $node->defaultValue);
                },
                Node::SELECTION_SET => function(SelectionSet $node) {
                    return self::block($node->selections);
                },
                Node::FIELD => function(Field $node) {
                    return self::join([
                        self::wrap('', $node->alias, ': ') . $node->name . self::wrap('(', self::join($node->arguments, ', '), ')'),
                        self::join($node->directives, ' '),
                        $node->selectionSet
                    ], ' ');
                },
                Node::ARGUMENT => function(Argument $node) {
                    return $node->name . ': ' . $node->value;
                },

                // Fragments
                Node::FRAGMENT_SPREAD => function(FragmentSpread $node) {
                    return '...' . $node->name . self::wrap(' ', self::join($node->directives, ' '));
                },
                Node::INLINE_FRAGMENT => function(InlineFragment $node) {
                    return self::join([
                        "...",
                        self::wrap('on ', $node->typeCondition),
                        self::join($node->directives, ' '),
                        $node->selectionSet
                    ], ' ');
                },
                Node::FRAGMENT_DEFINITION => function(FragmentDefinition $node) {
                    return "fragment {$node->name} on {$node->typeCondition} "
                        . self::wrap('', self::join($node->directives, ' '), ' ')
                        . $node->selectionSet;
                },

                // Value
                Node::INT => function(IntValue  $node) {return $node->value;},
                Node::FLOAT => function(FloatValue $node) {return $node->value;},
                Node::STRING => function(StringValue $node) {return json_encode($node->value);},
                Node::BOOLEAN => function(BooleanValue $node) {return $node->value ? 'true' : 'false';},
                Node::ENUM => function(EnumValue $node) {return $node->value;},
                Node::LST => function(ListValue $node) {return '[' . self::join($node->values, ', ') . ']';},
                Node::OBJECT => function(ObjectValue $node) {return '{' . self::join($node->fields, ', ') . '}';},
                Node::OBJECT_FIELD => function(ObjectField $node) {return $node->name . ': ' . $node->value;},

                // Directive
                Node::DIRECTIVE => function(Directive $node) {
                    return '@' . $node->name . self::wrap('(', self::join($node->arguments, ', '), ')');
                },

                // Type
                Node::NAMED_TYPE => function(NamedType $node) {return $node->name;},
                Node::LIST_TYPE => function(ListType $node) {return '[' . $node->type . ']';},
                Node::NON_NULL_TYPE => function(NonNullType $node) {return $node->type . '!';},

                // Type System Definitions
                Node::SCHEMA_DEFINITION => function(SchemaDefinition $def) {
                    return self::join([
                        'schema',
                        self::join($def->directives, ' '),
                        self::block($def->operationTypes)
                    ], ' ');
                },
                Node::OPERATION_TYPE_DEFINITION => function(OperationTypeDefinition $def) {return $def->operation . ': ' . $def->type;},

                Node::SCALAR_TYPE_DEFINITION => function(ScalarTypeDefinition $def) {
                    return self::join(['scalar', $def->name, self::join($def->directives, ' ')], ' ');
                },
                Node::OBJECT_TYPE_DEFINITION => function(ObjectTypeDefinition $def) {
                    return self::join([
                        'type',
                        $def->name,
                        self::wrap('implements ', self::join($def->interfaces, ', ')),
                        self::join($def->directives, ' '),
                        self::block($def->fields)
                    ], ' ');
                },
                Node::FIELD_DEFINITION => function(FieldDefinition $def) {
                    return $def->name
                         . self::wrap('(', self::join($def->arguments, ', '), ')')
                         . ': ' . $def->type
                         . self::wrap(' ', self::join($def->directives, ' '));
                },
                Node::INPUT_VALUE_DEFINITION => function(InputValueDefinition $def) {
                    return self::join([
                        $def->name . ': ' . $def->type,
                        self::wrap('= ', $def->defaultValue),
                        self::join($def->directives, ' ')
                    ], ' ');
                },
                Node::INTERFACE_TYPE_DEFINITION => function(InterfaceTypeDefinition $def) {
                    return self::join([
                        'interface',
                        $def->name,
                        self::join($def->directives, ' '),
                        self::block($def->fields)
                    ], ' ');
                },
                Node::UNION_TYPE_DEFINITION => function(UnionTypeDefinition $def) {
                    return self::join([
                        'union',
                        $def->name,
                        self::join($def->directives, ' '),
                        '= ' . self::join($def->types, ' | ')
                    ], ' ');
                },
                Node::ENUM_TYPE_DEFINITION => function(EnumTypeDefinition $def) {
                    return self::join([
                        'enum',
                        $def->name,
                        self::join($def->directives, ' '),
                        self::block($def->values)
                    ], ' ');
                },
                Node::ENUM_VALUE_DEFINITION => function(EnumValueDefinition $def) {
                    return self::join([
                        $def->name,
                        self::join($def->directives, ' ')
                    ], ' ');
                },
                Node::INPUT_OBJECT_TYPE_DEFINITION => function(InputObjectTypeDefinition $def) {
                    return self::join([
                        'input',
                        $def->name,
                        self::join($def->directives, ' '),
                        self::block($def->fields)
                    ], ' ');
                },
                Node::TYPE_EXTENSION_DEFINITION => function(TypeExtensionDefinition $def) {return "extend {$def->definition}";},
                Node::DIRECTIVE_DEFINITION => function(DirectiveDefinition $def) {
                    return 'directive @' . $def->name . self::wrap('(', self::join($def->arguments, ', '), ')')
                        . ' on ' . self::join($def->locations, ' | ');
                }
            )
        ));
    }

    /**
     * If maybeString is not null or empty, then wrap with start and end, otherwise
     * print an empty string.
     */
    public static function wrap($start, $maybeString, $end = '')
    {
        return $maybeString ? ($start . $maybeString . $end) : '';
    }

    /**
     * Given array, print each item on its own line, wrapped in an
     * indented "{ }" block.
     */
    public static function block($array)
    {
        return $array && self::length($array) ? self::indent("{\n" . self::join($array, "\n")) . "\n}" : '{}';
    }

    public static function indent($maybeString)
    {
        return $maybeString ? str_replace("\n", "\n  ", $maybeString) : '';
    }

    public static function manyList($start, $list, $separator, $end)
    {
        return self::length($list) === 0 ? null : ($start . self::join($list, $separator) . $end);
    }

    public static function length($maybeArray)
    {
        return $maybeArray ? count($maybeArray) : 0;
    }

    public static function join($maybeArray, $separator = '')
    {
        return $maybeArray
            ? implode(
                $separator,
                array_filter(
                    $maybeArray,
                    function($x) { return !!$x;}
                )
            )
            : '';
    }
}
