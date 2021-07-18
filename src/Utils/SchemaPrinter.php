<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function implode;
use function iterator_to_array;
use function ksort;
use function mb_strlen;
use function preg_match_all;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * Given an instance of Schema, prints it in schema definition language.
 */
class SchemaPrinter
{
    /**
     * @param array<string, bool> $options
     *    Available options:
     *    - commentDescriptions:
     *        Provide true to use preceding comments as the description.
     *        This option is provided to ease adoption and will be removed in v16.
     *
     * @api
     */
    public static function doPrint(Schema $schema, array $options = []): string
    {
        return static::printFilteredSchema(
            $schema,
            static function (Directive $directive): bool {
                return ! Directive::isSpecifiedDirective($directive);
            },
            static function (Type $type): bool {
                return ! Type::isBuiltInType($type);
            },
            $options
        );
    }

    /**
     * @param callable(Directive  $directive): bool $directiveFilter
     * @param callable(Type       $type):      bool $typeFilter
     * @param array<string, bool> $options
     */
    protected static function printFilteredSchema(Schema $schema, callable $directiveFilter, callable $typeFilter, array $options): string
    {
        $directives = array_filter($schema->getDirectives(), $directiveFilter);

        $types = $schema->getTypeMap();
        ksort($types);
        $types = array_filter($types, $typeFilter);

        return sprintf(
            "%s\n",
            implode(
                "\n\n",
                array_filter(
                    array_merge(
                        [static::printSchemaDefinition($schema)],
                        array_map(
                            static function (Directive $directive) use ($options): string {
                                return static::printDirective($directive, $options);
                            },
                            $directives
                        ),
                        array_map(
                            static function ($type) use ($options): string {
                                return static::printType($type, $options);
                            },
                            $types
                        )
                    )
                )
            )
        );
    }

    protected static function printSchemaDefinition(Schema $schema): string
    {
        if (static::isSchemaOfCommonNames($schema)) {
            return '';
        }

        $operationTypes = [];

        $queryType = $schema->getQueryType();
        if ($queryType !== null) {
            $operationTypes[] = sprintf('  query: %s', $queryType->name);
        }

        $mutationType = $schema->getMutationType();
        if ($mutationType !== null) {
            $operationTypes[] = sprintf('  mutation: %s', $mutationType->name);
        }

        $subscriptionType = $schema->getSubscriptionType();
        if ($subscriptionType !== null) {
            $operationTypes[] = sprintf('  subscription: %s', $subscriptionType->name);
        }

        return sprintf("schema {\n%s\n}", implode("\n", $operationTypes));
    }

    /**
     * GraphQL schema define root types for each type of operation. These types are
     * the same as any other type and can be named in any manner, however there is
     * a common naming convention:
     *
     *   schema {
     *     query: Query
     *     mutation: Mutation
     *   }
     *
     * When using this naming convention, the schema description can be omitted.
     */
    protected static function isSchemaOfCommonNames(Schema $schema): bool
    {
        $queryType = $schema->getQueryType();
        if ($queryType !== null && $queryType->name !== 'Query') {
            return false;
        }

        $mutationType = $schema->getMutationType();
        if ($mutationType !== null && $mutationType->name !== 'Mutation') {
            return false;
        }

        $subscriptionType = $schema->getSubscriptionType();

        return $subscriptionType === null || $subscriptionType->name === 'Subscription';
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printDirective(Directive $directive, array $options): string
    {
        return static::printDescription($options, $directive)
            . 'directive @' . $directive->name
            . static::printArgs($options, $directive->args)
            . ($directive->isRepeatable ? ' repeatable' : '')
            . ' on ' . implode(' | ', $directive->locations);
    }

    /**
     * @param array<string, bool>                                                               $options
     * @param Type|Directive|EnumValueDefinition|FieldArgument|FieldDefinition|InputObjectField $def
     */
    protected static function printDescription(array $options, $def, string $indentation = '', bool $firstInBlock = true): string
    {
        if ($def->description === null || $def->description === '') {
            return '';
        }

        $lines = static::descriptionLines($def->description, 120 - strlen($indentation));
        if (isset($options['commentDescriptions'])) {
            return static::printDescriptionWithComments($lines, $indentation, $firstInBlock);
        }

        $description = $indentation !== '' && ! $firstInBlock
            ? "\n" . $indentation . '"""'
            : $indentation . '"""';

        // In some circumstances, a single line can be used for the description.
        if (
            count($lines) === 1 &&
            mb_strlen($lines[0]) < 70 &&
            substr($lines[0], -1) !== '"'
        ) {
            return $description . static::escapeQuote($lines[0]) . "\"\"\"\n";
        }

        // Format a multi-line block quote to account for leading space.
        $hasLeadingSpace = isset($lines[0]) &&
            (
                substr($lines[0], 0, 1) === ' ' ||
                substr($lines[0], 0, 1) === '\t'
            );
        if (! $hasLeadingSpace) {
            $description .= "\n";
        }

        $lineLength = count($lines);
        for ($i = 0; $i < $lineLength; $i++) {
            if ($i !== 0 || ! $hasLeadingSpace) {
                $description .= $indentation;
            }

            $description .= static::escapeQuote($lines[$i]) . "\n";
        }

        $description .= $indentation . "\"\"\"\n";

        return $description;
    }

    /**
     * @return array<int, string>
     */
    protected static function descriptionLines(string $description, int $maxLen): array
    {
        $lines    = [];
        $rawLines = explode("\n", $description);
        foreach ($rawLines as $line) {
            if ($line === '') {
                $lines[] = $line;
            } else {
                // For > 120 character long lines, cut at space boundaries into sublines
                // of ~80 chars.
                $sublines = static::breakLine($line, $maxLen);
                foreach ($sublines as $subline) {
                    $lines[] = $subline;
                }
            }
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected static function breakLine(string $line, int $maxLen): array
    {
        if (strlen($line) < $maxLen + 5) {
            return [$line];
        }

        preg_match_all('/((?: |^).{15,' . ($maxLen - 40) . '}(?= |$))/', $line, $parts);
        $parts = $parts[0];

        return array_map('trim', $parts);
    }

    /**
     * @param array<int, string> $lines
     */
    protected static function printDescriptionWithComments(array $lines, string $indentation, bool $firstInBlock): string
    {
        $description = $indentation !== '' && ! $firstInBlock ? "\n" : '';
        foreach ($lines as $line) {
            if ($line === '') {
                $description .= $indentation . "#\n";
            } else {
                $description .= $indentation . '# ' . $line . "\n";
            }
        }

        return $description;
    }

    protected static function escapeQuote(string $line): string
    {
        return str_replace('"""', '\\"""', $line);
    }

    /**
     * @param array<string, bool>       $options
     * @param array<int, FieldArgument> $args
     */
    protected static function printArgs(array $options, array $args, string $indentation = ''): string
    {
        if (count($args) === 0) {
            return '';
        }

        // If every arg does not have a description, print them on one line.
        if (
            Utils::every(
                $args,
                static function ($arg): bool {
                    return strlen($arg->description ?? '') === 0;
                }
            )
        ) {
            return '(' . implode(', ', array_map('static::printInputValue', $args)) . ')';
        }

        return sprintf(
            "(\n%s\n%s)",
            implode(
                "\n",
                array_map(
                    static function (FieldArgument $arg, int $i) use ($indentation, $options): string {
                        return static::printDescription($options, $arg, '  ' . $indentation, $i === 0) . '  ' . $indentation .
                            static::printInputValue($arg);
                    },
                    $args,
                    array_keys($args)
                )
            ),
            $indentation
        );
    }

    /**
     * @param InputObjectField|FieldArgument $arg
     */
    protected static function printInputValue($arg): string
    {
        $argDecl = $arg->name . ': ' . (string) $arg->getType();
        if ($arg->defaultValueExists()) {
            $argDecl .= ' = ' . Printer::doPrint(AST::astFromValue($arg->defaultValue, $arg->getType()));
        }

        return $argDecl;
    }

    /**
     * @param array<string, bool> $options
     */
    public static function printType(Type $type, array $options = []): string
    {
        if ($type instanceof ScalarType) {
            return static::printScalar($type, $options);
        }

        if ($type instanceof ObjectType) {
            return static::printObject($type, $options);
        }

        if ($type instanceof InterfaceType) {
            return static::printInterface($type, $options);
        }

        if ($type instanceof UnionType) {
            return static::printUnion($type, $options);
        }

        if ($type instanceof EnumType) {
            return static::printEnum($type, $options);
        }

        if ($type instanceof InputObjectType) {
            return static::printInputObject($type, $options);
        }

        throw new Error(sprintf('Unknown type: %s.', Utils::printSafe($type)));
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printScalar(ScalarType $type, array $options): string
    {
        return sprintf('%sscalar %s', static::printDescription($options, $type), $type->name) . static::printFieldOrTypeDirectives($type);
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printObject(ObjectType $type, array $options): string
    {
        $interfaces            = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface): string {
                        return $interface->name;
                    },
                    $interfaces
                )
            )
            : '';

        return static::printDescription($options, $type) .
            sprintf("type %s%s {\n%s\n}", $type->name, $implementedInterfaces, static::printFields($options, $type));
    }

    /**
     * @param array<string, bool>      $options
     * @param ObjectType|InterfaceType $type
     */
    protected static function printFields(array $options, $type): string
    {
        $fields = array_values($type->getFields());

        return implode(
            "\n",
            array_map(
                static function (FieldDefinition $f, int $i) use ($options): string {
                    return static::printDescription($options, $f, '  ', $i === 0) . '  ' .
                        $f->name . static::printArgs($options, $f->args, '  ') . ': ' .
                        (string) $f->getType() . static::printFieldOrTypeDirectives($f);
                },
                $fields,
                array_keys($fields)
            )
        );
    }

    /**
     * @param FieldDefinition|ScalarType|EnumValueDefinition $fieldOrEnumVal
     */
    protected static function printFieldOrTypeDirectives($fieldOrEnumVal): string
    {
        $serialized = '';

        if (($fieldOrEnumVal instanceof FieldDefinition || $fieldOrEnumVal instanceof EnumValueDefinition) && $fieldOrEnumVal->deprecationReason !== null) {
            $serialized .= static::printDeprecated($fieldOrEnumVal);
        }

        if ($fieldOrEnumVal->astNode !== null) {
            foreach ($fieldOrEnumVal->astNode->directives as $directive) {
                /** @var DirectiveNode $directive */
                if ($directive->name->value === Directive::DEPRECATED_NAME && $fieldOrEnumVal->deprecationReason !== null) {
                    continue;
                }

                $serialized .= ' @' . $directive->name->value;

                if ($directive->arguments->count() === 0) {
                    continue;
                }

                $serialized .= '(' . implode(', ', array_map(static function (ArgumentNode $argument): string {
                    switch ($argument->value->kind) {
                        case NodeKind::INT:
                            $type = Type::int();
                            break;
                        case NodeKind::FLOAT:
                            $type = Type::float();
                            break;
                        case NodeKind::STRING:
                            $type = Type::string();
                            break;
                        case NodeKind::BOOLEAN:
                            $type = Type::boolean();
                            break;
                        default:
                            return '';
                    }

                    return $argument->name->value . ': ' . Printer::doPrint(AST::astFromValue($argument->value->value, $type));
                }, iterator_to_array($directive->arguments))) . ')';
            }
        }

        return $serialized;
    }

    /**
     * @param FieldArgument|EnumValueDefinition $fieldOrEnumVal
     */
    protected static function printDeprecated($fieldOrEnumVal): string
    {
        $reason = $fieldOrEnumVal->deprecationReason;
        if ($reason === null) {
            return '';
        }

        if ($reason === '' || $reason === Directive::DEFAULT_DEPRECATION_REASON) {
            return ' @deprecated';
        }

        return ' @deprecated(reason: ' .
            Printer::doPrint(AST::astFromValue($reason, Type::string())) . ')';
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printInterface(InterfaceType $type, array $options): string
    {
        $interfaces            = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface): string {
                        return $interface->name;
                    },
                    $interfaces
                )
            )
            : '';

        return static::printDescription($options, $type) .
            sprintf("interface %s%s {\n%s\n}", $type->name, $implementedInterfaces, static::printFields($options, $type));
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printUnion(UnionType $type, array $options): string
    {
        return static::printDescription($options, $type) .
            sprintf('union %s = %s', $type->name, implode(' | ', $type->getTypes()));
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printEnum(EnumType $type, array $options): string
    {
        return static::printDescription($options, $type) .
            sprintf("enum %s {\n%s\n}", $type->name, static::printEnumValues($type->getValues(), $options));
    }

    /**
     * @param array<int, EnumValueDefinition> $values
     * @param array<string, bool>             $options
     */
    protected static function printEnumValues(array $values, array $options): string
    {
        return implode(
            "\n",
            array_map(
                static function (EnumValueDefinition $value, int $i) use ($options): string {
                    return static::printDescription($options, $value, '  ', $i === 0) . '  ' .
                        $value->name . static::printFieldOrTypeDirectives($value);
                },
                $values,
                array_keys($values)
            )
        );
    }

    /**
     * @param array<string, bool> $options
     */
    protected static function printInputObject(InputObjectType $type, array $options): string
    {
        $fields = array_values($type->getFields());

        return static::printDescription($options, $type) .
            sprintf(
                "input %s {\n%s\n}",
                $type->name,
                implode(
                    "\n",
                    array_map(
                        static function ($f, $i) use ($options): string {
                            return static::printDescription($options, $f, '  ', ! $i) . '  ' . static::printInputValue($f);
                        },
                        $fields,
                        array_keys($fields)
                    )
                )
            );
    }

    /**
     * @param array<string, bool> $options
     *
     * @api
     */
    public static function printIntrospectionSchema(Schema $schema, array $options = []): string
    {
        return static::printFilteredSchema(
            $schema,
            [Directive::class, 'isSpecifiedDirective'],
            [Introspection::class, 'isIntrospectionType'],
            $options
        );
    }
}
