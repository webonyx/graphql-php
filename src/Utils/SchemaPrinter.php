<?php declare(strict_types=1);

namespace GraphQL\Utils;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\BlockString;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use function implode;
use function ksort;
use function mb_strlen;
use function sprintf;
use function str_replace;
use function strlen;

/**
 * Prints the contents of a Schema in schema definition language.
 *
 * @phpstan-type Options array{}
 * @psalm-type Options array<never, never>
 */
class SchemaPrinter
{
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
            static fn (Directive $directive): bool => ! Directive::isSpecifiedDirective($directive),
            static fn (NamedType $type): bool => ! $type->isBuiltInType(),
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
     * @param callable(Type       &NamedType   $type):      bool $typeFilter
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
     * a common naming convention:.
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
     * @param array<string, bool>                                                          $options
     * @param (Type&NamedType)|Directive|EnumValueDefinition|Argument|FieldDefinition|InputObjectField $def
     */
    protected static function printDescription(array $options, $def, string $indentation = '', bool $firstInBlock = true): string
    {
        $description = $def->description;
        if ($description === null) {
            return '';
        }

        $preferMultipleLines = mb_strlen($description) > 70;
        $blockString = BlockString::print($description, '', $preferMultipleLines);
        $prefix = $indentation !== '' && ! $firstInBlock
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
     * @param array<string, bool>  $options
     * @param array<int, Argument> $args
     * @phpstan-param Options $options
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
                static fn (Argument $arg): bool => strlen($arg->description ?? '') === 0
            )
        ) {
            return '('
                . implode(
                    ', ',
                    array_map(
                        [static::class, 'printInputValue'],
                        $args
                    )
                )
                . ')';
        }

        return sprintf(
            "(\n%s\n%s)",
            implode(
                "\n",
                array_map(
                    static fn (Argument $arg, int $i): string => static::printDescription($options, $arg, '  ' . $indentation, $i === 0)
                        . '  '
                        . $indentation
                        . static::printInputValue($arg),
                    $args,
                    array_keys($args)
                )
            ),
            $indentation
        );
    }

    /**
     * @param InputObjectField|Argument $arg
     */
    protected static function printInputValue($arg): string
    {
        $argDecl = "{$arg->name}: {$arg->getType()->toString()}";

        if ($arg->defaultValueExists()) {
            $defaultValueAST = AST::astFromValue($arg->defaultValue, $arg->getType());

            if ($defaultValueAST === null) {
                $inconvertibleDefaultValue = Utils::printSafe($arg->defaultValue);
                throw new InvariantViolation("Unable to convert defaultValue of argument {$arg->name} into AST: {$inconvertibleDefaultValue}.");
            }

            $argDecl .= ' = ' . Printer::doPrint($defaultValueAST);
        }

        return $argDecl;
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printScalar(ScalarType $type, array $options): string
    {
        return static::printDescription($options, $type)
            . "scalar {$type->name}";
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printObject(ObjectType $type, array $options): string
    {
        return static::printDescription($options, $type)
            . "type {$type->name}"
            . self::printImplementedInterfaces($type)
            . static::printFields($options, $type);
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
                return static::printDescription($options, $f, '  ', $i === 0)
                    . '  '
                    . $f->name
                    . static::printArgs($options, $f->args, '  ')
                    . ': '
                    . $f->getType()->toString()
                    . static::printDeprecated($f);
            },
            $fields,
            array_keys($fields)
        );

        return self::printBlock($fields);
    }

    /**
     * @param FieldDefinition|EnumValueDefinition $fieldOrEnumVal
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

        $reasonAST = AST::astFromValue($reason, Type::string());
        assert($reasonAST instanceof StringValueNode);

        $reasonASTString = Printer::doPrint($reasonAST);

        return " @deprecated(reason: {$reasonASTString})";
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
        return static::printDescription($options, $type)
            . sprintf('interface %s', $type->name)
            . self::printImplementedInterfaces($type)
            . static::printFields($options, $type);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printUnion(UnionType $type, array $options): string
    {
        $types = $type->getTypes();
        $types = count($types) > 0
            ? ' = ' . implode(' | ', $types)
            : '';

        return static::printDescription($options, $type) . 'union ' . $type->name . $types;
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
                return static::printDescription($options, $value, '  ', $i === 0)
                    . '  '
                    . $value->name
                    . static::printDeprecated($value);
            },
            $values,
            array_keys($values)
        );

        return static::printDescription($options, $type)
            . sprintf('enum %s', $type->name)
            . static::printBlock($values);
    }

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    protected static function printInputObject(InputObjectType $type, array $options): string
    {
        $fields = [];
        $firstInBlock = true;

        foreach ($type->getFields() as $field) {
            $fields[] = static::printDescription($options, $field, '  ', $firstInBlock)
                . '  '
                . static::printInputValue($field);
            $firstInBlock = false;
        }

        return static::printDescription($options, $type)
            . "input {$type->name}"
            . static::printBlock($fields);
    }

    /**
     * @param array<string> $items
     */
    protected static function printBlock(array $items): string
    {
        return count($items) > 0
            ? " {\n" . implode("\n", $items) . "\n}"
            : '';
    }
}
