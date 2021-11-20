<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\BlockString;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ImplementingType;
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
use function array_values;
use function count;
use function explode;
use function implode;
use function is_callable;
use function is_string;
use function json_encode;
use function ksort;
use function ltrim;
use function mb_strlen;
use function mb_strpos;
use function sprintf;
use function str_replace;
use function trim;

/**
 * Prints the contents of a Schema in schema definition language.
 *
 * @phpstan-type Options array{commentDescriptions?: bool, printDirectives?: callable(DirectiveNode): bool}
 *
 *  - commentDescriptions:
 *      Provide true to use preceding comments as the description.
 *      This option is provided to ease adoption and will be removed in v16.
 *  - printDirectives
 *      Callable used to determine should be directive printed or not.
 */
class SchemaPrinter
{
    protected const LINE_LENGTH = 70;

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
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
     * @param array<string, bool> $options
     * @phpstan-param Options $options
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

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
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
     * @param callable(Directive  $directive): bool $directiveFilter
     * @param callable(Type       $type):      bool $typeFilter
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printFilteredSchema(Schema $schema, callable $directiveFilter, callable $typeFilter, array $options): string
    {
        $directives = array_filter($schema->getDirectives(), $directiveFilter);

        $types = $schema->getTypeMap();
        ksort($types);
        $types = array_filter($types, $typeFilter);

        $elements = [static::printSchemaDefinition($schema)];

        foreach ($directives as $directive) {
            $elements[] = static::printDirective($directive, $options);
        }

        foreach ($types as $type) {
            $elements[] = static::printType($type, $options);
        }

        return implode("\n\n", array_filter($elements)) . "\n";
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
     * @phpstan-param Options $options
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
        $description = $def->description;
        if ($description === null) {
            return '';
        }

        if (isset($options['commentDescriptions'])) {
            return static::printDescriptionWithComments($description, $indentation, $firstInBlock);
        }

        $preferMultipleLines = static::isLineTooLong($description);
        $blockString         = BlockString::print($description, '', $preferMultipleLines);
        $prefix              = $indentation !== '' && ! $firstInBlock
            ? "\n" . $indentation
            : $indentation;

        return $prefix . str_replace("\n", "\n" . $indentation, $blockString) . "\n";
    }

    protected static function printDescriptionWithComments(string $description, string $indentation, bool $firstInBlock): string
    {
        $comment = $indentation !== '' && ! $firstInBlock ? "\n" : '';
        foreach (explode("\n", $description) as $line) {
            if ($line === '') {
                $comment .= $indentation . "#\n";
            } else {
                $comment .= $indentation . '# ' . $line . "\n";
            }
        }

        return $comment;
    }

    /**
     * @param array<string, bool>       $options
     * @param array<int, FieldArgument> $args
     * @phpstan-param Options $options
     */
    protected static function printArgs(array $options, array $args, string $indentation = ''): string
    {
        // Empty?
        if (count($args) === 0) {
            return '';
        }

        // Print arguments
        $length      = 0;
        $arguments   = [];
        $description = false;

        foreach ($args as $i => $arg) {
            $value       = static::printArg($arg, $options, '  ' . $indentation, $i === 0);
            $length     += mb_strlen($value);
            $description = $description || mb_strlen($arg->description ?? '') > 0;
            $arguments[] = $value;
        }

        // Return
        return static::printChildrenBlock($arguments, '(', ')', $description || static::isLineTooLong($length), $indentation);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printArg(FieldArgument $type, array $options, string $indentation = '', bool $firstInBlock = true): string
    {
        return static::printDescription($options, $type, $indentation, $firstInBlock) .
            $indentation .
            static::printInputValue($type) .
            static::printTypeDirectives($type, $options, $indentation);
    }

    /**
     * @param InputObjectField|FieldArgument $arg
     */
    protected static function printInputValue($arg): string
    {
        $argDecl = $arg->name . ': ' . (string) $arg->getType();
        if ($arg->defaultValueExists()) {
            // TODO Pass `options`.
            $value       = AST::astFromValue($arg->defaultValue, $arg->getType());
            $indentation = $arg instanceof InputObjectField ? '  ' : '    ';
            $argDecl    .= ' = ' . static::printValue($value, [], $indentation);
        }

        return $argDecl;
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printScalar(ScalarType $type, array $options): string
    {
        return static::printDescription($options, $type) .
            sprintf('scalar %s', $type->name) .
            static::printTypeDirectives($type, $options);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printObject(ObjectType $type, array $options): string
    {
        return static::printDescription($options, $type) .
            sprintf('type %s', $type->name) .
            self::printImplementedInterfaces($type) .
            static::printTypeDirectives($type, $options) .
            static::printFields($options, $type);
    }

    /**
     * @param array<string, bool>      $options
     * @param ObjectType|InterfaceType $type
     * @phpstan-param Options $options
     */
    protected static function printFields(array $options, $type): string
    {
        $fields = array_values($type->getFields());
        $fields = array_map(
            static function (FieldDefinition $f, int $i) use ($options): string {
                return static::printField($f, $options, '  ', $i === 0);
            },
            $fields,
            array_keys($fields)
        );

        return self::printBlock($fields);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printField(FieldDefinition $type, array $options, string $indentation = '', bool $firstInBlock = true): string
    {
        return static::printDescription($options, $type, $indentation, $firstInBlock) .
            '  ' .
            $type->name .
            static::printArgs($options, $type->args, '  ') .
            ': ' .
            (string) $type->getType() .
            static::printTypeDirectives($type, $options, $indentation);
    }

    /**
     * @param FieldDefinition|EnumValueDefinition $fieldOrEnumVal
     */
    protected static function printDeprecated($fieldOrEnumVal): string
    {
        $reason = static::getDeprecatedReason($fieldOrEnumVal);
        if ($reason === null) {
            return '';
        }

        if ($reason === '') {
            return ' @deprecated';
        }

        return ' @deprecated(reason: ' .
            Printer::doPrint(AST::astFromValue($reason, Type::string())) . ')';
    }

    protected static function printImplementedInterfaces(ImplementingType $type): string
    {
        $interfaces = $type->getInterfaces();

        return count($interfaces) > 0
        ? ' implements ' . implode(
            ' & ',
            array_map(
                static fn (InterfaceType $interface): string => $interface->name,
                $interfaces
            )
        )
        : '';
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printInterface(InterfaceType $type, array $options): string
    {
        return static::printDescription($options, $type) .
            sprintf('interface %s', $type->name) .
            self::printImplementedInterfaces($type) .
            static::printTypeDirectives($type, $options) .
            static::printFields($options, $type);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printUnion(UnionType $type, array $options): string
    {
        $types      = $type->getTypes();
        $types      = count($types) > 0
            ? ' = ' . implode(' | ', $types)
            : '';
        $directives = static::printTypeDirectives($type, $options, '');

        if (static::isLineTooLong($directives)) {
            $types       = ltrim($types);
            $directives .= "\n";
        }

        return static::printDescription($options, $type) .
            'union ' . $type->name .
            $directives .
            $types;
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printEnum(EnumType $type, array $options): string
    {
        $values = $type->getValues();
        $values = array_map(
            static function (EnumValueDefinition $value, int $i) use ($options): string {
                return static::printEnumValue($value, $options, '  ', $i === 0);
            },
            $values,
            array_keys($values)
        );

        return static::printDescription($options, $type) .
            sprintf('enum %s', $type->name) .
            static::printTypeDirectives($type, $options) .
            static::printBlock($values);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printEnumValue(EnumValueDefinition $type, array $options, string $indentation = '', bool $firstInBlock = false): string
    {
        return static::printDescription($options, $type, $indentation, $firstInBlock) .
            '  ' .
            $type->name .
            static::printTypeDirectives($type, $options, $indentation);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printInputObject(InputObjectType $type, array $options): string
    {
        $fields = array_values($type->getFields());
        $fields = array_map(
            static function (InputObjectField $f, $i) use ($options): string {
                return static::printInputObjectField($f, $options, '  ', ! $i);
            },
            $fields,
            array_keys($fields)
        );

        return static::printDescription($options, $type) .
            sprintf('input %s', $type->name) .
            static::printTypeDirectives($type, $options) .
            static::printBlock($fields);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printInputObjectField(InputObjectField $type, array $options, string $indentation = '', bool $firstInBlock = true): string
    {
        return static::printDescription($options, $type, $indentation, $firstInBlock) .
            '  ' .
            static::printInputValue($type) .
            static::printTypeDirectives($type, $options, $indentation);
    }

    /**
     * @param array<string> $items
     */
    protected static function printBlock(array $items): string
    {
        // TODO Deprecated?
        return static::printChildrenBlock($items, ' {', '}', true);
    }

    /**
     * @param Type|EnumValueDefinition|EnumType|InterfaceType|FieldDefinition|UnionType|InputObjectType|InputObjectField|FieldArgument $type
     * @param array<string, bool>                                                                                                      $options
     * @phpstan-param Options $options
     */
    protected static function printTypeDirectives($type, array $options, string $indentation = ''): string
    {
        // Enabled?
        $filter       = $options['printDirectives'] ?? null;
        $deprecatable = $type instanceof EnumValueDefinition || $type instanceof FieldDefinition;

        if (! is_callable($filter)) {
            if ($deprecatable) {
                return static::printDeprecated($type);
            }

            return '';
        }

        // Collect directives
        $node           = $type->astNode;
        $nodeDirectives = [];

        if ($node !== null) {
            $nodeDirectives = $node->directives;
        } elseif ($deprecatable && $type->deprecationReason !== null) {
            // TODO Is there a better way to create directive node?
            $name             = Directive::DEPRECATED_NAME;
            $reason           = json_encode(static::getDeprecatedReason($type));
            $nodeDirectives[] = Parser::directive("@{$name}(reason: {$reason})");
        }

        if (count($nodeDirectives) === 0) {
            return '';
        }

        // Print
        $length     = 0;
        $directives = [];

        foreach ($nodeDirectives as $nodeDirective) {
            if (! $filter($nodeDirective)) {
                continue;
            }

            $directive    = static::printTypeDirective($nodeDirective, $options, $indentation);
            $length      += mb_strlen($directive);
            $directives[] = $directive;
        }

        // Multiline?
        $serialized = '';

        if (count($directives) > 0) {
            $delimiter  = static::isLineTooLong($length)
                ? "\n{$indentation}"
                : ' ';
            $serialized = $delimiter . implode($delimiter, $directives);
        }

        // Return
        return $serialized;
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printTypeDirective(DirectiveNode $directive, array $options, string $indentation): string
    {
        $length    = 0;
        $arguments = [];

        foreach ($directive->arguments as $argument) {
            $value       = static::printArgument($argument, $options, '  ' . $indentation);
            $length     += mb_strlen($value);
            $arguments[] = $value;
        }

        return "@{$directive->name->value}" .
            static::printChildrenBlock($arguments, '(', ')', static::isLineTooLong($length), $indentation);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printArgument(ArgumentNode $argument, array $options, string $indentation): string
    {
        return "{$indentation}{$argument->name->value}: " .
            self::printValue($argument->value, $options, $indentation);
    }

    /**
     * @param ObjectValueNode|ListValueNode|BooleanValueNode|IntValueNode|FloatValueNode|EnumValueNode|StringValueNode|NullValueNode|null $value
     * @param array<string, bool>                                                                                                         $options
     * @phpstan-param Options $options
     */
    protected static function printValue($value, array $options, string $indentation): string
    {
        $result = '';

        if ($value instanceof ListValueNode) {
            $length = 0;
            $values = [];

            foreach ($value->values as $item) {
                $string   = '  ' . $indentation . Printer::doPrint($item);
                $length  += mb_strlen($string);
                $values[] = $string;
            }

            $result = static::printChildrenBlock($values, '[', ']', static::isLineTooLong($length), $indentation);
        } else {
            $result = Printer::doPrint($value);
        }

        return $result;
    }

    /**
     * @param array<string> $lines
     */
    protected static function printChildrenBlock(array $lines, string $begin, string $end, bool $multiline, string $indentation = ''): string
    {
        $block = '';

        if (count($lines) > 0) {
            if ($multiline) {
                $wrapped = false;

                for ($i = 0, $c = count($lines); $i < $c; $i++) {
                    // If line contains LF we wrap it by empty lines
                    $line = trim($lines[$i], "\n");
                    $wrap = mb_strpos($line, "\n") !== false;

                    if ($i === 0) {
                        $line = "\n{$line}";
                    }

                    if (($wrap && $i > 0) || $wrapped) {
                        $line = "\n{$line}";
                    }

                    $block  .= "{$line}\n";
                    $wrapped = $wrap;
                }

                $block .= $indentation;
            } else {
                $block = implode(', ', array_map('trim', $lines));
            }

            $block = $begin . $block . $end;
        }

        return $block;
    }

    /**
     * @param string|int $string
     */
    protected static function isLineTooLong($string): bool
    {
        return (is_string($string) ? mb_strlen($string) : $string) > self::LINE_LENGTH;
    }

    /**
     * @param FieldDefinition|EnumValueDefinition $fieldOrEnumVal
     */
    protected static function getDeprecatedReason($fieldOrEnumVal): ?string
    {
        $reason = $fieldOrEnumVal->deprecationReason;

        if ($reason === '' || $reason === Directive::DEFAULT_DEPRECATION_REASON) {
            $reason = '';
        }

        return $reason;
    }
}
