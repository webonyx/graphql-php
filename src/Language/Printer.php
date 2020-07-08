<?php

declare(strict_types=1);

namespace GraphQL\Language;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
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

        return $instance->printAST($ast);
    }

    protected function __construct()
    {
    }

    /**
     * Traverse an AST bottom-up, converting all nodes to strings.
     *
     * We do not use strong type hints in the closures, since the AST is manipulated
     * in such a way that it no longer resembles the well-formed result of parsing.
     */
    public function printAST($ast)
    {
        return Visitor::visit(
            $ast,
            [
                'leave' => [
                    NodeKind::NAME => static function ($name) : string {
                        return $name->value;
                    },

                    NodeKind::VARIABLE => static function ($variable) : string {
                        return '$' . $variable->name;
                    },

                    NodeKind::DOCUMENT => function ($document) : string {
                        return $this->join($document->definitions, "\n\n") . "\n";
                    },

                    NodeKind::OPERATION_DEFINITION => function ($operationDefinition) {
                        $op           = $operationDefinition->operation;
                        $name         = $operationDefinition->name;
                        $varDefs      = $this->wrap('(', $this->join($operationDefinition->variableDefinitions, ', '), ')');
                        $directives   = $this->join($operationDefinition->directives, ' ');
                        $selectionSet = $operationDefinition->selectionSet;

                        // Anonymous queries with no directives or variable definitions can use
                        // the query short form.
                        return $name === null && ! $directives && ! $varDefs && $op === 'query'
                            ? $selectionSet
                            : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');
                    },

                    NodeKind::VARIABLE_DEFINITION => function ($variableDefinition) : string {
                        return $variableDefinition->variable
                            . ': '
                            . $variableDefinition->type
                            . $this->wrap(' = ', $variableDefinition->defaultValue)
                            . $this->wrap(' ', $this->join($variableDefinition->directives, ' '));
                    },

                    NodeKind::SELECTION_SET => function ($selectionSet) {
                        return $this->block($selectionSet->selections);
                    },

                    NodeKind::FIELD => function ($field) {
                        return $this->join(
                            [
                                $this->wrap('', $field->alias, ': ') . $field->name . $this->wrap(
                                    '(',
                                    $this->join($field->arguments, ', '),
                                    ')'
                                ),
                                $this->join($field->directives, ' '),
                                $field->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::ARGUMENT => static function ($argument) : string {
                        return $argument->name . ': ' . $argument->value;
                    },

                    NodeKind::FRAGMENT_SPREAD => function ($fragmentSpread) : string {
                        return '...'
                            . $fragmentSpread->name
                            . $this->wrap(' ', $this->join($fragmentSpread->directives, ' '));
                    },

                    NodeKind::INLINE_FRAGMENT => function ($inlineFragment) {
                        return $this->join(
                            [
                                '...',
                                $this->wrap('on ', $inlineFragment->typeCondition),
                                $this->join($inlineFragment->directives, ' '),
                                $inlineFragment->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::FRAGMENT_DEFINITION => function ($fragmentDefinition) : string {
                        // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                        return sprintf('fragment %s', $fragmentDefinition->name)
                            . $this->wrap('(', $this->join($fragmentDefinition->variableDefinitions, ', '), ')')
                            . sprintf(' on %s ', $fragmentDefinition->typeCondition)
                            . $this->wrap('', $this->join($fragmentDefinition->directives, ' '), ' ')
                            . $fragmentDefinition->selectionSet;
                    },

                    NodeKind::INT => static function ($int) {
                        return $int->value;
                    },

                    NodeKind::FLOAT => static function ($float) : string {
                        return $float->value;
                    },

                    NodeKind::STRING => function ($string, $key) {
                        if ($string->block) {
                            return $this->printBlockString($string->value, $key === 'description');
                        }

                        return json_encode($string->value);
                    },

                    NodeKind::BOOLEAN => static function ($boolean) {
                        return $boolean->value ? 'true' : 'false';
                    },

                    NodeKind::NULL => static function ($null) : string {
                        return 'null';
                    },

                    NodeKind::ENUM => static function ($enum) : string {
                        return $enum->value;
                    },

                    NodeKind::LST => function ($list) : string {
                        return '[' . $this->join($list->values, ', ') . ']';
                    },

                    NodeKind::OBJECT => function ($object) : string {
                        return '{' . $this->join($object->fields, ', ') . '}';
                    },

                    NodeKind::OBJECT_FIELD => static function ($objectField) : string {
                        return $objectField->name . ': ' . $objectField->value;
                    },

                    NodeKind::DIRECTIVE => function ($directive) : string {
                        return '@' . $directive->name . $this->wrap('(', $this->join($directive->arguments, ', '), ')');
                    },

                    NodeKind::NAMED_TYPE => static function ($namedType) : string {
                        return $namedType->name;
                    },

                    NodeKind::LIST_TYPE => static function ($listType) : string {
                        return '[' . $listType->type . ']';
                    },

                    NodeKind::NON_NULL_TYPE => static function ($nonNullType) : string {
                        return $nonNullType->type . '!';
                    },

                    NodeKind::SCHEMA_DEFINITION => function ($schemaDefinitionNode) {
                        return $this->join(
                            [
                                'schema',
                                $this->join($schemaDefinitionNode->directives, ' '),
                                $this->block($schemaDefinitionNode->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OPERATION_TYPE_DEFINITION => static function ($operationTypeDefinition) : string {
                        return $operationTypeDefinition->operation . ': ' . $operationTypeDefinition->type;
                    },

                    NodeKind::SCALAR_TYPE_DEFINITION => $this->addDescription(function ($scalarTypeDefinition) {
                        return $this->join(['scalar', $scalarTypeDefinition->name, $this->join($scalarTypeDefinition->directives, ' ')], ' ');
                    }),

                    NodeKind::OBJECT_TYPE_DEFINITION => $this->addDescription(function ($objectTypeDefinition) {
                        return $this->join(
                            [
                                'type',
                                $objectTypeDefinition->name,
                                $this->wrap('implements ', $this->join($objectTypeDefinition->interfaces, ' & ')),
                                $this->join($objectTypeDefinition->directives, ' '),
                                $this->block($objectTypeDefinition->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::FIELD_DEFINITION => $this->addDescription(function ($fieldDefinition) {
                        $noIndent = Utils::every($fieldDefinition->arguments, static function (string $arg) : bool {
                            return strpos($arg, "\n") === false;
                        });

                        return $fieldDefinition->name
                            . ($noIndent
                                ? $this->wrap('(', $this->join($fieldDefinition->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($fieldDefinition->arguments, "\n")), "\n)"))
                            . ': ' . $fieldDefinition->type
                            . $this->wrap(' ', $this->join($fieldDefinition->directives, ' '));
                    }),

                    NodeKind::INPUT_VALUE_DEFINITION => $this->addDescription(function ($inputValueDefinition) {
                        return $this->join(
                            [
                                $inputValueDefinition->name . ': ' . $inputValueDefinition->type,
                                $this->wrap('= ', $inputValueDefinition->defaultValue),
                                $this->join($inputValueDefinition->directives, ' '),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::INTERFACE_TYPE_DEFINITION => $this->addDescription(function ($interfaceTypeDefinition) {
                        return $this->join(
                            [
                                'interface',
                                $interfaceTypeDefinition->name,
                                $this->join($interfaceTypeDefinition->directives, ' '),
                                $this->block($interfaceTypeDefinition->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::UNION_TYPE_DEFINITION => $this->addDescription(function ($unionTypeDefinition) {
                        return $this->join(
                            [
                                'union',
                                $unionTypeDefinition->name,
                                $this->join($unionTypeDefinition->directives, ' '),
                                $unionTypeDefinition->types
                                    ? '= ' . $this->join($unionTypeDefinition->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_TYPE_DEFINITION => $this->addDescription(function ($enumTypeDefinition) {
                        return $this->join(
                            [
                                'enum',
                                $enumTypeDefinition->name,
                                $this->join($enumTypeDefinition->directives, ' '),
                                $this->block($enumTypeDefinition->values),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_VALUE_DEFINITION => $this->addDescription(function ($enumValueDefinition) {
                        return $this->join(
                            [
                                $enumValueDefinition->name,
                                $this->join($enumValueDefinition->directives, ' '),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $this->addDescription(function ($inputObjectTypeDefinition) {
                        return $this->join(
                            [
                                'input',
                                $inputObjectTypeDefinition->name,
                                $this->join($inputObjectTypeDefinition->directives, ' '),
                                $this->block($inputObjectTypeDefinition->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::SCHEMA_EXTENSION => function ($schemaTypeExtension) {
                        return $this->join(
                            [
                                'extend schema',
                                $this->join($schemaTypeExtension->directives, ' '),
                                $this->block($schemaTypeExtension->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::SCALAR_TYPE_EXTENSION => function ($scalarTypeExtension) {
                        return $this->join(
                            [
                                'extend scalar',
                                $scalarTypeExtension->name,
                                $this->join($scalarTypeExtension->directives, ' '),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OBJECT_TYPE_EXTENSION => function ($objectTypeExtension) {
                        return $this->join(
                            [
                                'extend type',
                                $objectTypeExtension->name,
                                $this->wrap('implements ', $this->join($objectTypeExtension->interfaces, ' & ')),
                                $this->join($objectTypeExtension->directives, ' '),
                                $this->block($objectTypeExtension->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INTERFACE_TYPE_EXTENSION => function ($interfaceTypeExtension) {
                        return $this->join(
                            [
                                'extend interface',
                                $interfaceTypeExtension->name,
                                $this->join($interfaceTypeExtension->directives, ' '),
                                $this->block($interfaceTypeExtension->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::UNION_TYPE_EXTENSION => function ($unionTypeExtension) {
                        return $this->join(
                            [
                                'extend union',
                                $unionTypeExtension->name,
                                $this->join($unionTypeExtension->directives, ' '),
                                $unionTypeExtension->types
                                    ? '= ' . $this->join($unionTypeExtension->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    },

                    NodeKind::ENUM_TYPE_EXTENSION => function ($enumTypeExtension) {
                        return $this->join(
                            [
                                'extend enum',
                                $enumTypeExtension->name,
                                $this->join($enumTypeExtension->directives, ' '),
                                $this->block($enumTypeExtension->values),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INPUT_OBJECT_TYPE_EXTENSION => function ($inputObjectTypeExtension) {
                        return $this->join(
                            [
                                'extend input',
                                $inputObjectTypeExtension->name,
                                $this->join($inputObjectTypeExtension->directives, ' '),
                                $this->block($inputObjectTypeExtension->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::DIRECTIVE_DEFINITION => $this->addDescription(function ($directiveDefinition) {
                        $noIndent = Utils::every($directiveDefinition->arguments, static function (string $arg) : bool {
                            return strpos($arg, "\n") === false;
                        });

                        return 'directive @'
                            . $directiveDefinition->name
                            . ($noIndent
                                ? $this->wrap('(', $this->join($directiveDefinition->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($directiveDefinition->arguments, "\n")), "\n"))
                            . ($directiveDefinition->repeatable ? ' repeatable' : '')
                            . ' on ' . $this->join($directiveDefinition->locations, ' | ');
                    }),
                ],
            ]
        );
    }

    public function addDescription(callable $cb)
    {
        return function ($node) use ($cb) {
            return $this->join([$node->description, $cb($node)], "\n");
        };
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
