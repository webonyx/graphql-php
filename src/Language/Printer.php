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
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
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
    public function doPrint($ast)
    {
        return Visitor::visit($ast, [
            'leave' => [
                NodeType::NAME => function(Name $node) {
                    return '' . $node->getValue();
                },
                NodeType::VARIABLE => function($node) {
                    return '$' . $node->name;
                },
                NodeType::DOCUMENT => function(Document $node) {
                    return $this->join($node->getDefinitions(), "\n\n") . "\n";
                },
                NodeType::OPERATION_DEFINITION => function(OperationDefinition $node) {
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
                NodeType::VARIABLE_DEFINITION => function(VariableDefinition $node) {
                    return $node->variable . ': ' . $node->type . $this->wrap(' = ', $node->defaultValue);
                },
                NodeType::SELECTION_SET => function(SelectionSet $node) {
                    return $this->block($node->getSelections());
                },
                NodeType::FIELD => function(Field $node) {
                    return $this->join([
                        $this->wrap('', $node->getAlias(), ': ') . $node->getName() . $this->wrap('(', $this->join($node->getArguments(), ', '), ')'),
                        $this->join($node->getDirectives(), ' '),
                        $node->getSelectionSet()
                    ], ' ');
                },
                NodeType::ARGUMENT => function(Argument $node) {
                    return $node->getName() . ': ' . $node->getValue();
                },

                // Fragments
                NodeType::FRAGMENT_SPREAD => function(FragmentSpread $node) {
                    return '...' . $node->getName() . $this->wrap(' ', $this->join($node->getDirectives(), ' '));
                },
                NodeType::INLINE_FRAGMENT => function(InlineFragment $node) {
                    return $this->join([
                        "...",
                        $this->wrap('on ', $node->getTypeCondition()),
                        $this->join($node->getDirectives(), ' '),
                        $node->getSelectionSet()
                    ], ' ');
                },
                NodeType::FRAGMENT_DEFINITION => function(FragmentDefinition $node) {
                    return "fragment {$node->getName()} on {$node->getTypeCondition()} "
                        . $this->wrap('', $this->join($node->getDirectives(), ' '), ' ')
                        . $node->getSelectionSet();
                },

                // Value
                NodeType::INT => function(IntValue  $node) {
                    return $node->getValue();
                },
                NodeType::FLOAT => function(FloatValue $node) {
                    return $node->getValue();
                },
                NodeType::STRING => function(StringValue $node) {
                    return json_encode($node->getValue());
                },
                NodeType::BOOLEAN => function(BooleanValue $node) {
                    return $node->getValue() ? 'true' : 'false';
                },
                NodeType::ENUM => function(EnumValue $node) {
                    return $node->getValue();
                },
                NodeType::LST => function(ListValue $node) {
                    return '[' . $this->join($node->getValues(), ', ') . ']';
                },
                NodeType::OBJECT => function(ObjectValue $node) {
                    return '{' . $this->join($node->getFields(), ', ') . '}';
                },
                NodeType::OBJECT_FIELD => function(ObjectField $node) {
                    return $node->getName() . ': ' . $node->getValue();
                },

                // Directive
                NodeType::DIRECTIVE => function(Directive $node) {
                    return '@' . $node->getName() . $this->wrap('(', $this->join($node->getArguments(), ', '), ')');
                },

                // Type
                NodeType::NAMED_TYPE => function(NamedType $node) {
                    return $node->getName();
                },
                NodeType::LIST_TYPE => function(ListType $node) {
                    return '[' . $node->getType() . ']';
                },
                NodeType::NON_NULL_TYPE => function(NonNullType $node) {
                    return $node->getType() . '!';
                },

                // Type System Definitions
                NodeType::SCHEMA_DEFINITION => function(SchemaDefinition $def) {
                    return $this->join([
                        'schema',
                        $this->join($def->getDirectives(), ' '),
                        $this->block($def->getOperationTypes())
                    ], ' ');
                },
                NodeType::OPERATION_TYPE_DEFINITION => function(OperationTypeDefinition $def) {
                    return $def->getOperation() . ': ' . $def->getType();
                },

                NodeType::SCALAR_TYPE_DEFINITION => function(ScalarTypeDefinition $def) {
                    return $this->join(['scalar', $def->getName(), $this->join($def->getDirectives(), ' ')], ' ');
                },
                NodeType::OBJECT_TYPE_DEFINITION => function(ObjectTypeDefinition $def) {
                    return $this->join([
                        'type',
                        $def->getName(),
                        $this->wrap('implements ', $this->join($def->getInterfaces(), ', ')),
                        $this->join($def->getDirectives(), ' '),
                        $this->block($def->getFields())
                    ], ' ');
                },
                NodeType::FIELD_DEFINITION => function(FieldDefinition $def) {
                    return $def->getName()
                         . $this->wrap('(', $this->join($def->getArguments(), ', '), ')')
                         . ': ' . $def->getType()
                         . $this->wrap(' ', $this->join($def->getDirectives(), ' '));
                },
                NodeType::INPUT_VALUE_DEFINITION => function(InputValueDefinition $def) {
                    return $this->join([
                        $def->getName() . ': ' . $def->getType(),
                        $this->wrap('= ', $def->getDefaultValue()),
                        $this->join($def->getDirectives(), ' ')
                    ], ' ');
                },
                NodeType::INTERFACE_TYPE_DEFINITION => function(InterfaceTypeDefinition $def) {
                    return $this->join([
                        'interface',
                        $def->getName(),
                        $this->join($def->getDirectives(), ' '),
                        $this->block($def->getFields())
                    ], ' ');
                },
                NodeType::UNION_TYPE_DEFINITION => function(UnionTypeDefinition $def) {
                    return $this->join([
                        'union',
                        $def->name,
                        $this->join($def->directives, ' '),
                        '= ' . $this->join($def->types, ' | ')
                    ], ' ');
                },
                NodeType::ENUM_TYPE_DEFINITION => function(EnumTypeDefinition $def) {
                    return $this->join([
                        'enum',
                        $def->getName(),
                        $this->join($def->getDirectives(), ' '),
                        $this->block($def->getValues())
                    ], ' ');
                },
                NodeType::ENUM_VALUE_DEFINITION => function(EnumValueDefinition $def) {
                    return $this->join([
                        $def->getName(),
                        $this->join($def->getDirectives(), ' ')
                    ], ' ');
                },
                NodeType::INPUT_OBJECT_TYPE_DEFINITION => function(InputObjectTypeDefinition $def) {
                    return $this->join([
                        'input',
                        $def->getName(),
                        $this->join($def->getDirectives(), ' '),
                        $this->block($def->getFields())
                    ], ' ');
                },
                NodeType::TYPE_EXTENSION_DEFINITION => function(TypeExtensionDefinition $def) {
                    return "extend {$def->definition}";
                },
                NodeType::DIRECTIVE_DEFINITION => function(DirectiveDefinition $def) {
                    return 'directive @' . $def->getName() . $this->wrap('(', $this->join($def->getArguments(), ', '), ')')
                        . ' on ' . $this->join($def->getLocations(), ' | ');
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
        return $array && $this->length($array) ? $this->indent("{\n" . $this->join($array, "\n")) . "\n}" : '{}';
    }

    public function indent($maybeString)
    {
        return $maybeString ? str_replace("\n", "\n  ", $maybeString) : '';
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
                array_filter(
                    $maybeArray,
                    function($x) { return !!$x;}
                )
            )
            : '';
    }
}
